<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access\Access;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return Access::userCan($actor, 'admin.users_manage');
    }

    public function view(User $actor, User $user): bool
    {
        return Access::userCan($actor, 'admin.users_manage');
    }

    public function create(User $actor): bool
    {
        return Access::userCan($actor, 'admin.users_manage');
    }

    public function update(User $actor, User $user): bool
    {
        return Access::userCan($actor, 'admin.users_manage');
    }

    public function delete(User $actor, User $user): bool
    {
        return Access::userCan($actor, 'admin.users_manage') && $actor->id !== $user->id;
    }

    public function restore(User $actor, User $user): bool
    {
        return Access::userCan($actor, 'admin.users_manage');
    }

    public function manageAccess(User $actor): bool
    {
        return Access::userCan($actor, 'admin.roles_manage');
    }

    public function viewAttachments(User $actor, User $user): bool
    {
        return $actor->id === $user->id || Access::userCan($actor, 'admin.users_manage');
    }

    public function manageAttachments(User $actor, User $user): bool
    {
        return $actor->id === $user->id || Access::userCan($actor, 'admin.users_manage');
    }
}
