<?php

namespace App\Policies;

use App\Models\Absence;
use App\Models\User;
use App\Support\Access\Access;

class AbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, Absence $absence): bool
    {
        if (! Access::userCan($user, 'organization.view')) {
            return false;
        }

        if (! $user->mustStayInOwnRegion()) {
            return true;
        }

        $absence->loadMissing('assignedGuard:id,region_id');

        return $user->canAccessRegion($absence->assignedGuard?->region_id);
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'hr.absences_attendance_record');
    }

    public function update(User $user, Absence $absence): bool
    {
        return Access::userCan($user, 'hr.absences_attendance_record') && $this->view($user, $absence);
    }
}
