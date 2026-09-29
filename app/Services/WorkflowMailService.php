<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\WorkflowActionMail;
use App\Models\AuditLog;
use App\Models\DatabaseBackup;
use App\Models\Deployment;
use App\Models\EmailDelivery;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\User;
use App\Support\Money;
use App\Support\Notifications\NotificationPreferences;
use App\Support\Notifications\WorkflowActionCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Mail;

class WorkflowMailService
{
    public function notifyFromAudit(AuditLog $log): void
    {
        $context = is_array($log->context) ? $log->context : [];

        if ($log->action === 'user.updated' && ! $this->accessChanged($context)) {
            return;
        }

        $definition = WorkflowActionCatalog::findForAudit($log->action, $context);

        if ($definition === null) {
            return;
        }

        $priority = WorkflowActionCatalog::priority($log->action, $context);
        $category = $log->category instanceof \App\Enums\AuditCategory ? $log->category->value : (string) $log->category;
        $severity = $log->severity instanceof \App\Enums\AuditSeverity ? $log->severity->value : (string) $log->severity;
        $forced = $priority === 'critical' || NotificationPreferences::isForced($log->action, $category, $severity);

        if (! config('psg.notifications.workflow_email_enabled', true) && ! $forced) {
            return;
        }

        if (str_starts_with($log->action, 'backup.') && ! config('psg.backup.notify', true) && ! $forced) {
            return;
        }

        $channel = $this->rule($log->action)['channel'] ?? WorkflowActionCatalog::channel($log->action);

        if ($forced && $channel === 'in_app') {
            $channel = 'both';
        }

        if (! in_array($channel, ['email', 'both'], true)) {
            return;
        }

        $recipients = app(NotificationRecipientService::class)
            ->resolve($log)
            ->filter(fn (User $user) => filled($user->email))
            ->filter(fn (User $user) => NotificationPreferences::wantsEmail($user, $log->action, $category, $severity))
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        $actionUrl = $this->actionUrlFor($log);

        foreach ($recipients as $recipient) {
            $delivery = $this->reserveDelivery($log, $recipient, $definition['subject'], $priority);

            if ($delivery === null) {
                continue;
            }

            Mail::to($recipient->email)->queue(new WorkflowActionMail(
                headline: $definition['headline'],
                summary: $this->summaryFor($log, $recipient, $definition),
                actorName: $log->actor_name,
                actionUrl: $actionUrl,
                actionLabel: $definition['action_label'],
                details: $this->detailsFor($log, $recipient),
                priority: $priority,
                deliveryId: $delivery->id,
            ));
        }
    }

    /** @param  array<string, mixed>  $context */
    private function accessChanged(array $context): bool
    {
        $before = is_array($context['before'] ?? null) ? $context['before'] : [];
        $after = is_array($context['after'] ?? null) ? $context['after'] : [];

        return ($before['role'] ?? null) !== ($after['role'] ?? null)
            || ($before['is_active'] ?? null) !== ($after['is_active'] ?? null);
    }

    /** @return array{channel?: string, audience?: string} */
    private function rule(string $action): array
    {
        $rules = config('psg.notifications.email_rules', []);
        $rule = is_array($rules) ? ($rules[$action] ?? []) : [];

        return is_array($rule) ? $rule : [];
    }

    private function reserveDelivery(AuditLog $log, User $recipient, string $subject, string $priority): ?EmailDelivery
    {
        try {
            return EmailDelivery::query()->create([
                'audit_log_id' => $log->id,
                'user_id' => $recipient->id,
                'recipient_email' => $recipient->email,
                'subject' => $subject,
                'action' => $log->action,
                'priority' => $priority,
                'status' => 'queued',
                'triggered_by' => $log->actor_id,
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }
    }

    /** @param  array{headline: string}  $definition */
    private function summaryFor(AuditLog $log, User $recipient, array $definition): string
    {
        if (! WorkflowActionCatalog::sensitive($log->action) || $this->mayIncludeAmounts($recipient)) {
            return $log->summary;
        }

        return $definition['headline'].'. Open the record to review the details.';
    }

    private function mayIncludeAmounts(User $recipient): bool
    {
        if (! config('psg.notifications.include_sensitive_amounts', false)) {
            return false;
        }

        return in_array($recipient->role, [
            UserRole::SuperAdmin,
            UserRole::ManagingDirector,
            UserRole::FinanceManager,
        ], true);
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
                DatabaseBackup::class => route('backups.show', $log->subject_id),
                Deployment::class => route('deployments.show', $log->subject_id),
                Payment::class => route('payments.show', $log->subject_id),
                Staff::class => route('staff.show', $log->subject_id),
                User::class => route('users.show', $log->subject_id),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return list<string> */
    private function detailsFor(AuditLog $log, User $recipient): array
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

            if ($this->mayIncludeAmounts($recipient)) {
                $details[] = 'Gross: '.Money::format($subject->gross_total, $subject->currency);
                $details[] = 'Deductions: '.Money::format($subject->deductions_total, $subject->currency);
                $details[] = 'Net pay: '.Money::format($subject->net_total, $subject->currency);
            } else {
                $details[] = 'Amounts: open the payroll run to review totals';
            }

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
                $details[] = 'Balance: '.Money::format($subject->balance, $subject->currency);
            }
        }

        if ($subject instanceof Site) {
            $details[] = 'Site: '.$subject->name;
            if (isset($context['required'])) {
                $details[] = 'Required: '.$context['required'].' guards';
            }
            if (isset($context['deployed'])) {
                $details[] = 'Normal deployment: '.$context['deployed'].' guards';
            }
            if (isset($context['shortage'])) {
                $details[] = 'Operational gap: '.$context['shortage'].' guard'.((int) $context['shortage'] === 1 ? '' : 's');
                $details[] = 'Manpower deficit: '.$context['shortage'];
                $details[] = 'Action required: Arrange replacement or approved overtime coverage.';
            }
        }

        if ($subject instanceof Payment) {
            $subject->loadMissing('invoice:id,reference,balance,currency,due_date', 'client:id,name');
            if ($subject->client) {
                $details[] = 'Client: '.$subject->client->name;
            }
            if ($subject->invoice) {
                $details[] = 'Invoice: '.$subject->invoice->reference;
                $outstanding = (float) $subject->invoice->balance;
                $details[] = $outstanding > 0
                    ? 'Payment: partial, balance '.Money::format($outstanding, $subject->invoice->currency)
                    : 'Payment: paid in full';
            }
        }

        if ($subject instanceof Deployment) {
            $subject->loadMissing('assignedGuard:id,employment_id,full_name', 'site:id,name');
            if ($subject->assignedGuard) {
                $details[] = 'Guard: '.$subject->assignedGuard->full_name.' ('.$subject->assignedGuard->employment_id.')';
            }
            if ($subject->site) {
                $details[] = 'Site: '.$subject->site->name;
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
                $details[] = 'Expiry: '.Carbon::parse($context['expires_at'])->format('d M Y');
            }
        }

        return $details;
    }
}
