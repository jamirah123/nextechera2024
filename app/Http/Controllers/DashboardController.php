<?php

namespace App\Http\Controllers;

use App\Support\Navigation\RoleNavigation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $view = match ($user->role?->value) {
            'super_admin' => 'dashboards.super-admin',
            'operations_manager' => 'dashboards.operations',
            'hr_manager' => 'dashboards.hr',
            'shift_manager' => 'dashboards.shift',
            'finance_manager' => 'dashboards.finance',
            default => 'dashboards.generic',
        };

        return view($view, [
            'user' => $user,
            'kpis' => RoleNavigation::kpis($user),
            'modules' => RoleNavigation::modules($user),
        ]);
    }
}
