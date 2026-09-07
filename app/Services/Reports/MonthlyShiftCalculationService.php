<?php

namespace App\Services\Reports;

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Guard;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
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
        $guards = $this->guardQuery($filters)
            ->with(['region:id,name', 'currentSite:id,name', 'supervisorProfile:id,guard_id,supervisor_code'])
            ->get(['id', 'employment_id', 'full_name', 'region_id', 'current_site_id']);

        return $this->mapRows($filters, $guards);
    }

    /**
     * @param  array{
     *     year: int,
     *     month: int,
     *     region_id?: int|null,
     *     site_id?: int|null
     * }  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(array $filters, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= table_per_page();

        /** @var LengthAwarePaginator<int, Guard> $paginator */
        $paginator = $this->guardQuery($filters)
            ->with(['region:id,name', 'currentSite:id,name', 'supervisorProfile:id,guard_id,supervisor_code'])
            ->paginate($perPage)
            ->withQueryString();

        $rows = $this->mapRows($filters, $paginator->getCollection());

        /** @var LengthAwarePaginator<int, array<string, mixed>> $pagedRows */
        $pagedRows = $paginator->setCollection($rows);

        return $pagedRows;
    }

    /**
     * Month-wide KPI totals (not limited to the current page).
     *
     * @param  array{
     *     year: int,
     *     month: int,
     *     region_id?: int|null,
     *     site_id?: int|null
     * }  $filters
     * @return array{normal: int, overtime: int, total: int, guards: int}
     */
    public function summaryTotals(array $filters): array
    {
        $start = Carbon::create((int) $filters['year'], (int) $filters['month'], 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $guardQuery = $this->guardQuery($filters);
        $guards = (clone $guardQuery)->count();

        $counts = Shift::query()
            ->whereBetween('shift_date', [$start->toDateString(), $end->toDateString()])
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['site_id']), fn ($q) => $q->where('site_id', $filters['site_id']))
            ->whereIn('guard_id', (clone $guardQuery)->select('id'))
            ->whereIn('status', ShiftStatus::payableValues())
            ->whereIn('shift_type', [
                ShiftType::Normal->value,
                ShiftType::Overtime->value,
                ShiftType::Relief->value,
                ShiftType::Replacement->value,
                ShiftType::SpecialDuty->value,
            ])
            ->selectRaw('shift_type, COUNT(*) as aggregate')
            ->groupBy('shift_type')
            ->pluck('aggregate', 'shift_type');

        $normal = (int) ($counts[ShiftType::Normal->value] ?? 0);
        $overtime = (int) ($counts[ShiftType::Overtime->value] ?? 0);
        $other = (int) ($counts[ShiftType::Relief->value] ?? 0)
            + (int) ($counts[ShiftType::Replacement->value] ?? 0)
            + (int) ($counts[ShiftType::SpecialDuty->value] ?? 0);

        return [
            'normal' => $normal,
            'overtime' => $overtime,
            'total' => $normal + $overtime + $other,
            'guards' => $guards,
        ];
    }

    /**
     * @return list<string>
     */
    public function exportHeaders(): array
    {
        return [
            '#',
            'Employment ID',
            'Name',
            'Role',
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
            $row['role'],
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

    /**
     * @param  array{
     *     year: int,
     *     month: int,
     *     region_id?: int|null,
     *     site_id?: int|null
     * }  $filters
     */
    private function guardQuery(array $filters): Builder
    {
        return Guard::query()
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['site_id']), fn ($q) => $q->where('current_site_id', $filters['site_id']))
            ->orderBy('employment_id');
    }

    /**
     * @param  array{
     *     year: int,
     *     month: int,
     *     region_id?: int|null,
     *     site_id?: int|null
     * }  $filters
     * @param  Collection<int, Guard>  $guards
     * @return Collection<int, array<string, mixed>>
     */
    private function mapRows(array $filters, Collection $guards): Collection
    {
        if ($guards->isEmpty()) {
            return collect();
        }

        $start = Carbon::create((int) $filters['year'], (int) $filters['month'], 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $shiftRows = Shift::query()
            ->whereBetween('shift_date', [$start->toDateString(), $end->toDateString()])
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['site_id']), fn ($q) => $q->where('site_id', $filters['site_id']))
            ->whereIn('guard_id', $guards->pluck('id'))
            ->get(['guard_id', 'shift_type', 'status']);

        $grouped = $shiftRows->groupBy('guard_id');

        return $guards->map(function (Guard $guard) use ($grouped) {
            $shifts = $grouped->get($guard->id, collect());

            $worked = $shifts->filter(fn (Shift $shift) => $shift->status->countsAsWorked());

            $normal = $worked->where('shift_type', ShiftType::Normal)->count();
            $overtime = $worked->where('shift_type', ShiftType::Overtime)->count();
            $relief = $worked->where('shift_type', ShiftType::Relief)->count();
            $replacement = $worked->where('shift_type', ShiftType::Replacement)->count();
            $special = $worked->where('shift_type', ShiftType::SpecialDuty)->count();

            return [
                'guard_id' => $guard->id,
                'employment_id' => $guard->employment_id,
                'full_name' => $guard->full_name,
                'role' => $guard->supervisorProfile ? 'Supervisor' : 'Guard',
                'is_supervisor' => $guard->supervisorProfile !== null,
                'region' => $guard->region?->name,
                'site' => $guard->currentSite?->name,
                'normal_shifts' => $normal,
                'overtime_shifts' => $overtime,
                'relief_shifts' => $relief,
                'replacement_shifts' => $replacement,
                'special_duty_shifts' => $special,
                'total_shifts' => $normal + $overtime + $relief + $replacement + $special,
                'missed_shifts' => $shifts->where('status', ShiftStatus::Missed)->count(),
                'cancelled_shifts' => $shifts->where('status', ShiftStatus::Cancelled)->count(),
            ];
        })->values();
    }

    /**
     * @return array{
     *     guard_id: int,
     *     employment_id: string,
     *     full_name: string,
     *     normal_shifts: int,
     *     overtime_shifts: int,
     *     relief_shifts: int,
     *     replacement_shifts: int,
     *     special_duty_shifts: int,
     *     total_shifts: int
     * }
     */
    public function guardRowForPeriod(int $guardId, string $start, string $end, ?\App\Models\PayrollRun $run = null): array
    {
        $guard = Guard::query()->findOrFail($guardId);

        $shifts = Shift::query()
            ->where('guard_id', $guardId)
            ->whereBetween('shift_date', [$start, $end])
            ->when($run?->region_id, fn ($q) => $q->where('region_id', $run->region_id))
            ->when($run?->site_id, fn ($q) => $q->where('site_id', $run->site_id))
            ->get(['shift_type', 'status']);

        $worked = $shifts->filter(fn (Shift $shift) => $shift->status->countsAsWorked());

        $normal = $worked->where('shift_type', ShiftType::Normal)->count();
        $overtime = $worked->where('shift_type', ShiftType::Overtime)->count();
        $relief = $worked->where('shift_type', ShiftType::Relief)->count();
        $replacement = $worked->where('shift_type', ShiftType::Replacement)->count();
        $special = $worked->where('shift_type', ShiftType::SpecialDuty)->count();

        return [
            'guard_id' => $guard->id,
            'employment_id' => $guard->employment_id,
            'full_name' => $guard->full_name,
            'normal_shifts' => $normal,
            'overtime_shifts' => $overtime,
            'relief_shifts' => $relief,
            'replacement_shifts' => $replacement,
            'special_duty_shifts' => $special,
            'total_shifts' => $normal + $overtime + $relief + $replacement + $special,
        ];
    }
}
