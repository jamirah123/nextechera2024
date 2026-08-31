<?php

namespace App\Services;

use App\Mail\WorkflowActionMail;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\PayrollRun;
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

        $definition = WorkflowActionCatalog::find($log->action);

        if ($definition === null) {
            return;
        }

        $recipients = $this->resolveRecipients($log, $definition);

        if ($recipients->isEmpty()) {
            return;
        }

        $mail = new WorkflowActionMail(
            headline: $definition['headline'],
            summary: $log->summary,
            actorName: $log->actor_name,
            actionUrl: $this->actionUrlFor($log),
            actionLabel: $definition['action_label'],
            details: $this->detailsFor($log),
        );

        foreach ($recipients as $recipient) {
            Mail::to($recipient->email)->send($mail);
        }
    }

    /**
     * @param  array{permissions: list<string>, subject: string, headline: string, action_label: string, include_stakeholders?: bool}  $definition
     * @return Collection<int, User>
     */
    private function resolveRecipients(AuditLog $log, array $definition): Collection
    {
        $recipients = $this->usersWithAnyPermission($definition['permissions']);

        if ($definition['include_stakeholders'] ?? false) {
            $recipients = $recipients->merge($this->stakeholdersFor($log));
        }

        return $recipients
            ->filter(fn (User $user) => filled($user->email))
            ->unique('id')
            ->reject(fn (User $user) => $log->actor_id !== null && $user->id === $log->actor_id)
            ->values();
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
            $details[] = 'Reference: '.$subject->reference;
            $details[] = 'Period: '.$subject->periodLabel();
        }

        if ($subject instanceof Invoice) {
            $details[] = 'Invoice: '.$subject->reference;
        }

        return $details;
    }
}
