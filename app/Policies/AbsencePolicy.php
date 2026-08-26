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
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Absence $absence): bool
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
            || $user->hasRole(UserRole::OperationsManager)
            || $user->hasRole(UserRole::ShiftManager);
    }
}
