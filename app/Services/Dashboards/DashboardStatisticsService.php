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

class DashboardStatisticsService
{
    /** @return list<array<string, mixed>> */
    public function for(User $user): array
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
        $labels = [];
        $completed = [];
        $missed = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $labels[] = $date->format('D');
            $query = Shift::query()->forDate($date->toDateString());
            $this->scopeShiftsToUser($query, $user);

            $completed[] = (clone $query)->where('status', ShiftStatus::Completed)->count();
            $missed[] = (clone $query)->where('status', ShiftStatus::Missed)->count();
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

        $other = (clone $query)->count();

        foreach ($statuses as $index => $status) {
            $count = (clone $query)->where('operational_status', $status)->count();
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

        foreach ($statuses as $index => $status) {
            $labels[] = $status->label();
            $values[] = Leave::query()->where('status', $status)->count();
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

        foreach ($statuses as $index => $status) {
            $count = Invoice::query()->where('status', $status)->count();
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
        $labels = [];
        $values = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $values[] = (float) Payment::query()
                ->collections()
                ->whereBetween('payment_date', [
                    $month->copy()->startOfMonth()->toDateString(),
                    $month->copy()->endOfMonth()->toDateString(),
                ])
                ->sum('amount');
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
}
