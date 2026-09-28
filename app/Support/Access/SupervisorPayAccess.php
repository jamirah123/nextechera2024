<?php

namespace App\Support\Access;

use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\PayrollPayslip;
use App\Models\Staff;
use App\Models\User;

class SupervisorPayAccess
{
    public static function hidesSupervisorPay(User $user): bool
    {
        return in_array($user->role, [
            UserRole::OperationsManager,
            UserRole::ShiftManager,
        ], true);
    }

    public static function canViewStaffPay(User $user, Staff $staff): bool
    {
        if (! self::hidesSupervisorPay($user)) {
            return true;
        }

        return ! self::staffIsSupervisor($staff);
    }

    public static function canViewGuardPay(User $user, Guard $guard): bool
    {
        if (! self::hidesSupervisorPay($user)) {
            return true;
        }

        return ! self::guardIsSupervisor($guard);
    }

    public static function canViewPayslip(User $user, PayrollPayslip $payslip): bool
    {
        if (! self::hidesSupervisorPay($user)) {
            return true;
        }

        return ! $payslip->belongsToSupervisor();
    }

    public static function staffIsSupervisor(Staff $staff): bool
    {
        if ($staff->relationLoaded('supervisorProfile')) {
            return $staff->supervisorProfile !== null;
        }

        return $staff->supervisorProfile()->exists();
    }

    public static function guardIsSupervisor(Guard $guard): bool
    {
        if ($guard->relationLoaded('supervisorProfile')) {
            return $guard->supervisorProfile !== null;
        }

        return $guard->supervisorProfile()->exists();
    }
}
