<?php

namespace App\Services\Reports;

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Guard;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class MonthlyShiftCalculationService
{
    /**
     * Deterministic month-end shift totals.
     * Total = Normal + Overtime (completed shifts only by default).
     *
     * @param  array{
     *     year: int,
     *     month: int,
     *     region_id?: int|null,
     *     site_id?: int|null
     * }  $filters
     * @return Collection<int, array{
     *     guard_id: int,
     *     employment_id: string,
     *     full_name: string,
     *     region: string|null,
     *     site: string|null,
     *     normal_shifts: int,
     *     overtime_shifts: int,
     *     relief_shifts: int,
     *     replacement_shifts: int,
     *     special_duty_shifts: int,
     *     total_shifts: int,
     *     missed_shifts: int,
     *     cancelled_shifts: int
     * }>
     */
    public function calculate(array $filters): Collection
    {
        $start = Carbon::create((int) $filters['year'], (int) $filters['month'], 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $guards = Guard::query()
            ->with(['region:id,name', 'currentSite:id,name'])
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['site_id']), fn ($q) => $q->where('current_site_id', $filters['site_id']))
            ->orderBy('employment_id')
            ->get(['id', 'employment_id', 'full_name', 'region_id', 'current_site_id']);

        $shiftRows = Shift::query()
            ->whereBetween('shift_date', [$start->toDateString(), $end->toDateString()])
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['site_id']), fn ($q) => $q->where('site_id', $filters['site_id']))
            ->whereIn('guard_id', $guards->pluck('id'))
            ->get(['guard_id', 'shift_type', 'status']);

        $grouped = $shiftRows->groupBy('guard_id');

        return $guards->map(function (Guard $guard) use ($grouped) {
            $shifts = $grouped->get($guard->id, collect());

            $worked = $shifts->filter(fn (Shift $shift) => $shift->status === ShiftStatus::Completed);

            $normal = $worked->where('shift_type', ShiftType::Normal)->count();
            $overtime = $worked->where('shift_type', ShiftType::Overtime)->count();
            $relief = $worked->where('shift_type', ShiftType::Relief)->count();
            $replacement = $worked->where('shift_type', ShiftType::Replacement)->count();
            $special = $worked->where('shift_type', ShiftType::SpecialDuty)->count();

            return [
                'guard_id' => $guard->id,
                'employment_id' => $guard->employment_id,
                'full_name' => $guard->full_name,
                'region' => $guard->region?->name,
                'site' => $guard->currentSite?->name,
                'normal_shifts' => $normal,
                'overtime_shifts' => $overtime,
                'relief_shifts' => $relief,
                'replacement_shifts' => $replacement,
                'special_duty_shifts' => $special,
                // Master formula focuses on normal + overtime; other worked types included in total.
                'total_shifts' => $normal + $overtime + $relief + $replacement + $special,
                'missed_shifts' => $shifts->where('status', ShiftStatus::Missed)->count(),
                'cancelled_shifts' => $shifts->where('status', ShiftStatus::Cancelled)->count(),
            ];
        })->values();
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return [
            '#',
            'Employment ID',
            'Guard Name',
            'Region',
            'Site',
            'Normal Shifts',
            'Overtime Shifts',
            'Relief',
            'Replacement',
            'Special Duty',
            'Total Shifts',
            'Missed',
            'Cancelled',
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return list<list<string|int>>
     */
    public function exportRows(Collection $rows): array
    {
        return $rows->values()->map(fn (array $row, int $index) => [
            $index + 1,
            $row['employment_id'],
            $row['full_name'],
            $row['region'] ?? '',
            $row['site'] ?? '',
            $row['normal_shifts'],
            $row['overtime_shifts'],
            $row['relief_shifts'],
            $row['replacement_shifts'],
            $row['special_duty_shifts'],
            $row['total_shifts'],
            $row['missed_shifts'],
            $row['cancelled_shifts'],
        ])->all();
    }
}
