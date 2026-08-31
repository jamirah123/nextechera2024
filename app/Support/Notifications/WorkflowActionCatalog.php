<?php

namespace App\Support\Notifications;

class WorkflowActionCatalog
{
    /**
     * Maps audit actions to permission keys for email recipients.
     *
     * @return array<string, array{permissions: list<string>, subject: string, headline: string, action_label: string, include_stakeholders?: bool}>
     */
    public static function definitions(): array
    {
        return [
            'payroll.submitted' => [
                'permissions' => ['finance.payroll.approve'],
                'subject' => 'Payroll submitted for approval',
                'headline' => 'Payroll submitted for your approval',
                'action_label' => 'Review payroll run',
            ],
            'payroll.approved' => [
                'permissions' => ['finance.manage'],
                'subject' => 'Payroll approved',
                'headline' => 'Payroll run approved',
                'action_label' => 'Open payroll run',
                'include_stakeholders' => true,
            ],
            'payroll.rejected' => [
                'permissions' => ['finance.manage'],
                'subject' => 'Payroll returned for revision',
                'headline' => 'Payroll run returned to finance',
                'action_label' => 'Review payroll run',
                'include_stakeholders' => true,
            ],
            'payroll.paid' => [
                'permissions' => ['finance.payroll.approve', 'finance.manage'],
                'subject' => 'Payroll marked as paid',
                'headline' => 'Payroll disbursement completed',
                'action_label' => 'View payroll run',
            ],
            'leave.requested' => [
                'permissions' => ['hr.leaves_approve'],
                'subject' => 'Leave request pending approval',
                'headline' => 'New leave request to review',
                'action_label' => 'Review leave request',
            ],
            'leave.approved' => [
                'permissions' => ['hr.leaves_manage'],
                'subject' => 'Leave request approved',
                'headline' => 'Leave request approved',
                'action_label' => 'View leave request',
                'include_stakeholders' => true,
            ],
            'leave.rejected' => [
                'permissions' => ['hr.leaves_manage'],
                'subject' => 'Leave request rejected',
                'headline' => 'Leave request rejected',
                'action_label' => 'View leave request',
                'include_stakeholders' => true,
            ],
            'finance.invoice_issued' => [
                'permissions' => ['finance.view'],
                'subject' => 'Invoice issued',
                'headline' => 'Client invoice issued',
                'action_label' => 'View invoice',
            ],
        ];
    }

    public static function has(string $action): bool
    {
        return array_key_exists($action, self::definitions());
    }

    /** @return array{permissions: list<string>, subject: string, headline: string, action_label: string, include_stakeholders?: bool}|null */
    public static function find(string $action): ?array
    {
        return self::definitions()[$action] ?? null;
    }
}
