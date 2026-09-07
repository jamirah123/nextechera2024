<?php

namespace App\Support\Access;

use App\Enums\UserRole;
use App\Models\User;

class Access
{
    public static function userCan(User $user, string $permission): bool
    {
        return app(RolePermissionService::class)->userCan($user, $permission);
    }

    public static function roleCan(UserRole|string $role, string $permission): bool
    {
        return app(RolePermissionService::class)->roleCan($role, $permission);
    }

    /**
     * Operations managers may create/edit but must not permanently delete records.
     */
    public static function userCanDelete(User $user): bool
    {
        return $user->role !== UserRole::OperationsManager;
    }
}
