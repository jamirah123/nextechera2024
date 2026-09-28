<?php

namespace App\Policies;

use App\Models\LeaveTypeConfig;
use App\Models\User;
use App\Support\Access\Access;

class LeaveTypeConfigPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'hr.leave_types_manage')
            || Access::userCan($user, 'hr.leaves_manage');
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'hr.leave_types_manage');
    }

    public function update(User $user, LeaveTypeConfig $leaveTypeConfig): bool
    {
        return Access::userCan($user, 'hr.leave_types_manage');
    }
}
