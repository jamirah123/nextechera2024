<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\User;

class AbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, Absence $absence): bool
    {
        if (! $this->canAccess($user)) {
            return false;
        }

        if (! $user->mustStayInOwnRegion()) {
            return true;
        }

        $absence->loadMissing('assignedGuard:id,region_id');

        return $user->canAccessRegion($absence->assignedGuard?->region_id);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Absence $absence): bool
    {
        return $this->canManage($user) && $this->view($user, $absence);
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
            || $user->hasRole(UserRole::HrManager)
            || $user->hasRole(UserRole::OperationsManager)
            || $user->hasRole(UserRole::ShiftManager)
            || $user->hasRole(UserRole::RegionSupervisor);
    }
}
