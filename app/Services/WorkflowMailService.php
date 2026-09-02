<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\WorkflowActionMail;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Notifications\WorkflowActionCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class WorkflowMailService
{
    public function notifyFromAudit(AuditLog $log): void
    {
        if (! config('psg.notifications.workflow_email_enabled', true)) {
            return;
        }

        $context = is_array($log->context) ? $log->context : [];
        $definition = WorkflowActionCatalog::findForAudit($log->action, $context);

        if ($definition === null) {
            return;
        }

        $recipients = $this->resolveRecipients($log, $definition);

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->queue(new WorkflowActionMail(
                headline: $definition['headline'],
                summary: $log->summary,
                actorName: $log->actor_name,
                actionUrl: $this->actionUrlFor($log),
                actionLabel: $definition['action_label'],
                details: $this->detailsFor($log),
            ));
        }
    }

    /**
     * @param  array{permissions?: list<string>, roles?: list<UserRole>, subject: string, headline: string, action_label: string, include_stakeholders?: bool}  $definition
     * @return Collection<int, User>
     */
    private function resolveRecipients(AuditLog $log, array $definition): Collection
    {
        if (! empty($definition['roles'])) {
            $recipients = $this->usersWithRoles($definition['roles']);
        } else {
            $recipients = $this->usersWithAnyPermission($definition['permissions'] ?? []);
        }

        if ($definition['include_stakeholders'] ?? false) {
            $recipients = $recipients->merge($this->stakeholdersFor($log));
        }

        return $recipients
            ->filter(fn (User $user) => filled($user->email))
            ->unique('id')
            ->reject(fn (User $user) => $log->actor_id !== null && $user->id === $log->actor_id)
            ->values();
    }

    /** @param  list<UserRole>  $roles */
    private function usersWithRoles(array $roles): Collection
    {
        if ($roles === []) {
            return collect();
        }

        $roleValues = array_map(
            fn (UserRole $role) => $role->value,
            $roles,
        );

        return User::query()
            ->active()
            ->whereNotNull('email')
            ->whereIn('role', $roleValues)
            ->get();
    }

    /** @return Collection<int, User> */
    private function usersWithAnyPermission(array $permissions): Collection
    {
        if ($permissions === []) {
            return collect();
        }

        return User::query()
            ->active()
            ->whereNotNull('email')
            ->get()
            ->filter(fn (User $user) => collect($permissions)->contains(
                fn (string $permission) => Access::userCan($user, $permission),
            ));
    }

    /** @return Collection<int, User> */
    private function stakeholdersFor(AuditLog $log): Collection
    {
        $subject = $this->resolveSubject($log);

        if ($subject instanceof PayrollRun && $subject->submitted_by) {
            $submitter = User::query()->active()->find($subject->submitted_by);

            return $submitter ? collect([$submitter]) : collect();
        }

        if ($subject instanceof Leave && $subject->requested_by) {
            $requester = User::query()->active()->find($subject->requested_by);

            return $requester ? collect([$requester]) : collect();
        }

        return collect();
    }

    private function resolveSubject(AuditLog $log): ?Model
    {
        if (! $log->subject_type || ! $log->subject_id) {
            return null;
        }

        if (! class_exists($log->subject_type)) {
            return null;
        }

        return $log->subject_type::query()->find($log->subject_id);
    }

    private function actionUrlFor(AuditLog $log): ?string
    {
        if (! $log->subject_type || ! $log->subject_id) {
            return url('/');
        }

        try {
            return match ($log->subject_type) {
                PayrollRun::class => route('payroll.show', $log->subject_id),
                Leave::class => route('leaves.show', $log->subject_id),
                Invoice::class => route('invoices.show', $log->subject_id),
                Shift::class => route('shifts.show', $log->subject_id),
                Site::class => route('sites.show', $log->subject_id),
                Guard::class => route('guards.show', $log->subject_id),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    private function detailsFor(AuditLog $log): array
    {
        $details = [];
        $context = is_array($log->context) ? $log->context : [];

        if (filled($context['reason'] ?? null)) {
            $details[] = 'Reason: '.$context['reason'];
        }

        $subject = $this->resolveSubject($log);

        if ($subject instanceof Leave) {
            $subject->loadMissing('assignedGuard:id,employment_id,full_name');

            if ($subject->assignedGuard) {
                $details[] = 'Guard: '.$subject->assignedGuard->full_name.' ('.$subject->assignedGuard->employment_id.')';
            }

            $details[] = 'Period: '.$subject->start_date->format('d M Y').' – '.$subject->end_date->format('d M Y');
        }

        if ($subject instanceof PayrollRun) {
            $subject->loadMissing('approver:id,name', 'submitter:id,name');

            $details[] = 'Reference: '.$subject->reference;
            $details[] = 'Period: '.$subject->periodLabel();
            $details[] = 'Payslips: '.$subject->payslipCount();
            $details[] = 'Gross: '.\App\Support\Money::format($subject->gross_total, $subject->currency);
            $details[] = 'Deductions: '.\App\Support\Money::format($subject->deductions_total, $subject->currency);
            $details[] = 'Net pay: '.\App\Support\Money::format($subject->net_total, $subject->currency);

            if ($subject->approver) {
                $details[] = 'Approved by: '.$subject->approver->name;
            }

            if ($subject->submitter) {
                $details[] = 'Submitted by: '.$subject->submitter->name;
            }

            if ($log->action === 'payroll.approved') {
                $details[] = 'Individual payslip emails are not sent. Open the payroll run to review or print payslips.';
            }
        }

        if ($subject instanceof Invoice) {
            $details[] = 'Invoice: '.$subject->reference;
            if ($subject->due_date) {
                $details[] = 'Due: '.$subject->due_date->format('d M Y');
            }
            if ((float) $subject->balance > 0) {
                $details[] = 'Balance: '.\App\Support\Money::format($subject->balance, $subject->currency);
            }
        }

        if ($subject instanceof Site) {
            $details[] = 'Site: '.$subject->name;
            if (isset($context['deployed'], $context['required'])) {
                $details[] = 'Deployed: '.$context['deployed'].' / '.$context['required'];
            }
        }

        if ($subject instanceof Shift) {
            $subject->loadMissing('assignedGuard:id,employment_id,full_name', 'site:id,name');
            if ($subject->assignedGuard) {
                $details[] = 'Guard: '.$subject->assignedGuard->full_name.' ('.$subject->assignedGuard->employment_id.')';
            }
            if ($subject->site) {
                $details[] = 'Site: '.$subject->site->name;
            }
            if ($subject->shift_date) {
                $details[] = 'Date: '.$subject->shift_date->format('d M Y');
            }
        }

        if ($subject instanceof Guard) {
            $details[] = 'Guard: '.$subject->full_name.' ('.$subject->employment_id.')';
            if (filled($context['expires_at'] ?? null)) {
                $details[] = 'Expiry: '.\Carbon\Carbon::parse($context['expires_at'])->format('d M Y');
            }
        }

        return $details;
    }
}
