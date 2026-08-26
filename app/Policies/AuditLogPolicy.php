<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin()
            || $user->hasRole(UserRole::OperationsManager);
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
