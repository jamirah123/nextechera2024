<?php

namespace App\Policies;

use App\Models\Desertion;
use App\Models\User;
use App\Support\Access\Access;

class DesertionPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, Desertion $desertion): bool
    {
        if (! Access::userCan($user, 'organization.view')) {
            return false;
        }

        if (! $user->mustStayInOwnRegion()) {
            return true;
        }

        $desertion->loadMissing('assignedGuard:id,region_id');

        return $user->canAccessRegion($desertion->assignedGuard?->region_id);
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'hr.desertions_manage');
    }

    public function update(User $user, Desertion $desertion): bool
    {
        return Access::userCan($user, 'hr.desertions_manage') && $this->view($user, $desertion);
    }
}
