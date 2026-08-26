<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access\RoleAccessMatrix;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('manageAccess', User::class);

        $roles = UserRole::cases();
        $counts = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return view('admin.roles.index', [
            'roles' => $roles,
            'matrix' => RoleAccessMatrix::rows(),
            'counts' => $counts,
        ]);
    }
}
