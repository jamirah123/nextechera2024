<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, Shift $shift): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Shift $shift): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Shift $shift): bool
    {
        return $this->canManage($user);
    }

    public function manageStatus(User $user, Shift $shift): bool
    {
        return $this->canManage($user);
    }

    public function override(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole(UserRole::OperationsManager);
    }

    private function canAccess(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SuperAdmin,
            UserRole::OperationsManager,
            UserRole::HrManager,
            UserRole::ShiftManager,
            UserRole::FinanceManager,
        ], true);
    }

    private function canManage(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole(UserRole::OperationsManager)
            || $user->hasRole(UserRole::ShiftManager);
    }
}
