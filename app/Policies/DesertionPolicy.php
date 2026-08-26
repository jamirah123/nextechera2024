<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Desertion;
use App\Models\User;

class DesertionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, Desertion $desertion): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Desertion $desertion): bool
    {
        return $this->canManage($user);
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
            || $user->hasRole(UserRole::HrManager)
            || $user->hasRole(UserRole::OperationsManager);
    }
}
