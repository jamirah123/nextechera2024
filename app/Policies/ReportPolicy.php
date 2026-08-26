<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [
            UserRole::SuperAdmin,
            UserRole::OperationsManager,
            UserRole::HrManager,
            UserRole::ShiftManager,
            UserRole::FinanceManager,
        ], true);
    }
}
