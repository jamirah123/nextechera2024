<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class FinancePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function manage(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole(UserRole::FinanceManager);
    }

    private function canAccess(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole(UserRole::FinanceManager)
            || $user->hasRole(UserRole::OperationsManager);
    }
}
