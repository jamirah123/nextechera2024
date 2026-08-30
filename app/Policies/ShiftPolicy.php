<?php

namespace App\Policies;

use App\Models\Shift;
use App\Models\User;
use App\Support\Access\Access;

class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, Shift $shift): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'operations.shifts_manage');
    }

    public function update(User $user, Shift $shift): bool
    {
        return Access::userCan($user, 'operations.shifts_manage');
    }

    public function delete(User $user, Shift $shift): bool
    {
        return Access::userCan($user, 'operations.shifts_manage');
    }

    public function manageStatus(User $user, Shift $shift): bool
    {
        return Access::userCan($user, 'operations.shifts_manage');
    }

    public function override(User $user): bool
    {
        return Access::userCan($user, 'operations.shifts_override');
    }
}
