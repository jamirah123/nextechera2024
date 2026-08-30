<?php

namespace App\Policies;

use App\Models\ShiftReplacement;
use App\Models\User;
use App\Support\Access\Access;

class ShiftReplacementPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, ShiftReplacement $replacement): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'operations.replacements_record');
    }
}
