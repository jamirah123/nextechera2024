<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access\Access;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'reporting.view_export');
    }
}
