<?php

namespace App\Services;

use App\Enums\AbsenceReason;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Models\Absence;
use App\Models\Guard;
use App\Models\Shift;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AbsenceService
{
    public function __construct(private GuardService $guards)
    {
    }

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
                'absence_date' => $data['absence_date'],
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

            $this->guards->updateGuard($guard, [
                'operational_status' => OperationalStatus::Absent->value,
            ], 'absence_recorded');

            return $absence->fresh(['assignedGuard', 'site', 'shift']);
        });
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
