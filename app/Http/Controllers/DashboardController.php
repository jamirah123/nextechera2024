<?php

namespace App\Http\Controllers;

use App\Services\Compliance\ComplianceSnapshotService;
use App\Services\Dashboards\DashboardStatisticsService;
use App\Services\Dashboards\OperationalDashboardService;
use App\Services\Dashboards\ShiftDeskService;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private OperationalDashboardService $opsDashboards,
        private ShiftDeskService $shiftDesk,
        private ComplianceSnapshotService $compliance,
        private DashboardStatisticsService $statistics,
    ) {
    }

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $view = match ($user->role?->value) {
            'super_admin' => 'dashboards.super-admin',
            'managing_director' => 'dashboards.managing-director',
            'operations_manager' => 'dashboards.operations',
            'hr_manager' => 'dashboards.hr',
            'shift_manager' => 'dashboards.shift',
            'finance_manager' => 'dashboards.finance',
            'region_supervisor' => 'dashboards.region-supervisor',
            default => 'dashboards.generic',
        };

        $shiftDeskSnapshot = in_array($user->role?->value, ['shift_manager', 'operations_manager', 'managing_director'], true)
            ? $this->shiftDesk->snapshot($user)
            : null;

        $kpis = match ($user->role?->value) {
            'shift_manager', 'operations_manager' => $shiftDeskSnapshot !== null
                ? $this->shiftDesk->kpis($user)
                : RoleNavigation::kpis($user),
            default => RoleNavigation::kpis($user),
        };

        return view($view, [
            'user' => $user,
            'kpis' => $kpis,
            'modules' => RoleNavigation::modules($user),
            'ops' => $this->opsDashboards->landingSnapshot(),
            'compliance' => $this->compliance->snapshot(),
            'shiftDesk' => $shiftDeskSnapshot,
            'charts' => $this->statistics->for($user),
        ]);
    }
}
