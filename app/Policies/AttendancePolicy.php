<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use App\Support\Access\Access;

class AttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'hr.absences_attendance_record');
    }
}
