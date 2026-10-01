<?php

namespace App\Services\Dashboards;

use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\User;
use App\Support\Performance\DashboardCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardStatisticsService
{
    /** @return list<array<string, mixed>> */
    public function for(User $user): array
    {
        $region = $user->mustStayInOwnRegion() ? (string) ($user->regionId() ?? 'none') : 'all';
        $key = 'psg.dashboard.charts.'.DashboardCache::version().'.'.$user->role->value.'.'.$region;
        $ttl = max(15, (int) config('psg.performance.dashboard_cache_seconds', 45));

        return Cache::remember($key, $ttl, fn () => $this->build($user));
    }

    /** @return list<array<string, mixed>> */
    private function build(User $user): array
    {
        return match ($user->role) {
            UserRole::FinanceManager => [
                $this->invoiceStatusChart(),
                $this->collectionsTrendChart(),
            ],
            UserRole::HrManager => [
                $this->workforceChart($user),
                $this->leavePipelineChart(),
            ],
            UserRole::ManagingDirector => [
                $this->shiftOutcomesChart($user),
                $this->workforceChart($user),
                $this->invoiceStatusChart(),
                $this->collectionsTrendChart(),
            ],
            UserRole::SuperAdmin,
            UserRole::OperationsManager,
            UserRole::ShiftManager,
            UserRole::RegionSupervisor => [
                $this->shiftOutcomesChart($user),
                $this->workforceChart($user),
            ],
            default => [
                $this->shiftOutcomesChart($user),
                $this->workforceChart($user),
            ],
        };
    }

    /** @return array<string, mixed> */
    private function shiftOutcomesChart(User $user): array
    {
        $start = now()->subDays(6)->startOfDay();
        $query = Shift::query()->whereBetween('shift_date', [$start->toDateString(), now()->toDateString()]);
        $this->scopeShiftsToUser($query, $user);

        $rows = $query
            ->selectRaw('shift_date, status, COUNT(*) as aggregate')
            ->groupBy('shift_date', 'status')
            ->get()
            ->groupBy(function (Shift $row): string {
                $day = $row->shift_date;

                return $day instanceof \Carbon\CarbonInterface ? $day->toDateString() : substr((string) $day, 0, 10);
            });

        $labels = [];
        $completed = [];
        $missed = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('D');
            $bucket = $rows->get($date->toDateString(), collect());
            $completed[] = (int) $bucket->filter(fn (Shift $row) => $this->statusValue($row->status) === ShiftStatus::Completed->value)->sum('aggregate');
            $missed[] = (int) $bucket->filter(fn (Shift $row) => $this->statusValue($row->status) === ShiftStatus::Missed->value)->sum('aggregate');
        }

        return [
            'id' => 'shift-outcomes',
            'title' => 'Shift outcomes',
            'subtitle' => 'Completed vs missed over the last 7 days',
            'type' => 'bar',
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Completed',
                    'data' => $completed,
                    'backgroundColor' => 'rgba(5, 150, 105, 0.85)',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Missed',
                    'data' => $missed,
                    'backgroundColor' => 'rgba(217, 119, 6, 0.85)',
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function workforceChart(User $user): array
    {
        $statuses = [
            OperationalStatus::OnDuty,
            OperationalStatus::OffDuty,
            OperationalStatus::OnLeave,
            OperationalStatus::Absent,
            OperationalStatus::AwaitingDeployment,
        ];

        $labels = [];
        $values = [];
        $colors = [
            'rgba(5, 150, 105, 0.9)',
            'rgba(100, 116, 139, 0.9)',
            'rgba(14, 165, 233, 0.9)',
            'rgba(217, 119, 6, 0.9)',
            'rgba(99, 102, 241, 0.9)',
        ];

        $query = Guard::query()->where('employment_status', EmploymentStatus::Active);
        $this->scopeGuardsToUser($query, $user);

        $counts = [];
        foreach ($query->selectRaw('operational_status, COUNT(*) as aggregate')->groupBy('operational_status')->get() as $row) {
            $counts[$this->statusValue($row->operational_status)] = (int) $row->aggregate;
        }

        $other = (int) array_sum($counts);

        foreach ($statuses as $index => $status) {
            $count = (int) ($counts[$status->value] ?? 0);
            if ($count > 0) {
                $labels[] = $status->label();
                $values[] = $count;
                $other -= $count;
            }
        }

        if ($other > 0) {
            $labels[] = 'Other';
            $values[] = $other;
            $colors[] = 'rgba(148, 163, 184, 0.9)';
        }

        return [
            'id' => 'workforce-status',
            'title' => 'Guard workforce',
            'subtitle' => 'Active guards by operational status',
            'type' => 'doughnut',
            'labels' => $labels ?: ['No active guards'],
            'datasets' => [
                [
                    'label' => 'Guards',
                    'data' => $values ?: [0],
                    'backgroundColor' => array_slice($colors, 0, max(count($values), 1)),
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function leavePipelineChart(): array
    {
        $statuses = [
            LeaveStatus::Pending,
            LeaveStatus::Approved,
            LeaveStatus::Rejected,
            LeaveStatus::Completed,
        ];

        $labels = [];
        $values = [];
        $colors = [
            'rgba(217, 119, 6, 0.9)',
            'rgba(5, 150, 105, 0.9)',
            'rgba(244, 63, 94, 0.9)',
            'rgba(14, 165, 233, 0.9)',
        ];

        $counts = [];
        foreach (Leave::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $row) {
            $counts[$this->statusValue($row->status)] = (int) $row->aggregate;
        }

        foreach ($statuses as $index => $status) {
            $labels[] = $status->label();
            $values[] = (int) ($counts[$status->value] ?? 0);
        }

        return [
            'id' => 'leave-pipeline',
            'title' => 'Leave pipeline',
            'subtitle' => 'Requests by approval status',
            'type' => 'doughnut',
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Leave requests',
                    'data' => $values,
                    'backgroundColor' => $colors,
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function invoiceStatusChart(): array
    {
        $statuses = [
            InvoiceStatus::Issued,
            InvoiceStatus::PartiallyPaid,
            InvoiceStatus::Paid,
            InvoiceStatus::Overdue,
        ];

        $labels = [];
        $values = [];
        $colors = [
            'rgba(14, 165, 233, 0.9)',
            'rgba(217, 119, 6, 0.9)',
            'rgba(5, 150, 105, 0.9)',
            'rgba(244, 63, 94, 0.9)',
        ];

        $counts = [];
        foreach (Invoice::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $row) {
            $counts[$this->statusValue($row->status)] = (int) $row->aggregate;
        }

        foreach ($statuses as $index => $status) {
            $count = (int) ($counts[$status->value] ?? 0);
            if ($count > 0) {
                $labels[] = $status->label();
                $values[] = $count;
            }
        }

        return [
            'id' => 'invoice-status',
            'title' => 'Invoice status',
            'subtitle' => 'Open and settled client invoices',
            'type' => 'doughnut',
            'labels' => $labels ?: ['No invoices yet'],
            'datasets' => [
                [
                    'label' => 'Invoices',
                    'data' => $values ?: [0],
                    'backgroundColor' => array_slice($colors, 0, max(count($values), 1)),
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function collectionsTrendChart(): array
    {
        $start = now()->subMonths(5)->startOfMonth()->toDateString();
        $bucketSql = DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y-%m', payment_date)"
            : "DATE_FORMAT(payment_date, '%Y-%m')";

        $totals = Payment::query()
            ->collections()
            ->where('payment_date', '>=', $start)
            ->selectRaw($bucketSql.' as bucket, SUM(amount) as total')
            ->groupBy(DB::raw($bucketSql))
            ->pluck('total', 'bucket');

        $labels = [];
        $values = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $values[] = (float) ($totals[$month->format('Y-m')] ?? 0);
        }

        return [
            'id' => 'collections-trend',
            'title' => 'Collections trend',
            'subtitle' => 'Payments received over the last 6 months',
            'type' => 'line',
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Collected',
                    'data' => $values,
                    'borderColor' => 'rgba(30, 64, 175, 0.95)',
                    'backgroundColor' => 'rgba(30, 64, 175, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 3,
                ],
            ],
        ];
    }

    private function scopeGuardsToUser($query, User $user): void
    {
        if ($user->mustStayInOwnRegion() && $user->regionId()) {
            $query->where('region_id', $user->regionId());
        }
    }

    private function scopeShiftsToUser($query, User $user): void
    {
        if ($user->mustStayInOwnRegion() && $user->regionId()) {
            $query->where('region_id', $user->regionId());
        }
    }

    private function statusValue(mixed $status): string
    {
        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
    }
}
