<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access\Access;

class FinancePolicy
{
    public function viewAny(User $user): bool
    {
        return Access::userCan($user, 'finance.view');
    }

    public function view(User $user): bool
    {
        return Access::userCan($user, 'finance.view');
    }

    public function manage(User $user): bool
    {
        return Access::userCan($user, 'finance.manage');
    }
}
