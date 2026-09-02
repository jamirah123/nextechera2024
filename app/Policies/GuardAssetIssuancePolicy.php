<?php

namespace App\Policies;

use App\Models\GuardAssetIssuance;
use App\Models\User;
use App\Support\Access\Access;

class GuardAssetIssuancePolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, GuardAssetIssuance $issuance): bool
    {
        if (! Access::userCan($user, 'organization.view')) {
            return false;
        }

        if (! $user->mustStayInOwnRegion()) {
            return true;
        }

        $issuance->loadMissing('assignedGuard:id,region_id');

        return $user->canAccessRegion($issuance->assignedGuard?->region_id);
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'hr.assets_manage');
    }

    public function update(User $user, GuardAssetIssuance $issuance): bool
    {
        return Access::userCan($user, 'hr.assets_manage') && $this->view($user, $issuance);
    }

    public function delete(User $user, GuardAssetIssuance $issuance): bool
    {
        return $this->update($user, $issuance);
    }
}
