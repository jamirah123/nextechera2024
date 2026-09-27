<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;
use App\Support\Access\Access;

class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'hr.positions_manage') || Access::userCan($user, 'hr.promotions_manage');
    }

    public function manage(User $user): bool
    {
        return Access::userCan($user, 'hr.positions_manage');
    }

    public function create(User $user): bool
    {
        return $this->manage($user);
    }

    public function update(User $user, Position $position): bool
    {
        return $this->manage($user);
    }
}
