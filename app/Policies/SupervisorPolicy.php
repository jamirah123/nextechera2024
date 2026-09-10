<?php

namespace App\Policies;

use App\Models\Supervisor;
use App\Models\User;
use App\Support\Access\Access;

class SupervisorPolicy extends OrganizationPolicy
{
    public function correctEmploymentId(User $user, Supervisor $supervisor): bool
    {
        return Access::userCan($user, 'employees.correct_employment_id');
    }
}
