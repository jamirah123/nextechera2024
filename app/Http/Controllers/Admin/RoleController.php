<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Access\PermissionCatalog;
use App\Support\Access\RoleAccessMatrix;
use App\Support\Access\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function __construct(private RolePermissionService $permissions)
    {
    }

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
            'catalog' => PermissionCatalog::definitions(),
            'grants' => $this->permissions->matrix(),
            'counts' => $counts,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorize('manageAccess', User::class);

        $input = $request->input('permissions', []);
        $grants = [];

        foreach (PermissionCatalog::keys() as $permission) {
            $grants[$permission] = [];

            foreach (UserRole::values() as $role) {
                if (in_array($role, [UserRole::SuperAdmin->value, UserRole::ManagingDirector->value], true)) {
                    continue;
                }

                if (! empty($input[$permission][$role])) {
                    $grants[$permission][] = $role;
                }
            }
        }

        $this->permissions->sync($grants);

        return redirect()
            ->route('roles.index')
            ->with('status', 'Roles and permissions saved.');
    }

    public function reset(): RedirectResponse
    {
        $this->authorize('manageAccess', User::class);

        $this->permissions->seedDefaults();

        return redirect()
            ->route('roles.index')
            ->with('status', 'Permissions restored to system defaults.');
    }

    public function clone(Request $request): RedirectResponse
    {
        $this->authorize('manageAccess', User::class);

        $data = $request->validate([
            'source_role' => ['required', Rule::enum(UserRole::class)],
            'target_role' => ['required', Rule::enum(UserRole::class), 'different:source_role'],
        ]);

        $target = UserRole::from($data['target_role']);

        if (in_array($target, [UserRole::SuperAdmin, UserRole::ManagingDirector], true)) {
            return back()->withErrors([
                'target_role' => 'Super Admin and Managing Director always have fixed access and cannot be used as the clone target.',
            ]);
        }

        $this->permissions->cloneRole(
            UserRole::from($data['source_role']),
            $target,
        );

        $source = UserRole::from($data['source_role']);

        return redirect()
            ->route('roles.index')
            ->with('status', "Copied permissions from {$source->label()} to {$target->label()}.");
    }
}
