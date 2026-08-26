<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\User;

class DeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Deployment $deployment): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Deployment $deployment): bool
    {
        return $this->canManage($user);
    }

    public function transfer(User $user, Deployment $deployment): bool
    {
        return $this->canManage($user);
    }

    public function end(User $user, Deployment $deployment): bool
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
            || $user->hasRole(UserRole::OperationsManager)
            || $user->hasRole(UserRole::ShiftManager);
    }
}
