<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->canManage($user);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole(UserRole::OperationsManager);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->deleteAny($user);
    }

    private function canAccess(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SuperAdmin,
            UserRole::OperationsManager,
            UserRole::HrManager,
            UserRole::ShiftManager,
            UserRole::FinanceManager,
        ], true);
    }

    private function canManage(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasRole(UserRole::OperationsManager);
    }
}
