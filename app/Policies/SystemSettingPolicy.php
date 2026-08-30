<?php

namespace App\Policies;

use App\Models\SystemSetting;
use App\Models\User;
use App\Support\Access\Access;

class SystemSettingPolicy
{
    public function viewAny(User $actor): bool
    {
        return Access::userCan($actor, 'admin.settings_manage');
    }

    public function update(User $actor, ?SystemSetting $settings = null): bool
    {
        return Access::userCan($actor, 'admin.settings_manage');
    }

    public function runMaintenance(User $actor): bool
    {
        return Access::userCan($actor, 'admin.settings_manage');
    }
}
