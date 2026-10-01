<?php

namespace App\Services;

use App\Enums\CoverageStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Models\BillingProfile;
use App\Models\Deployment;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SiteManpowerRequirement;
use Illuminate\Support\Collection;

class ManpowerService
{
    /** @var array<int, array{total: int, day: int, night: int}>|null */
    private ?array $deploymentCountCache = null;

    /** @var array<int, BillingProfile|null>|null keyed by site id */
    private ?array $billingProfileCache = null;

    /**
     * @return array{
     *     required: int,
     *     required_day: int,
     *     required_night: int,
     *     required_day_armed: int,
     *     required_day_unarmed: int,
     *     required_night_armed: int,
     *     required_night_unarmed: int,
     *     scheduled: int,
     *     available: int,
     *     deployed: int,
     *     deployed_day: int,
     *     deployed_night: int,
     *     shortage: int,
     *     surplus: int,
     *     shortage_day: int,
     *     shortage_night: int,
     *     coverage_percent: float,
     *     status: CoverageStatus,
     *     contracted: int,
     *     sla_shortage: int,
     *     sla_percent: float|null,
     *     billing_profile: BillingProfile|null
     * }
     */
    public function forSite(Site $site): array
    {
        return $this->forSites(collect([$site]))->get($site->id)
            ?? $this->buildSiteSnapshot($site, ['total' => 0, 'day' => 0, 'night' => 0], null);
    }

    /**
     * Batch coverage for many sites in a handful of queries.
     *
     * @param  Collection<int, Site>  $sites
     * @return Collection<int, array<string, mixed>> keyed by site id
     */
    public function forSites(Collection $sites): Collection
    {
        if ($sites->isEmpty()) {
            return collect();
        }

        $siteIds = $sites->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $counts = $this->deploymentCountsForSites($siteIds);
        $profiles = $this->billingProfilesForSites($sites);

        return $sites->mapWithKeys(function (Site $site) use ($counts, $profiles) {
            $siteId = (int) $site->id;

            return [
                $siteId => $this->buildSiteSnapshot(
                    $site,
                    $counts[$siteId] ?? ['total' => 0, 'day' => 0, 'night' => 0],
                    $profiles[$siteId] ?? null,
                ),
            ];
        });
    }

    /**
     * Required vs standing deployments vs shifts allocated for a specific date.
     *
     * @return array{
     *     date: string,
     *     required: int,
     *     required_day: int,
     *     required_night: int,
     *     deployed: int,
     *     deployed_day: int,
     *     deployed_night: int,
     *     allocated: int,
     *     allocated_day: int,
     *     allocated_night: int,
     *     shortage: int,
     *     shortage_day: int,
     *     shortage_night: int,
     *     allocation_shortage: int,
     *     allocation_shortage_day: int,
     *     allocation_shortage_night: int,
     *     coverage_percent: float,
     *     allocation_coverage_percent: float,
     *     status: CoverageStatus,
     *     allocation_status: CoverageStatus
     * }
     */
    public function forSiteOnDate(Site $site, string $date): array
    {
        $base = $this->forSite($site);

        $allocatedRows = Shift::query()
            ->forDate($date)
            ->where('site_id', $site->id)
            ->whereIn('status', ShiftStatus::blockingAllocationValues())
            ->selectRaw('period, COUNT(*) as aggregate')
            ->groupBy('period')
            ->pluck('aggregate', 'period');

        $allocatedDay = (int) ($allocatedRows[ShiftPeriod::Day->value] ?? 0);
        $allocatedNight = (int) ($allocatedRows[ShiftPeriod::Night->value] ?? 0);
        $allocated = $allocatedDay + $allocatedNight;
        $required = $base['required'];
        $requiredDay = $base['required_day'];
        $requiredNight = $base['required_night'];

        return [
            'date' => $date,
            'required' => $required,
            'required_day' => $requiredDay,
            'required_night' => $requiredNight,
            'deployed' => $base['deployed'],
            'deployed_day' => $base['deployed_day'],
            'deployed_night' => $base['deployed_night'],
            'allocated' => $allocated,
            'allocated_day' => $allocatedDay,
            'allocated_night' => $allocatedNight,
            'shortage' => $base['shortage'],
            'shortage_day' => $base['shortage_day'],
            'shortage_night' => $base['shortage_night'],
            'allocation_shortage' => max(0, $requiredDay - $allocatedDay) + max(0, $requiredNight - $allocatedNight),
            'allocation_shortage_day' => max(0, $requiredDay - $allocatedDay),
            'allocation_shortage_night' => max(0, $requiredNight - $allocatedNight),
            'coverage_percent' => $base['coverage_percent'],
            'allocation_coverage_percent' => $required > 0 ? round(($allocated / $required) * 100, 1) : 0.0,
            'status' => $base['status'],
            'allocation_status' => $this->coverageStatus($required, $allocated),
            'shifts' => $base['shifts'] ?? $this->shiftCoverageSummary($base),
            'allocation_shifts' => $this->shiftCoverageSummary([
                'required' => $required,
                'required_day' => $requiredDay,
                'required_night' => $requiredNight,
                'deployed' => $allocated,
                'deployed_day' => $allocatedDay,
                'deployed_night' => $allocatedNight,
                'shortage_day' => max(0, $requiredDay - $allocatedDay),
                'shortage_night' => max(0, $requiredNight - $allocatedNight),
                'shortage' => max(0, $requiredDay - $allocatedDay) + max(0, $requiredNight - $allocatedNight),
            ]),
        ];
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @return Collection<int, array<string, mixed>> keyed by site id
     */
    public function forSitesOnDate(Collection $sites, string $date): Collection
    {
        if ($sites->isEmpty()) {
            return collect();
        }

        $bases = $this->forSites($sites);
        $siteIds = $sites->pluck('id')->map(fn ($id) => (int) $id)->all();

        $allocatedRows = Shift::query()
            ->forDate($date)
            ->whereIn('site_id', $siteIds)
            ->whereIn('status', ShiftStatus::blockingAllocationValues())
            ->selectRaw('site_id, period, COUNT(*) as aggregate')
            ->groupBy('site_id', 'period')
            ->get()
            ->groupBy('site_id');

        return $sites->mapWithKeys(function (Site $site) use ($bases, $allocatedRows, $date) {
            $siteId = (int) $site->id;
            $base = $bases->get($siteId) ?? $this->buildSiteSnapshot($site, ['total' => 0, 'day' => 0, 'night' => 0], null);
            $periodCounts = ($allocatedRows->get($siteId) ?? collect())->mapWithKeys(function ($row) {
                $period = $row->period instanceof \BackedEnum
                    ? $row->period->value
                    : (string) $row->period;

                return [$period => (int) $row->aggregate];
            });
            $allocatedDay = (int) ($periodCounts[ShiftPeriod::Day->value] ?? 0);
            $allocatedNight = (int) ($periodCounts[ShiftPeriod::Night->value] ?? 0);
            $allocated = $allocatedDay + $allocatedNight;
            $required = $base['required'];
            $requiredDay = $base['required_day'];
            $requiredNight = $base['required_night'];

            return [
                $siteId => [
                    'date' => $date,
                    'required' => $required,
                    'required_day' => $requiredDay,
                    'required_night' => $requiredNight,
                    'deployed' => $base['deployed'],
                    'deployed_day' => $base['deployed_day'],
                    'deployed_night' => $base['deployed_night'],
                    'allocated' => $allocated,
                    'allocated_day' => $allocatedDay,
                    'allocated_night' => $allocatedNight,
                    'shortage' => $base['shortage'],
                    'shortage_day' => $base['shortage_day'],
                    'shortage_night' => $base['shortage_night'],
                    'allocation_shortage' => max(0, $requiredDay - $allocatedDay) + max(0, $requiredNight - $allocatedNight),
                    'allocation_shortage_day' => max(0, $requiredDay - $allocatedDay),
                    'allocation_shortage_night' => max(0, $requiredNight - $allocatedNight),
                    'coverage_percent' => $base['coverage_percent'],
                    'allocation_coverage_percent' => $required > 0 ? round(($allocated / $required) * 100, 1) : 0.0,
                    'status' => $base['status'],
                    'allocation_status' => $this->coverageStatus($required, $allocated),
                    'shifts' => $base['shifts'] ?? $this->shiftCoverageSummary($base),
                    'allocation_shifts' => $this->shiftCoverageSummary([
                        'required' => $required,
                        'required_day' => $requiredDay,
                        'required_night' => $requiredNight,
                        'deployed' => $allocated,
                        'deployed_day' => $allocatedDay,
                        'deployed_night' => $allocatedNight,
                        'shortage_day' => max(0, $requiredDay - $allocatedDay),
                        'shortage_night' => max(0, $requiredNight - $allocatedNight),
                        'shortage' => max(0, $requiredDay - $allocatedDay) + max(0, $requiredNight - $allocatedNight),
                    ]),
                ],
            ];
        });
    }

    /**
     * @return array{
     *     required: int,
     *     deployed: int,
     *     allocated: int,
     *     shortage: int,
     *     allocation_shortage: int,
     *     surplus: int,
     *     coverage_percent: float,
     *     allocation_coverage_percent: float,
     *     status: CoverageStatus,
     *     allocation_status: CoverageStatus,
     *     sites_count: int,
     *     understaffed_sites: int,
     *     under_allocated_sites: int
     * }
     */
    public function forCompanyOnDate(string $date, ?int $regionId = null): array
    {
        $sites = Site::query()
            ->where('status', SiteStatus::Active)
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->get();

        $snapshots = $this->forSitesOnDate($sites, $date);

        $required = 0;
        $deployed = 0;
        $allocated = 0;
        $understaffed = 0;
        $underAllocated = 0;

        foreach ($snapshots as $snap) {
            $required += $snap['required'];
            $deployed += $snap['deployed'];
            $allocated += $snap['allocated'];
            if ($snap['status'] === CoverageStatus::Understaffed) {
                $understaffed++;
            }
            if ($snap['allocation_status'] === CoverageStatus::Understaffed) {
                $underAllocated++;
            }
        }

        $shortage = max(0, $required - $deployed);
        $allocationShortage = max(0, $required - $allocated);
        $surplus = max(0, $deployed - $required);

        return [
            'required' => $required,
            'deployed' => $deployed,
            'allocated' => $allocated,
            'shortage' => $shortage,
            'allocation_shortage' => $allocationShortage,
            'surplus' => $surplus,
            'coverage_percent' => $required > 0 ? round(($deployed / $required) * 100, 1) : 0.0,
            'allocation_coverage_percent' => $required > 0 ? round(($allocated / $required) * 100, 1) : 0.0,
            'status' => $this->coverageStatus($required, $deployed),
            'allocation_status' => $this->coverageStatus($required, $allocated),
            'sites_count' => $sites->count(),
            'understaffed_sites' => $understaffed,
            'under_allocated_sites' => $underAllocated,
        ];
    }

    /**
     * @return array{
     *     required: int,
     *     deployed: int,
     *     shortage: int,
     *     surplus: int,
     *     coverage_percent: float,
     *     status: CoverageStatus,
     *     sites_count: int,
     *     understaffed_sites: int
     * }
     */
    public function forRegion(Region $region): array
    {
        $sites = $region->sites()->where('status', SiteStatus::Active)->get();

        return $this->aggregate($sites);
    }

    /**
     * @return array{
     *     required: int,
     *     deployed: int,
     *     shortage: int,
     *     surplus: int,
     *     coverage_percent: float,
     *     status: CoverageStatus,
     *     sites_count: int,
     *     understaffed_sites: int
     * }
     */
    public function forCompany(): array
    {
        $sites = Site::query()->where('status', SiteStatus::Active)->get();

        return $this->aggregate($sites);
    }

    /**
     * Clear request-local caches (useful after writes in the same request).
     */
    public function flushRequestCache(): void
    {
        $this->deploymentCountCache = null;
        $this->billingProfileCache = null;
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @return array{
     *     required: int,
     *     deployed: int,
     *     shortage: int,
     *     surplus: int,
     *     coverage_percent: float,
     *     status: CoverageStatus,
     *     sites_count: int,
     *     understaffed_sites: int
     * }
     */
    private function aggregate(Collection $sites): array
    {
        $snapshots = $this->forSites($sites);

        $required = 0;
        $deployed = 0;
        $understaffed = 0;

        foreach ($snapshots as $snap) {
            $required += $snap['required'];
            $deployed += $snap['deployed'];
            if ($snap['status'] === CoverageStatus::Understaffed) {
                $understaffed++;
            }
        }

        $shortage = max(0, $required - $deployed);
        $surplus = max(0, $deployed - $required);
        $coverage = $required > 0 ? round(($deployed / $required) * 100, 1) : 0.0;

        return [
            'required' => $required,
            'deployed' => $deployed,
            'shortage' => $shortage,
            'surplus' => $surplus,
            'coverage_percent' => $coverage,
            'status' => $this->coverageStatus($required, $deployed),
            'sites_count' => $sites->count(),
            'understaffed_sites' => $understaffed,
        ];
    }

    /**
     * @param  array{total: int, day: int, night: int}  $counts
     * @return array<string, mixed>
     */
    private function buildSiteSnapshot(Site $site, array $counts, ?BillingProfile $billingProfile): array
    {
        $required = (int) $site->required_guards;
        $requiredDay = (int) $site->required_day_guards;
        $requiredNight = (int) $site->required_night_guards;
        $deployed = $counts['total'];
        $deployedDay = $counts['day'];
        $deployedNight = $counts['night'];

        $shortageDay = max(0, $requiredDay - $deployedDay);
        $shortageNight = max(0, $requiredNight - $deployedNight);

        // Prefer per-shift remaining shortage when day/night requirements are configured.
        $periodConfigured = ($requiredDay + $requiredNight) > 0;
        $shortage = $periodConfigured
            ? ($shortageDay + $shortageNight)
            : max(0, $required - $deployed);
        $requiredForCoverage = $periodConfigured ? ($requiredDay + $requiredNight) : $required;
        if ($requiredForCoverage <= 0) {
            $requiredForCoverage = $required;
        }

        $surplus = max(0, $deployed - $requiredForCoverage);
        $coverage = $requiredForCoverage > 0 ? round(($deployed / $requiredForCoverage) * 100, 1) : 0.0;

        $contracted = $billingProfile?->contractedGuardTotal() ?? 0;
        $slaShortage = $contracted > 0 ? max(0, $contracted - $deployed) : 0;
        $slaPercent = $contracted > 0 ? round(($deployed / $contracted) * 100, 1) : null;

        return [
            'required' => $requiredForCoverage > 0 ? $requiredForCoverage : $required,
            'required_day' => $requiredDay,
            'required_night' => $requiredNight,
            'required_day_armed' => (int) $site->required_day_armed_guards,
            'required_day_unarmed' => (int) $site->required_day_unarmed_guards,
            'required_night_armed' => (int) $site->required_night_armed_guards,
            'required_night_unarmed' => (int) $site->required_night_unarmed_guards,
            'scheduled' => $deployed,
            'available' => $deployed,
            'deployed' => $deployed,
            'deployed_day' => $deployedDay,
            'deployed_night' => $deployedNight,
            'shortage' => $shortage,
            'surplus' => $surplus,
            'shortage_day' => $shortageDay,
            'shortage_night' => $shortageNight,
            'coverage_percent' => $coverage,
            'status' => $this->coverageStatus($requiredForCoverage > 0 ? $requiredForCoverage : $required, $deployed),
            'contracted' => $contracted,
            'sla_shortage' => $slaShortage,
            'sla_percent' => $slaPercent,
            'billing_profile' => $billingProfile,
            'shifts' => $this->shiftCoverageSummary([
                'required_day' => $requiredDay,
                'required_night' => $requiredNight,
                'deployed_day' => $deployedDay,
                'deployed_night' => $deployedNight,
                'shortage_day' => $shortageDay,
                'shortage_night' => $shortageNight,
                'required' => $requiredForCoverage > 0 ? $requiredForCoverage : $required,
                'deployed' => $deployed,
                'shortage' => $shortage,
            ]),
        ];
    }

    /**
     * Shift-aware coverage breakdown for dashboards and site summaries.
     *
     * Optional OT gap fields preserve original shortage history while showing
     * temporary overtime that closes the remaining gap for a period.
     *
     * @param  array<string, mixed>  $snapshot
     * @param  array{day?: array{original_shortage?: int, overtime_covered?: int, remaining_shortage?: int}, night?: array{original_shortage?: int, overtime_covered?: int, remaining_shortage?: int}}  $otGaps
     * @return array{
     *     required: int,
     *     deployed: int,
     *     remaining: int,
     *     day: array<string, mixed>,
     *     night: array<string, mixed>
     * }
     */
    public function shiftCoverageSummary(array $snapshot, array $otGaps = []): array
    {
        $day = $this->periodCoverageRow(
            required: (int) ($snapshot['required_day'] ?? 0),
            permanent: (int) ($snapshot['deployed_day'] ?? 0),
            permanentShortage: (int) ($snapshot['shortage_day'] ?? 0),
            ot: $otGaps['day'] ?? null,
            label: 'Day',
        );
        $night = $this->periodCoverageRow(
            required: (int) ($snapshot['required_night'] ?? 0),
            permanent: (int) ($snapshot['deployed_night'] ?? 0),
            permanentShortage: (int) ($snapshot['shortage_night'] ?? 0),
            ot: $otGaps['night'] ?? null,
            label: 'Night',
        );

        $required = (int) ($snapshot['required'] ?? ($day['required'] + $night['required']));
        $deployed = (int) ($snapshot['deployed'] ?? ($day['permanent'] + $night['permanent']));
        $remaining = (int) ($day['remaining'] + $night['remaining']);
        $deficit = (int) ($day['deficit'] + $night['deficit']);

        return [
            'required' => $required,
            'deployed' => $deployed,
            'remaining' => $remaining,
            'deficit' => $deficit,
            'overtime' => (int) ($day['overtime'] + $night['overtime']),
            'day' => $day,
            'night' => $night,
        ];
    }

    /**
     * @param  array{original_shortage?: int, overtime_covered?: int, remaining_shortage?: int}|null  $ot
     * @return array{
     *     label: string,
     *     required: int,
     *     permanent: int,
     *     overtime: int,
     *     covered: int,
     *     remaining: int,
     *     original_shortage: int,
     *     status: string,
     *     headline: string,
     *     detail: string|null
     * }
     */
    private function periodCoverageRow(int $required, int $permanent, int $permanentShortage, ?array $ot, string $label): array
    {
        $overtime = max(0, (int) ($ot['overtime_covered'] ?? 0));
        $originalShortage = max($permanentShortage, (int) ($ot['original_shortage'] ?? $permanentShortage));
        $deployed = $permanent + $overtime;
        $covered = $required > 0 ? min($required, $deployed) : $deployed;
        $remaining = max(0, $required - $deployed);
        $deficit = max(0, $required - $permanent);
        $excess = max(0, $deployed - $required);

        if ($required <= 0) {
            $status = 'unconfigured';
            $headline = "{$label}: not configured";
            $short = '—';
            $detail = null;
        } elseif ($excess > 0) {
            $status = 'overstaffed';
            $headline = "{$label}: Overstaffed — {$deployed} deployed against {$required}, excess {$excess}.";
            $short = "+ {$deployed}/{$required}";
            $detail = "{$permanent} normal + {$overtime} overtime";
        } elseif ($remaining > 0) {
            $status = 'understaffed';
            $headline = "{$label}: Shortage — covered {$covered}/{$required}, remaining {$remaining}, deficit {$deficit}.";
            $short = "! {$covered}/{$required}";
            $detail = "{$permanent} normal + {$overtime} overtime";
        } elseif ($deficit > 0) {
            $status = 'ot_supported';
            $headline = "{$label}: Fully covered operationally — {$deficit} normal manpower deficit supported by overtime.";
            $short = "OT {$covered}/{$required}";
            $detail = "{$permanent} normal + {$overtime} temporary cover";
        } else {
            $status = 'covered';
            $headline = "{$label}: Fully covered ({$covered}/{$required})";
            $short = "✓ {$covered}/{$required}";
            $detail = null;
        }

        return [
            'label' => $label,
            'required' => $required,
            'permanent' => $permanent,
            'overtime' => $overtime,
            'covered' => $covered,
            'remaining' => $remaining,
            'deficit' => $deficit,
            'excess' => $excess,
            'original_shortage' => $originalShortage,
            'status' => $status,
            'headline' => $headline,
            'short' => $short,
            'detail' => $detail,
        ];
    }

    /**
     * Site-level posture. Operational cover filled by overtime stays a normal-manpower deficit.
     *
     * @param  array<string, mixed>  $shifts
     * @return array{key: string, label: string, tone: string}
     */
    public function sitePosture(array $shifts): array
    {
        $required = 0;
        $deployed = 0;
        $remaining = 0;
        $deficit = 0;

        foreach (['day', 'night'] as $period) {
            $row = is_array($shifts[$period] ?? null) ? $shifts[$period] : [];
            $required += (int) ($row['required'] ?? 0);
            $deployed += (int) ($row['permanent'] ?? 0) + (int) ($row['overtime'] ?? 0);
            $remaining += (int) ($row['remaining'] ?? 0);
            $deficit += (int) ($row['deficit'] ?? 0);
        }

        if ($required <= 0) {
            return ['key' => 'unconfigured', 'label' => 'Unconfigured', 'tone' => 'slate'];
        }

        if ($remaining > 0) {
            return ['key' => 'shortage', 'label' => 'Shortage', 'tone' => 'rose'];
        }

        if ($deployed > $required) {
            return ['key' => 'overstaffed', 'label' => 'Overstaffed', 'tone' => 'sky'];
        }

        if ($deficit > 0) {
            return ['key' => 'ot_supported', 'label' => 'OT Supported', 'tone' => 'amber'];
        }

        return ['key' => 'covered', 'label' => 'Fully Covered', 'tone' => 'emerald'];
    }

    /**
     * @param  list<int>  $siteIds
     * @return array<int, array{total: int, day: int, night: int}>
     */
    private function deploymentCountsForSites(array $siteIds): array
    {
        if ($siteIds === []) {
            return [];
        }

        $missing = array_values(array_filter(
            $siteIds,
            fn (int $id) => $this->deploymentCountCache === null || ! array_key_exists($id, $this->deploymentCountCache),
        ));

        if ($missing !== []) {
            $this->deploymentCountCache ??= [];

            foreach ($missing as $id) {
                $this->deploymentCountCache[$id] = ['total' => 0, 'day' => 0, 'night' => 0];
            }

            $rows = Deployment::query()
                ->current()
                ->permanent()
                ->whereIn('site_id', $missing)
                ->selectRaw('site_id, shift_type, COUNT(*) as aggregate')
                ->groupBy('site_id', 'shift_type')
                ->get();

            foreach ($rows as $row) {
                $siteId = (int) $row->site_id;
                $count = (int) $row->aggregate;
                $type = $row->shift_type instanceof \BackedEnum
                    ? $row->shift_type->value
                    : (string) $row->shift_type;

                $this->deploymentCountCache[$siteId]['total'] += $count;

                if ($type === DeploymentShiftType::Day->value || $type === DeploymentShiftType::Rotating->value) {
                    $this->deploymentCountCache[$siteId]['day'] += $count;
                }

                if ($type === DeploymentShiftType::Night->value || $type === DeploymentShiftType::Rotating->value) {
                    $this->deploymentCountCache[$siteId]['night'] += $count;
                }
            }
        }

        $result = [];
        foreach ($siteIds as $id) {
            $result[$id] = $this->deploymentCountCache[$id] ?? ['total' => 0, 'day' => 0, 'night' => 0];
        }

        return $result;
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @return array<int, BillingProfile|null>
     */
    private function billingProfilesForSites(Collection $sites): array
    {
        $this->billingProfileCache ??= [];

        $needed = $sites->filter(
            fn (Site $site) => ! array_key_exists((int) $site->id, $this->billingProfileCache),
        );

        if ($needed->isNotEmpty()) {
            $today = now()->toDateString();
            $clientIds = $needed->pluck('client_id')->filter()->unique()->values()->all();

            $profiles = BillingProfile::query()
                ->active()
                ->whereIn('client_id', $clientIds)
                ->whereDate('effective_from', '<=', $today)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
                ->orderByDesc('effective_from')
                ->get();

            $bySite = $profiles->whereNotNull('site_id')->groupBy(fn (BillingProfile $profile) => (int) $profile->site_id);
            $byClient = $profiles->whereNull('site_id')->groupBy(fn (BillingProfile $profile) => (int) $profile->client_id);

            foreach ($needed as $site) {
                $siteId = (int) $site->id;
                $clientId = (int) $site->client_id;
                $siteProfile = ($bySite->get($siteId) ?? collect())->first();
                $clientProfile = ($byClient->get($clientId) ?? collect())->first();
                $this->billingProfileCache[$siteId] = $siteProfile ?? $clientProfile;
            }
        }

        $result = [];
        foreach ($sites as $site) {
            $siteId = (int) $site->id;
            $result[$siteId] = $this->billingProfileCache[$siteId] ?? null;
        }

        return $result;
    }

    private function coverageStatus(int $required, int $deployed): CoverageStatus
    {
        if ($required <= 0) {
            return CoverageStatus::Unconfigured;
        }

        if ($deployed < $required) {
            return CoverageStatus::Understaffed;
        }

        if ($deployed > $required) {
            return CoverageStatus::Overstaffed;
        }

        return CoverageStatus::FullyStaffed;
    }

    /**
     * Date-effective manpower for the posting board.
     *
     * Required counts come from the site manpower revision covering $date.
     * When no revision covers that date, the site's configured day and night
     * requirements are used. Counts are never hard-coded.
     *
     * A past duty date counts every posting whose dates cover that day.
     * Today and later count only postings that are still open, so a posting
     * closed this morning is not treated as deployed.
     *
     * @param  Collection<int, Site>  $sites
     * @return array<string, array{
     *     name: string,
     *     code: string|null,
     *     day: array{required: int, normal: int, ot: int, cover: int, operational: int, remaining: int, deficit: int},
     *     night: array{required: int, normal: int, ot: int, cover: int, operational: int, remaining: int, deficit: int}
     * }>
     */
    public function postingBoardCoverage(Collection $sites, string $date): array
    {
        $sites = $sites->keyBy(fn (Site $site) => $site->id);
        if ($sites->isEmpty()) {
            return [];
        }

        $requirements = SiteManpowerRequirement::query()
            ->whereIn('site_id', $sites->keys())
            ->whereDate('effective_from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique('site_id')
            ->keyBy('site_id');

        $deployments = Deployment::query()
            ->whereIn('site_id', $sites->keys())
            ->whereIn('status', [
                DeploymentStatus::Active,
                DeploymentStatus::Ended,
                DeploymentStatus::Transferred,
            ])
            ->whereDate('start_date', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $date);
            })
            ->when($date >= now()->toDateString(), function ($query): void {
                $query->where('is_current', true);
            })
            ->get(['site_id', 'shift_type', 'is_temporary', 'duty_type']);

        /** @var array<int, array<string, array{normal: int, ot: int, cover: int}>> $counts */
        $counts = [];
        foreach ($deployments as $deployment) {
            $periods = $deployment->shift_type === DeploymentShiftType::Rotating
                ? ['day', 'night']
                : [$deployment->shift_type === DeploymentShiftType::Night ? 'night' : 'day'];

            $bucket = 'normal';
            if ($deployment->is_temporary) {
                $bucket = $deployment->duty_type === ShiftType::Overtime ? 'ot' : 'cover';
            }

            foreach ($periods as $period) {
                $counts[$deployment->site_id][$period][$bucket] = ($counts[$deployment->site_id][$period][$bucket] ?? 0) + 1;
            }
        }

        $payload = [];
        foreach ($sites as $site) {
            $requirement = $requirements->get($site->id);
            $dayRequired = $requirement ? (int) $requirement->required_day : (int) $site->required_day_guards;
            $nightRequired = $requirement ? (int) $requirement->required_night : (int) $site->required_night_guards;
            $siteCounts = $counts[$site->id] ?? [];

            $payload[(string) $site->id] = [
                'name' => $site->name,
                'code' => $site->code,
                'day' => $this->boardPeriod(
                    $dayRequired,
                    $siteCounts['day']['normal'] ?? 0,
                    $siteCounts['day']['ot'] ?? 0,
                    $siteCounts['day']['cover'] ?? 0,
                ),
                'night' => $this->boardPeriod(
                    $nightRequired,
                    $siteCounts['night']['normal'] ?? 0,
                    $siteCounts['night']['ot'] ?? 0,
                    $siteCounts['night']['cover'] ?? 0,
                ),
            ];
        }

        return $payload;
    }

    /**
     * Projected headcount if the selected guards are posted on top of the
     * deployments already covering the duty date.
     *
     * Projected total = existing deployed + selected guards.
     * Excess = max(0, projected total − required manpower).
     *
     * @param  array<string, array{name?: string, day?: array<string, int>, night?: array<string, int>}>  $coverage
     * @param  list<array{site_id?: int, shift_type?: string}>  $selections
     * @return list<array{site: string, period: string, required: int, deployed: int, selected: int, projected: int, excess: int}>
     */
    public function overstaffingWarnings(array $coverage, array $selections): array
    {
        $selected = [];

        foreach ($selections as $selection) {
            $siteId = (string) ($selection['site_id'] ?? '');
            if ($siteId === '') {
                continue;
            }

            $shift = (string) ($selection['shift_type'] ?? 'day');
            $periods = match ($shift) {
                'rotating' => ['day', 'night'],
                'night' => ['night'],
                default => ['day'],
            };

            foreach ($periods as $period) {
                $selected[$siteId][$period] = ($selected[$siteId][$period] ?? 0) + 1;
            }
        }

        $warnings = [];

        foreach ($selected as $siteId => $periods) {
            $record = $coverage[$siteId] ?? null;
            if (! is_array($record)) {
                continue;
            }

            foreach ($periods as $period => $count) {
                $row = is_array($record[$period] ?? null) ? $record[$period] : [];
                $required = (int) ($row['required'] ?? 0);
                if ($required <= 0 || $count <= 0) {
                    continue;
                }

                $deployed = (int) ($row['normal'] ?? 0) + (int) ($row['ot'] ?? 0) + (int) ($row['cover'] ?? 0);
                $projected = $deployed + $count;
                $excess = max(0, $projected - $required);
                if ($excess <= 0) {
                    continue;
                }

                $warnings[] = [
                    'site' => (string) ($record['name'] ?? 'Site'),
                    'period' => $period === 'night' ? 'Night' : 'Day',
                    'required' => $required,
                    'deployed' => $deployed,
                    'selected' => $count,
                    'projected' => $projected,
                    'excess' => $excess,
                ];
            }
        }

        return $warnings;
    }

    /**
     * @return array{required: int, normal: int, ot: int, cover: int, operational: int, remaining: int, deficit: int}
     */
    private function boardPeriod(int $required, int $normal, int $ot, int $cover): array
    {
        $filled = $normal + $ot + $cover;
        $operational = $required > 0 ? min($required, $filled) : $filled;

        return [
            'required' => $required,
            'normal' => $normal,
            'ot' => $ot,
            'cover' => $cover,
            'operational' => $operational,
            'remaining' => max(0, $required - $filled),
            'deficit' => max(0, $required - $normal),
        ];
    }
}
