<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access\Access;
use Illuminate\Database\Eloquent\Model;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function view(User $user, Model $model): bool
    {
        return Access::userCan($user, 'organization.view');
    }

    public function create(User $user): bool
    {
        return Access::userCan($user, 'organization.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return Access::userCan($user, 'organization.manage');
    }

    public function deleteAny(User $user): bool
    {
        return Access::userCan($user, 'organization.manage');
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->deleteAny($user);
    }
}
