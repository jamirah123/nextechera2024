<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;
use App\Support\Access\Access;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, Incident $incident): bool
    {
        if (! Access::userCan($user, 'organization.view')) {
            return false;
        }

        if (! $user->mustStayInOwnRegion()) {
            return true;
        }

        $incident->loadMissing('site:id,region_id');

        return $user->canAccessRegion($incident->site?->region_id);
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'operations.incidents_manage');
    }

    public function update(User $user, Incident $incident): bool
    {
        return Access::userCan($user, 'operations.incidents_manage') && $this->view($user, $incident);
    }
}
