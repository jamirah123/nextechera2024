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
        return $this->canAccess($user) && $user->canAccessRegion($deployment->region_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Deployment $deployment): bool
    {
        return $this->canManage($user) && $user->canAccessRegion($deployment->region_id);
    }

    public function delete(User $user, Deployment $deployment): bool
    {
        return $this->canManage($user) && $user->canAccessRegion($deployment->region_id);
    }

    public function transfer(User $user, Deployment $deployment): bool
    {
        return $this->canManage($user) && $user->canAccessRegion($deployment->region_id);
    }

    public function end(User $user, Deployment $deployment): bool
    {
        return $this->canManage($user) && $user->canAccessRegion($deployment->region_id);
    }

    private function canAccess(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SuperAdmin,
            UserRole::OperationsManager,
            UserRole::HrManager,
            UserRole::ShiftManager,
            UserRole::FinanceManager,
            UserRole::RegionSupervisor,
        ], true);
    }

    private function canManage(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole(UserRole::OperationsManager)
            || $user->hasRole(UserRole::ShiftManager)
            || $user->hasRole(UserRole::RegionSupervisor);
    }
}
