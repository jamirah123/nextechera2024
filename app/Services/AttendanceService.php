<?php

namespace App\Services;

use App\Enums\AttendanceEventType;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\OperationalStatus;
use App\Models\Attendance;
use App\Models\Guard;
use App\Services\Operations\OperationalPeriodService;
use App\Support\Historical\HistoricalDates;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function __construct(
        private GuardService $guards,
        private AuditService $audit,
        private OperationalPeriodService $operationalPeriods,
    ) {}

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
            $occurredAt = $data['occurred_at'] ?? now();
            $historicalOnly = HistoricalDates::isPastCalendarDay($occurredAt);

            $this->operationalPeriods->assertWritableForDate(
                $occurredAt,
                auth()->user(),
                $data['notes'] ?? null,
            );

            $attendance = Attendance::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $data['site_id'] ?? $guard->current_site_id,
                'shift_id' => $data['shift_id'] ?? null,
                'event_type' => $event,
                'source' => $data['source'] ?? 'manual',
                'occurred_at' => $occurredAt,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'device_ref' => $data['device_ref'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            // Past-dated attendance is historical — do not mutate current operational status.
            if (! $historicalOnly) {
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
            }

            $fresh = $attendance->fresh(['assignedGuard', 'site']);

            $this->audit->log(
                action: 'attendance.recorded',
                summary: 'Attendance '.$event->value.' for '.$guard->employment_id
                    .' (occurred '.Carbon::parse($occurredAt)->toDateTimeString()
                    .'; entered '.now()->toDateTimeString().')'
                    .($historicalOnly ? ' — historical, status unchanged.' : '.'),
                category: AuditCategory::Hr,
                severity: AuditSeverity::Notice,
                subject: $fresh,
                context: [
                    'guard_id' => $guard->id,
                    'event_type' => $event->value,
                    'occurred_at' => Carbon::parse($occurredAt)->toDateTimeString(),
                    'historical' => $historicalOnly,
                ],
            );

            return $fresh;
        });
    }
}
