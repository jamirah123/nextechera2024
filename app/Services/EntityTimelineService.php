<?php

namespace App\Services;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\GuardStatusHistory;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Site;
use App\Support\Audit\AuditLogUrlResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class EntityTimelineService
{
    public function __construct(private AuditLogUrlResolver $urls)
    {
    }

    /**
     * @return Collection<int, array{
     *     occurred_at: \Carbon\Carbon|null,
     *     summary: string,
     *     category: string,
     *     category_tone: string,
     *     severity_label: string|null,
     *     severity_tone: string|null,
     *     actor: string,
     *     action: string|null,
     *     url: string|null,
     *     source: string
     * }>
     */
    public function for(Model $subject, ?\App\Models\User $viewer = null, int $limit = 40): Collection
    {
        $viewer ??= auth()->user();
        $entries = collect();

        foreach ($this->auditLogsFor($subject) as $log) {
            $entries->push($this->fromAuditLog($log, $viewer));
        }

        foreach ($this->domainEntriesFor($subject) as $entry) {
            $entries->push($entry);
        }

        return $entries
            ->sortByDesc(fn (array $entry) => $entry['occurred_at']?->timestamp ?? 0)
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, AuditLog> */
    private function auditLogsFor(Model $subject): Collection
    {
        $queries = collect([
            AuditLog::query()
                ->where('subject_type', $subject::class)
                ->where('subject_id', $subject->getKey()),
        ]);

        if ($subject instanceof Guard) {
            $deploymentIds = Deployment::query()->where('guard_id', $subject->id)->pluck('id');
            $leaveIds = Leave::query()->where('guard_id', $subject->id)->pluck('id');
            $absenceIds = Absence::query()->where('guard_id', $subject->id)->pluck('id');
            $desertionIds = Desertion::query()->where('guard_id', $subject->id)->pluck('id');
            $shiftIds = Shift::query()->where('guard_id', $subject->id)->latest('id')->limit(30)->pluck('id');

            if ($deploymentIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Deployment::class)->whereIn('subject_id', $deploymentIds));
            }
            if ($leaveIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Leave::class)->whereIn('subject_id', $leaveIds));
            }
            if ($absenceIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Absence::class)->whereIn('subject_id', $absenceIds));
            }
            if ($desertionIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Desertion::class)->whereIn('subject_id', $desertionIds));
            }
            if ($shiftIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Shift::class)->whereIn('subject_id', $shiftIds));
            }
        }

        if ($subject instanceof Site) {
            $deploymentIds = Deployment::query()->where('site_id', $subject->id)->pluck('id');
            $shiftIds = Shift::query()->where('site_id', $subject->id)->latest('id')->limit(30)->pluck('id');
            $incidentIds = Incident::query()->where('site_id', $subject->id)->pluck('id');

            if ($deploymentIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Deployment::class)->whereIn('subject_id', $deploymentIds));
            }
            if ($shiftIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Shift::class)->whereIn('subject_id', $shiftIds));
            }
            if ($incidentIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Incident::class)->whereIn('subject_id', $incidentIds));
            }
        }

        if ($subject instanceof Client) {
            $invoiceIds = Invoice::query()->where('client_id', $subject->id)->pluck('id');
            $siteIds = Site::query()->where('client_id', $subject->id)->pluck('id');

            if ($invoiceIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Invoice::class)->whereIn('subject_id', $invoiceIds));
                $paymentIds = Payment::query()->whereIn('invoice_id', $invoiceIds)->pluck('id');
                if ($paymentIds->isNotEmpty()) {
                    $queries->push(AuditLog::query()->where('subject_type', Payment::class)->whereIn('subject_id', $paymentIds));
                }
            }
            if ($siteIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Site::class)->whereIn('subject_id', $siteIds));
            }
        }

        if ($subject instanceof Invoice) {
            $paymentIds = $subject->payments()->pluck('id');
            if ($paymentIds->isNotEmpty()) {
                $queries->push(AuditLog::query()->where('subject_type', Payment::class)->whereIn('subject_id', $paymentIds));
            }
        }

        $ids = collect();
        $logs = collect();

        foreach ($queries as $query) {
            foreach ($query->latest('created_at')->latest('id')->limit(50)->get() as $log) {
                if ($ids->contains($log->id)) {
                    continue;
                }
                $ids->push($log->id);
                $logs->push($log);
            }
        }

        return $logs;
    }

    /** @return list<array<string, mixed>> */
    private function domainEntriesFor(Model $subject): array
    {
        if ($subject instanceof Guard) {
            return GuardStatusHistory::query()
                ->where('guard_id', $subject->id)
                ->with('changer:id,name')
                ->latest('effective_at')
                ->limit(15)
                ->get()
                ->map(fn (GuardStatusHistory $history) => [
                    'occurred_at' => $history->effective_at,
                    'summary' => $history->previousStatusLabel().' → '.$history->newStatusLabel(),
                    'category' => $history->statusTypeLabel(),
                    'category_tone' => $history->status_type === 'employment' ? 'brand' : 'emerald',
                    'severity_label' => null,
                    'severity_tone' => null,
                    'actor' => $history->changer?->name ?? 'System',
                    'action' => $history->reason,
                    'url' => route('guards.show', $subject),
                    'source' => 'status_history',
                ])
                ->all();
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function fromAuditLog(AuditLog $log, ?\App\Models\User $viewer): array
    {
        return [
            'occurred_at' => $log->created_at,
            'summary' => $log->summary,
            'category' => $log->category->label(),
            'category_tone' => $log->category->tone(),
            'severity_label' => $log->severity->label(),
            'severity_tone' => $log->severity->tone(),
            'actor' => $log->actor_name ?? 'System',
            'action' => $log->action,
            'url' => $this->urls->resolve($log, $viewer),
            'source' => 'audit',
        ];
    }
}
