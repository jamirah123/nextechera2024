<?php

namespace App\Policies;

use App\Models\Leave;
use App\Models\User;
use App\Support\Access\Access;

class LeavePolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, Leave $leave): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'hr.leaves_manage');
    }

    public function update(User $user, Leave $leave): bool
    {
        return Access::userCan($user, 'hr.leaves_manage');
    }

    public function approve(User $user, Leave $leave): bool
    {
        return Access::userCan($user, 'hr.leaves_approve');
    }
}
