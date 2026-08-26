<?php

namespace App\Services;

use App\Enums\AttendanceEventType;
use App\Enums\OperationalStatus;
use App\Models\Attendance;
use App\Models\Guard;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(private GuardService $guards)
    {
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     event_type: string,
     *     occurred_at?: string|null,
     *     site_id?: int|null,
     *     shift_id?: int|null,
     *     source?: string,
     *     latitude?: float|null,
     *     longitude?: float|null,
     *     device_ref?: string|null,
     *     notes?: string|null
     * }  $data
     */
    public function record(array $data): Attendance
    {
        return DB::transaction(function () use ($data) {
            $guard = Guard::query()->findOrFail($data['guard_id']);
            $event = AttendanceEventType::from($data['event_type']);

            $attendance = Attendance::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $data['site_id'] ?? $guard->current_site_id,
                'shift_id' => $data['shift_id'] ?? null,
                'event_type' => $event,
                'source' => $data['source'] ?? 'manual',
                'occurred_at' => $data['occurred_at'] ?? now(),
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'device_ref' => $data['device_ref'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            if ($event === AttendanceEventType::CheckIn || $event === AttendanceEventType::OnDuty) {
                $this->guards->updateGuard($guard, [
                    'operational_status' => OperationalStatus::OnDuty->value,
                ], 'attendance_'.$event->value);
            }

            if ($event === AttendanceEventType::CheckOut || $event === AttendanceEventType::OffDuty) {
                if ($guard->operational_status === OperationalStatus::OnDuty) {
                    $this->guards->updateGuard($guard, [
                        'operational_status' => OperationalStatus::OffDuty->value,
                    ], 'attendance_'.$event->value);
                }
            }

            return $attendance->fresh(['assignedGuard', 'site']);
        });
    }
}
