<?php

namespace App\Support\Audit;

use App\Models\Absence;
use App\Models\AuditLog;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\DatabaseBackup;
use App\Models\Deployment;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;

class AuditLogUrlResolver
{
    public function resolve(AuditLog $log, ?User $user = null): ?string
    {
        $user ??= auth()->user();

        if (! $log->subject_type || ! $log->subject_id) {
            return $this->auditFallback($user);
        }

        $url = match ($log->subject_type) {
            Shift::class => $this->safeRoute('shifts.show', $log->subject_id),
            ShiftReplacement::class => $this->safeRoute('replacements.show', $log->subject_id),
            Deployment::class => $this->safeRoute('deployments.show', $log->subject_id),
            Invoice::class => $user && Gate::forUser($user)->allows('viewFinance')
                ? $this->safeRoute('invoices.show', $log->subject_id)
                : null,
            Payment::class => $user && Gate::forUser($user)->allows('viewFinance')
                ? $this->safeRoute('payments.show', $log->subject_id)
                : null,
            PayrollRun::class => $user && Gate::forUser($user)->allows('viewFinance')
                ? $this->safeRoute('payroll.show', $log->subject_id)
                : null,
            BillingProfile::class => $user && Gate::forUser($user)->allows('viewFinance')
                ? $this->safeRoute('billing.show', $log->subject_id)
                : null,
            User::class => $user && \App\Support\Access\Access::userCan($user, 'admin.users_manage')
                ? $this->safeRoute('users.show', $log->subject_id)
                : null,
            Leave::class => $this->safeRoute('leaves.show', $log->subject_id),
            Absence::class => $this->safeRoute('absences.show', $log->subject_id),
            GuardAssetIssuance::class => $this->safeRoute('assets.show', $log->subject_id),
            Desertion::class => $this->safeRoute('desertions.show', $log->subject_id),
            Guard::class => $this->safeRoute('guards.show', $log->subject_id),
            Site::class => $this->safeRoute('sites.show', $log->subject_id),
            Client::class => $this->safeRoute('clients.show', $log->subject_id),
            WorkOrder::class => $this->safeRoute('work-orders.show', $log->subject_id),
            DatabaseBackup::class => $user && \App\Support\Access\Access::userCan($user, 'admin.backups_manage')
                ? $this->safeRoute('backups.show', $log->subject_id)
                : null,
            default => null,
        };

        return $url ?? $this->auditFallback($user);
    }

    private function auditFallback(?User $user): ?string
    {
        if ($user && Gate::forUser($user)->allows('viewAuditLogs')) {
            return null;
        }

        return null;
    }

    private function safeRoute(string $name, mixed $parameters): ?string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }
}
