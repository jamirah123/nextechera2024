<?php

namespace App\Policies;

use App\Models\Deployment;
use App\Models\User;
use App\Support\Access\Access;

class DeploymentPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, Deployment $deployment): bool
    {
        return Access::userCan($user, 'organization.view')
            && $user->canAccessRegion($deployment->region_id);
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'operations.deployments_manage');
    }

    public function board(User $user): bool
    {
        return Access::userCan($user, 'operations.deploy_board');
    }

    public function update(User $user, Deployment $deployment): bool
    {
        return Access::userCan($user, 'operations.deployments_manage')
            && $user->canAccessRegion($deployment->region_id);
    }

    public function delete(User $user, Deployment $deployment): bool
    {
        return $this->update($user, $deployment);
    }

    public function transfer(User $user, Deployment $deployment): bool
    {
        return $this->update($user, $deployment);
    }

    public function end(User $user, Deployment $deployment): bool
    {
        return $this->update($user, $deployment);
    }
}
