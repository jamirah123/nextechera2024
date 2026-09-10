<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;
use App\Support\Access\Access;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'staff.view');
    }

    public function view(User $user, Staff $staff): bool
    {
        return Access::userCan($user, 'staff.view');
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'staff.manage');
    }

    public function update(User $user, Staff $staff): bool
    {
        return Access::userCan($user, 'staff.manage');
    }

    public function correctEmploymentId(User $user, Staff $staff): bool
    {
        return Access::userCan($user, 'employees.correct_employment_id');
    }

    public function deleteAny(User $user): bool
    {
        return Access::userCan($user, 'staff.manage');
    }

    public function delete(User $user, Staff $staff): bool
    {
        return Access::userCan($user, 'staff.manage');
    }
}
