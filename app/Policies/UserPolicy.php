<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->isSuperAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isSuperAdmin();
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->isSuperAdmin() && $actor->id !== $user->id;
    }

    public function restore(User $actor, User $user): bool
    {
        return $actor->isSuperAdmin();
    }

    public function manageAccess(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
