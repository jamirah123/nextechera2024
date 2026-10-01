<?php

namespace App\Services\Guards;

use App\Enums\LeaveStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\GuardStatusHistory;
use App\Models\Leave;
use App\Models\Absence;
use App\Models\Shift;
use Carbon\Carbon;

/**
 * Effective-date lookups: where a guard was / what applied on a calendar day.
 */
class GuardAsOfService
{
    /**
     * @return array{
     *     date: string,
     *     guard: Guard,
     *     deployment: ?Deployment,
     *     site_id: ?int,
     *     site_name: ?string,
     *     shift_type: ?string,
     *     operational_status: ?string,
     *     leave: ?Leave,
     *     absence: ?Absence,
     *     shifts: \Illuminate\Support\Collection<int, Shift>
     * }
     */
    public function snapshot(Guard $guard, string|Carbon $date): array
    {
        $day = Carbon::parse($date)->toDateString();

        $deployment = Deployment::query()
            ->with(['site:id,name,code', 'region:id,name'])
            ->where('guard_id', $guard->id)
            ->activeOnDate($day)
            ->orderByDesc('is_current')
            ->orderByDesc('id')
            ->first();

        $leave = Leave::query()
            ->where('guard_id', $guard->id)
            ->where('status', LeaveStatus::Approved)
            ->whereDate('start_date', '<=', $day)
            ->whereDate('end_date', '>=', $day)
            ->orderByDesc('id')
            ->first();

        $absence = Absence::query()
            ->where('guard_id', $guard->id)
            ->whereDate('absence_date', $day)
            ->orderByDesc('id')
            ->first();

        $shifts = Shift::query()
            ->where('guard_id', $guard->id)
            ->where('shift_date', $day)
            ->orderBy('starts_at')
            ->get();

        $statusHistory = GuardStatusHistory::query()
            ->where('guard_id', $guard->id)
            ->where('status_type', 'operational')
            ->where('effective_at', '<=', Carbon::parse($day)->endOfDay())
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();

        return [
            'date' => $day,
            'guard' => $guard,
            'deployment' => $deployment,
            'site_id' => $deployment?->site_id,
            'site_name' => $deployment?->site?->name,
            'shift_type' => $deployment?->shift_type?->value,
            'operational_status' => $statusHistory?->new_status,
            'leave' => $leave,
            'absence' => $absence,
            'shifts' => $shifts,
        ];
    }
}
