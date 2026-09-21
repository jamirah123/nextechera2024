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

    public function managePurchases(User $user): bool
    {
        return Access::userCan($user, 'finance.manage')
            || Access::userCan($user, 'finance.purchases_manage');
    }

    public function viewPurchases(User $user): bool
    {
        return Access::userCan($user, 'finance.view')
            || Access::userCan($user, 'finance.purchases_manage');
    }

    public function approvePayroll(User $user): bool
    {
        return Access::userCan($user, 'finance.payroll.approve');
    }
}
