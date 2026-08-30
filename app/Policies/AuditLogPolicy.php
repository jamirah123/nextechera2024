<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access\Access;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'admin.audit_view');
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
