<?php

namespace App\Services\Operations;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\OperationalPeriodStatus;
use App\Models\OperationalPeriod;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Access\RolePermissionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OperationalPeriodService
{
    public function __construct(
        private AuditService $audit,
        private RolePermissionService $permissions,
    ) {}

    public function ensureForDate(string|Carbon $date): OperationalPeriod
    {
        $carbon = Carbon::parse($date)->startOfDay();
        $year = (int) $carbon->year;
        $month = (int) $carbon->month;

        $period = OperationalPeriod::query()->where('year', $year)->where('month', $month)->first();

        if ($period) {
            return $period;
        }

        return OperationalPeriod::query()->create([
            'year' => $year,
            'month' => $month,
            'starts_on' => $carbon->copy()->startOfMonth()->toDateString(),
            'ends_on' => $carbon->copy()->endOfMonth()->toDateString(),
            'status' => OperationalPeriodStatus::Open,
        ]);
    }

    /**
     * Block writes into a finalized ops month unless the actor may correct history.
     */
    public function assertWritableForDate(string|Carbon $date, ?User $actor = null, ?string $correctionReason = null): OperationalPeriod
    {
        $period = $this->ensureForDate($date);

        if ($period->isOpen()) {
            return $period;
        }

        $actor ??= auth()->user();
        $reason = trim((string) $correctionReason);

        if ($actor && $this->permissions->userCan($actor, 'operations.historical_correct') && $reason !== '') {
            return $period;
        }

        throw new InvalidArgumentException(
            'Operational period '.$period->label().' is finalized. Re-open it, or use historical correction with a reason.'
        );
    }

    public function close(OperationalPeriod $period, ?User $actor = null, ?string $notes = null): OperationalPeriod
    {
        if ($period->isClosed()) {
            throw new InvalidArgumentException('This operational period is already finalized.');
        }

        $period->update([
            'status' => OperationalPeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $actor?->id,
            'notes' => $notes ?? $period->notes,
        ]);

        $fresh = $period->fresh(['closer']);

        $this->audit->log(
            action: 'operations.period_closed',
            summary: 'Operational period '.$fresh->label().' finalized.',
            category: AuditCategory::System,
            severity: AuditSeverity::Warning,
            subject: $fresh,
            context: ['year' => $fresh->year, 'month' => $fresh->month],
        );

        return $fresh;
    }

    public function reopen(OperationalPeriod $period, ?User $actor = null): OperationalPeriod
    {
        if ($period->isOpen()) {
            throw new InvalidArgumentException('This operational period is already open.');
        }

        $period->update([
            'status' => OperationalPeriodStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
            'notes' => trim(($period->notes ? $period->notes."\n" : '').'Re-opened by '.($actor?->name ?? 'system').' on '.now()->toDateTimeString()),
        ]);

        $fresh = $period->fresh();

        $this->audit->log(
            action: 'operations.period_reopened',
            summary: 'Operational period '.$fresh->label().' re-opened.',
            category: AuditCategory::System,
            severity: AuditSeverity::Notice,
            subject: $fresh,
            context: ['year' => $fresh->year, 'month' => $fresh->month],
        );

        return $fresh;
    }

    public function ensureRollingWindow(int $monthsAhead = 3, int $monthsBehind = 3): void
    {
        DB::transaction(function () use ($monthsAhead, $monthsBehind): void {
            $cursor = now()->copy()->startOfMonth()->subMonths($monthsBehind);
            $total = $monthsBehind + 1 + $monthsAhead;

            for ($i = 0; $i < $total; $i++) {
                $this->ensureForDate($cursor);
                $cursor->addMonth();
            }
        });
    }
}
