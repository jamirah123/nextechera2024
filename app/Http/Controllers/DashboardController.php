<?php

namespace App\Http\Controllers;

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
    ) {
    }

    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $view = match ($user->role?->value) {
            'super_admin' => 'dashboards.super-admin',
            'operations_manager' => 'dashboards.operations',
            'hr_manager' => 'dashboards.hr',
            'shift_manager' => 'dashboards.shift',
            'finance_manager' => 'dashboards.finance',
            'region_supervisor' => 'dashboards.region-supervisor',
            default => 'dashboards.generic',
        };

        $shiftDeskSnapshot = in_array($user->role?->value, ['shift_manager', 'operations_manager'], true)
            ? $this->shiftDesk->snapshot($user)
            : null;

        return view($view, [
            'user' => $user,
            'kpis' => $shiftDeskSnapshot !== null
                ? $this->shiftDesk->kpis($user)
                : RoleNavigation::kpis($user),
            'modules' => RoleNavigation::modules($user),
            'ops' => $this->opsDashboards->landingSnapshot(),
            'shiftDesk' => $shiftDeskSnapshot,
        ]);
    }
}
