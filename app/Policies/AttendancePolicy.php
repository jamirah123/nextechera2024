<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole(UserRole::HrManager)
            || $user->hasRole(UserRole::OperationsManager)
            || $user->hasRole(UserRole::ShiftManager);
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
}
