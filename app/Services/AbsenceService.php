<?php

namespace App\Services;

use App\Enums\AbsenceReason;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Models\Absence;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Services\Operations\OperationalPeriodService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AbsenceService
{
    public function __construct(
        private GuardService $guards,
        private DeploymentService $deployments,
        private AuditService $audit,
        private OperationalPeriodService $operationalPeriods,
    ) {}

    /**
     * @param  array{
     *     guard_id: int,
     *     absence_date: string,
     *     reason: string,
     *     site_id?: int|null,
     *     shift_id?: int|null,
     *     action_taken?: string|null,
     *     replacement_required?: bool,
     *     replacement_guard_id?: int|null,
     *     notes?: string|null
     * }  $data
     */
    public function record(array $data): Absence
    {
        return DB::transaction(function () use ($data) {
            $guard = Guard::query()->findOrFail($data['guard_id']);
            $absenceDate = Carbon::parse($data['absence_date'])->toDateString();

            if ($absenceDate >= now()->toDateString()) {
                throw new InvalidArgumentException('Record absences only for a day that has already passed (the missed duty date).');
            }

            $this->operationalPeriods->assertWritableForDate(
                $absenceDate,
                auth()->user(),
                $data['notes'] ?? $data['action_taken'] ?? null,
            );

            if (Absence::query()
                ->where('guard_id', $guard->id)
                ->whereDate('absence_date', $absenceDate)
                ->exists()) {
                throw new InvalidArgumentException('An absence is already recorded for this guard on that date.');
            }

            $shift = null;
            if (! empty($data['shift_id'])) {
                $shift = Shift::query()->findOrFail($data['shift_id']);
                if ((int) $shift->guard_id !== (int) $guard->id) {
                    throw new InvalidArgumentException('Selected shift does not belong to this guard.');
                }
            }

            $absence = Absence::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $data['site_id'] ?? $shift?->site_id ?? $guard->current_site_id,
                'shift_id' => $shift?->id,
                'absence_date' => $absenceDate,
                'reason' => $data['reason'] ?? AbsenceReason::NoShow->value,
                'action_taken' => $data['action_taken'] ?? null,
                'replacement_required' => (bool) ($data['replacement_required'] ?? false),
                'replacement_guard_id' => $data['replacement_guard_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'reported_by' => auth()->id(),
                'reported_at' => now(),
            ]);

            if ($shift && in_array($shift->status, [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::InProgress], true)) {
                $shift->update([
                    'status' => ShiftStatus::Missed,
                    'notes' => trim(($shift->notes ? $shift->notes."\n" : '').'Marked missed due to absence #'.$absence->id),
                ]);
            }

            // Historical absences (already past) must not tear down today's posting / status.
            // Only the most recent calendar day (yesterday) still affects current operational state.
            $affectsCurrentState = $absenceDate === now()->copy()->subDay()->toDateString();

            if ($affectsCurrentState) {
                Deployment::query()
                    ->current()
                    ->where('guard_id', $guard->id)
                    ->each(function (Deployment $deployment) use ($absenceDate): void {
                        $this->deployments->end(
                            $deployment,
                            $absenceDate,
                            'Deployment ended due to recorded absence.',
                        );
                    });

                $guard->refresh();

                $this->guards->updateGuard($guard, [
                    'operational_status' => OperationalStatus::Absent->value,
                ], 'absence_recorded');
            }

            $fresh = $absence->fresh(['assignedGuard', 'site', 'shift']);

            $this->audit->log(
                action: 'absence.recorded',
                summary: 'Absence recorded for '.$guard->employment_id.' on '.$absenceDate
                    .' (entered '.now()->toDateTimeString().')'
                    .($affectsCurrentState ? '.' : ' — historical, current status unchanged.'),
                category: AuditCategory::Hr,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'guard_id' => $guard->id,
                    'absence_date' => $absenceDate,
                    'affects_current_state' => $affectsCurrentState,
                ],
            );

            return $fresh;
        });
    }

    /**
     * Return absent guards to the deployment board once the missed day has passed.
     */
    public function releaseEligibleAbsentGuards(?Carbon $asOf = null): int
    {
        $today = ($asOf ?? now())->toDateString();

        $eligibleGuardIds = Absence::query()
            ->select('guard_id')
            ->groupBy('guard_id')
            ->havingRaw('MAX(absence_date) < ?', [$today])
            ->pluck('guard_id');

        $released = 0;

        Guard::query()
            ->where('operational_status', OperationalStatus::Absent)
            ->whereIn('id', $eligibleGuardIds)
            ->orderBy('id')
            ->each(function (Guard $guard) use (&$released): void {
                Deployment::query()
                    ->current()
                    ->where('guard_id', $guard->id)
                    ->each(function (Deployment $deployment): void {
                        $this->deployments->end(
                            $deployment,
                            now()->toDateString(),
                            'Deployment ended while releasing guard after absence.',
                        );
                    });

                $guard->refresh();

                if ($guard->operational_status !== OperationalStatus::Absent) {
                    $released++;

                    return;
                }

                $this->guards->updateGuard($guard, [
                    'current_site_id' => null,
                    'current_supervisor_id' => null,
                    'operational_status' => OperationalStatus::AwaitingDeployment->value,
                ], 'absence_release');

                $released++;
            });

        return $released;
    }

    public function clear(Absence $absence, ?string $notes = null): Absence
    {
        return DB::transaction(function () use ($absence, $notes) {
            if ($notes) {
                $absence->update(['notes' => trim(($absence->notes ? $absence->notes."\n" : '').$notes)]);
            }

            $guard = $absence->assignedGuard()->firstOrFail();

            if ($guard->operational_status === OperationalStatus::Absent) {
                $this->guards->updateGuard($guard, [
                    'operational_status' => $guard->current_site_id
                        ? OperationalStatus::OffDuty->value
                        : OperationalStatus::AwaitingDeployment->value,
                ], 'absence_cleared');
            }

            return $absence->fresh();
        });
    }
}
