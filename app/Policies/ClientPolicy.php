<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access\Access;
use Illuminate\Database\Eloquent\Model;

class ClientPolicy extends OrganizationPolicy
{
    public function create(User $user): bool
    {
        return Access::userCan($user, 'organization.manage')
            || Access::userCan($user, 'clients.manage');
    }

    public function update(User $user, Model $model): bool
    {
        return $this->create($user);
    }
}
