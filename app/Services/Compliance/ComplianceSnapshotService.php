<?php

namespace App\Services\Compliance;

use App\Enums\ContractStatus;
use App\Enums\CoverageStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardDocumentType;
use App\Enums\SiteStatus;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Guard;
use App\Models\GuardAttachment;
use App\Models\Site;
use App\Services\ManpowerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ComplianceSnapshotService
{
    public function __construct(private ManpowerService $manpower) {}

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $withinDays = $this->renewalWindowDays();
        $today = now()->toDateString();
        $windowEnd = now()->addDays($withinDays)->toDateString();

        $guardsExpiredDocuments = $this->guardsWithExpiredDocumentsCount();
        $guardsExpiringDocuments = $this->guardsWithExpiringDocumentsCount($withinDays);
        $guardsExpiredContracts = $this->guardsWithExpiredContractsCount();
        $guardsExpiringContracts = $this->guardsWithExpiringContractsCount($withinDays);

        $clientsExpiring = Client::query()
            ->where('contract_status', ContractStatus::Active)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$today, $windowEnd])
            ->count();

        $clientsExpired = Client::query()
            ->whereIn('contract_status', [ContractStatus::Active, ContractStatus::Expired])
            ->whereNotNull('contract_end_date')
            ->whereDate('contract_end_date', '<', $today)
            ->count();

        $sitesExpiring = Site::query()
            ->where('status', SiteStatus::Active)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [$today, $windowEnd])
            ->count();

        $slaBreaches = $this->slaBreaches();
        $slaBreachRows = $slaBreaches
            ->take(5)
            ->map(fn (array $row) => [
                'site_id' => (int) $row['site']->id,
                'site_name' => (string) $row['site']->name,
                'contracted' => (int) $row['contracted'],
                'deployed' => (int) $row['deployed'],
                'shortage' => (int) $row['shortage'],
            ])
            ->values()
            ->all();

        $activeSites = Site::query()
            ->where('status', SiteStatus::Active)
            ->where('required_guards', '>', 0)
            ->get();
        $understaffed = $this->manpower->forSites($activeSites)
            ->filter(fn (array $snap) => $snap['status'] === CoverageStatus::Understaffed)
            ->count();

        return [
            'renewal_window_days' => $withinDays,
            'guards_expired_documents' => $guardsExpiredDocuments,
            'guards_expiring_documents' => $guardsExpiringDocuments,
            'guards_expired_contracts' => $guardsExpiredContracts,
            'guards_expiring_contracts' => $guardsExpiringContracts,
            'clients_expiring_contracts' => $clientsExpiring,
            'clients_expired_contracts' => $clientsExpired,
            'sites_expiring_contracts' => $sitesExpiring,
            'sla_breach_sites' => $slaBreaches->count(),
            'understaffed_sites' => $understaffed,
            'expired_document_guards' => $this->expiredDocumentGuardSamples()->all(),
            'expiring_contracts' => $this->expiringContractSamples($withinDays)->all(),
            'sla_breaches' => $slaBreachRows,
        ];
    }

    /**
     * Cached snapshot for dashboards (short TTL — ops data changes often).
     *
     * Cache-safe: nested lists are plain arrays (no Eloquent / Collection),
     * so database cache unserialization cannot yield __PHP_Incomplete_Class.
     *
     * @return array<string, mixed>
     */
    public function cachedSnapshot(?int $ttlSeconds = 45): array
    {
        $ttl = max(15, $ttlSeconds ?? (int) config('psg.performance.dashboard_cache_seconds', 45));
        $payload = Cache::remember('psg.compliance.snapshot', $ttl, fn () => $this->snapshot());

        if (! $this->snapshotIsCacheSafe($payload)) {
            Cache::forget('psg.compliance.snapshot');
            $payload = Cache::remember('psg.compliance.snapshot', $ttl, fn () => $this->snapshot());
        }

        return $payload;
    }

    /**
     * @param  mixed  $payload
     */
    private function snapshotIsCacheSafe(mixed $payload): bool
    {
        if (! is_array($payload)) {
            return false;
        }

        foreach (['sla_breaches', 'expiring_contracts', 'expired_document_guards'] as $key) {
            if (! array_key_exists($key, $payload) || ! is_array($payload[$key])) {
                return false;
            }
        }

        foreach ($payload['sla_breaches'] as $row) {
            if (! is_array($row) || ! isset($row['site_id'], $row['site_name'], $row['shortage'])) {
                return false;
            }
        }

        return true;
    }

    public function renewalWindowDays(): int
    {
        return max(1, (int) config('psg.compliance.contract_renewal_reminder_days', 30));
    }

    public function guardsWithExpiredDocumentsCount(): int
    {
        return (int) GuardAttachment::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->distinct('guard_id')
            ->count('guard_id');
    }

    public function guardsWithExpiringDocumentsCount(int $withinDays): int
    {
        return (int) GuardAttachment::query()
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), now()->addDays($withinDays)->toDateString()])
            ->distinct('guard_id')
            ->count('guard_id');
    }

    public function guardsWithExpiredContractsCount(): int
    {
        $fromEmploymentEnd = Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereNotNull('employment_end_date')
            ->whereDate('employment_end_date', '<', now()->toDateString())
            ->pluck('id');

        $fromContractDoc = GuardAttachment::query()
            ->where('document_type', GuardDocumentType::Contract)
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->pluck('guard_id');

        return Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereIn('id', $fromEmploymentEnd->merge($fromContractDoc)->unique())
            ->count();
    }

    public function guardsWithExpiringContractsCount(int $withinDays): int
    {
        $windowEnd = now()->addDays($withinDays)->toDateString();

        $fromEmploymentEnd = Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereNotNull('employment_end_date')
            ->whereBetween('employment_end_date', [now()->toDateString(), $windowEnd])
            ->pluck('id');

        $fromContractDoc = GuardAttachment::query()
            ->where('document_type', GuardDocumentType::Contract)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now()->toDateString(), $windowEnd])
            ->pluck('guard_id');

        return Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereIn('id', $fromEmploymentEnd->merge($fromContractDoc)->unique())
            ->count();
    }

    /**
     * @return Collection<int, array{site: Site, contracted: int, deployed: int, shortage: int, profile: BillingProfile|null}>
     */
    public function slaBreaches(): Collection
    {
        $sites = Site::query()
            ->where('status', SiteStatus::Active)
            ->with(['client:id,name', 'region:id,name'])
            ->orderBy('name')
            ->get();

        $coverageBySite = $this->manpower->forSites($sites);

        return $sites
            ->map(function (Site $site) use ($coverageBySite): ?array {
                $coverage = $coverageBySite->get($site->id);
                if (! $coverage) {
                    return null;
                }

                $contracted = (int) ($coverage['contracted'] ?? 0);

                if ($contracted <= 0) {
                    return null;
                }

                $deployed = $coverage['deployed'];
                $shortage = max(0, $contracted - $deployed);

                if ($shortage <= 0) {
                    return null;
                }

                return [
                    'site' => $site,
                    'contracted' => $contracted,
                    'deployed' => $deployed,
                    'shortage' => $shortage,
                    'profile' => $coverage['billing_profile'] ?? null,
                ];
            })
            ->filter()
            ->sortByDesc(fn (array $row) => $row['shortage'])
            ->values();
    }

    /**
     * @return Collection<int, array{guard: Guard, label: string, expires_at: string}>
     */
    private function expiredDocumentGuardSamples(): Collection
    {
        return GuardAttachment::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->with('guardRecord:id,employment_id,full_name')
            ->orderBy('expires_at')
            ->get()
            ->unique('guard_id')
            ->take(5)
            ->map(fn (GuardAttachment $attachment) => [
                'guard_id' => (int) $attachment->guard_id,
                'guard_name' => (string) ($attachment->guardRecord?->full_name ?? $attachment->guardRecord?->employment_id ?? 'Guard'),
                'label' => $attachment->displayName(),
                'expires_at' => $attachment->expires_at?->format('d M Y') ?? '—',
            ])
            ->values();
    }

    /**
     * @return Collection<int, array{type: string, name: string, end_date: string, url: string|null}>
     */
    private function expiringContractSamples(int $withinDays): Collection
    {
        $windowEnd = now()->addDays($withinDays)->toDateString();
        $items = collect();

        Client::query()
            ->where('contract_status', ContractStatus::Active)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [now()->toDateString(), $windowEnd])
            ->orderBy('contract_end_date')
            ->limit(3)
            ->get(['id', 'name', 'contract_end_date'])
            ->each(function (Client $client) use ($items): void {
                $items->push([
                    'type' => 'Client contract',
                    'name' => $client->name,
                    'end_date' => $client->contract_end_date?->format('d M Y') ?? '—',
                    'url' => route('clients.show', $client),
                ]);
            });

        Site::query()
            ->where('status', SiteStatus::Active)
            ->whereNotNull('contract_end_date')
            ->whereBetween('contract_end_date', [now()->toDateString(), $windowEnd])
            ->with('client:id,name')
            ->orderBy('contract_end_date')
            ->limit(3)
            ->get(['id', 'name', 'client_id', 'contract_end_date'])
            ->each(function (Site $site) use ($items): void {
                $items->push([
                    'type' => 'Site contract',
                    'name' => $site->name,
                    'end_date' => $site->contract_end_date?->format('d M Y') ?? '—',
                    'url' => route('sites.show', $site),
                ]);
            });

        return $items->sortBy('end_date')->take(5)->values();
    }
}
