<?php

namespace App\Services\Shifts;

use App\Enums\AttendanceEventType;
use App\Enums\ShiftStatus;
use App\Models\Attendance;
use App\Models\Shift;
use App\Services\ShiftService;

class ShiftLifecycleService
{
    public function __construct(private ShiftService $shifts) {}

    /**
     * @return array{started: int, completed: int, missed: int}
     */
    public function sync(): array
    {
        $now = now();
        $started = 0;
        $completed = 0;
        $missed = 0;
        $requireAttendance = (bool) config('psg.shifts.require_attendance_to_complete', false);

        Shift::query()
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed])
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderBy('id')
            ->each(function (Shift $shift) use (&$started): void {
                $this->shifts->updateStatus($shift, ShiftStatus::InProgress, automatic: true);
                $started++;
            });

        // Past window: complete automatically unless attendance gate is enabled.
        // Cancelled stays cancelled; Missed is a manual manager action (or attendance gate).
        Shift::query()
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed, ShiftStatus::InProgress])
            ->where('ends_at', '<=', $now)
            ->orderBy('id')
            ->each(function (Shift $shift) use (&$completed, &$missed, $requireAttendance): void {
                if ($requireAttendance && ! $this->hasAttendanceEvidence($shift)) {
                    $this->shifts->updateStatus($shift, ShiftStatus::Missed, automatic: true);
                    $missed++;

                    return;
                }

                $this->shifts->updateStatus($shift, ShiftStatus::Completed, automatic: true);
                $completed++;
            });

        return [
            'started' => $started,
            'completed' => $completed,
            'missed' => $missed,
        ];
    }

    public function hasAttendanceEvidence(Shift $shift): bool
    {
        $presenceEvents = [
            AttendanceEventType::CheckIn->value,
            AttendanceEventType::OnDuty->value,
            AttendanceEventType::Manual->value,
        ];

        if (Attendance::query()
            ->where('shift_id', $shift->id)
            ->whereIn('event_type', $presenceEvents)
            ->exists()) {
            return true;
        }

        return Attendance::query()
            ->where('guard_id', $shift->guard_id)
            ->where('site_id', $shift->site_id)
            ->whereNull('shift_id')
            ->whereIn('event_type', $presenceEvents)
            ->whereBetween('occurred_at', [$shift->starts_at, $shift->ends_at])
            ->exists();
    }
}
