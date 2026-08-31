<?php

namespace App\Support\Finance;

use App\Enums\PayrollRunStatus;
use App\Models\PayrollRun;
use App\Models\User;
use App\Support\Access\Access;

class PayrollAccess
{
    public static function canSubmit(User $user): bool
    {
        return Access::userCan($user, 'finance.manage');
    }

    public static function canApprove(User $user): bool
    {
        return Access::userCan($user, 'finance.payroll.approve');
    }

    public static function canReject(User $user, PayrollRun $run): bool
    {
        return $run->status->canReject() && self::canApprove($user);
    }

    public static function canCancel(User $user, PayrollRun $run): bool
    {
        if (! $run->status->canCancel()) {
            return false;
        }

        if (in_array($run->status, [PayrollRunStatus::Submitted, PayrollRunStatus::Approved, PayrollRunStatus::Paid], true)) {
            return self::canApprove($user);
        }

        return self::canSubmit($user);
    }

    public static function cancelLabel(PayrollRunStatus $status, User $user): string
    {
        if (in_array($status, [PayrollRunStatus::Approved, PayrollRunStatus::Paid], true)) {
            return self::canApprove($user) ? 'Delete run' : 'Reject run';
        }

        if ($status === PayrollRunStatus::Submitted) {
            return self::canApprove($user) ? 'Delete run' : 'Reject run';
        }

        return $status === PayrollRunStatus::Draft ? 'Cancel' : 'Delete run';
    }
}
