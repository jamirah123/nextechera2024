<?php

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;

class SystemSettingPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }

    public function update(User $actor, ?SystemSetting $settings = null): bool
    {
        return $actor->isSuperAdmin();
    }

    public function runMaintenance(User $actor): bool
    {
        return $actor->isSuperAdmin();
    }
}
