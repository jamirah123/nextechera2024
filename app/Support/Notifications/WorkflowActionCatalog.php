<?php

namespace App\Support\Notifications;

use App\Enums\UserRole;

class WorkflowActionCatalog
{
    /** @return list<UserRole> */
    public static function payrollNotificationRoles(): array
    {
        return [
            UserRole::SuperAdmin,
            UserRole::ManagingDirector,
            UserRole::OperationsManager,
            UserRole::HrManager,
            UserRole::FinanceManager,
        ];
    }

    /** @return list<UserRole> */
    public static function operationsNotificationRoles(): array
    {
        return [
            UserRole::SuperAdmin,
            UserRole::ManagingDirector,
            UserRole::OperationsManager,
            UserRole::ShiftManager,
            UserRole::RegionSupervisor,
        ];
    }

    /**
     * Maps audit actions to permission keys or role-based email recipients.
     *
     * @return array<string, array{permissions?: list<string>, roles?: list<UserRole>, subject: string, headline: string, action_label: string, include_stakeholders?: bool}>
     */
    public static function definitions(): array
    {
        $payrollRoles = self::payrollNotificationRoles();
        $opsRoles = self::operationsNotificationRoles();

        return [
            'payroll.submitted' => [
                'roles' => $payrollRoles,
                'subject' => 'Payroll submitted for approval',
                'headline' => 'Payroll submitted for approval',
                'action_label' => 'Review payroll run',
            ],
            'payroll.approved' => [
                'roles' => $payrollRoles,
                'subject' => 'Payroll approved',
                'headline' => 'Payroll run approved — review totals in system',
                'action_label' => 'Open payroll run',
            ],
            'payroll.rejected' => [
                'roles' => $payrollRoles,
                'subject' => 'Payroll returned for revision',
                'headline' => 'Payroll run returned to finance',
                'action_label' => 'Review payroll run',
            ],
            'payroll.paid' => [
                'roles' => $payrollRoles,
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
            'leave.pending_reminder' => [
                'permissions' => ['hr.leaves_approve'],
                'subject' => 'Leave request still pending',
                'headline' => 'Leave approval still pending',
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
            'finance.invoice_overdue' => [
                'permissions' => ['finance.view'],
                'subject' => 'Invoice overdue',
                'headline' => 'Client invoice is overdue',
                'action_label' => 'View invoice',
            ],
            'shift.missed' => [
                'roles' => $opsRoles,
                'subject' => 'Shift missed / no-show',
                'headline' => 'Shift marked as missed',
                'action_label' => 'View shift',
            ],
            'site.understaffed' => [
                'roles' => $opsRoles,
                'subject' => 'Site understaffed',
                'headline' => 'Site is understaffed',
                'action_label' => 'View site coverage',
            ],
            'guard.document_expiring' => [
                'permissions' => ['guards.manage', 'hr.leaves_manage'],
                'subject' => 'Guard document expiring soon',
                'headline' => 'Guard document expiring soon',
                'action_label' => 'View guard profile',
            ],
            'guard.document_expired' => [
                'permissions' => ['guards.manage', 'hr.leaves_manage'],
                'subject' => 'Guard document expired',
                'headline' => 'Guard document has expired',
                'action_label' => 'View guard profile',
            ],
            'client.contract_expiring' => [
                'roles' => $opsRoles,
                'subject' => 'Client contract expiring soon',
                'headline' => 'Client contract renewal due',
                'action_label' => 'View client',
            ],
            'client.contract_expired' => [
                'roles' => $opsRoles,
                'subject' => 'Client contract expired',
                'headline' => 'Client contract has expired',
                'action_label' => 'View client',
            ],
            'site.contract_expiring' => [
                'roles' => $opsRoles,
                'subject' => 'Site contract expiring soon',
                'headline' => 'Site contract renewal due',
                'action_label' => 'View site',
            ],
            'site.contract_expired' => [
                'roles' => $opsRoles,
                'subject' => 'Site contract expired',
                'headline' => 'Site contract has expired',
                'action_label' => 'View site',
            ],
            'site.sla_breach' => [
                'roles' => $opsRoles,
                'subject' => 'Site SLA breach',
                'headline' => 'Contracted guard count not met',
                'action_label' => 'View site coverage',
            ],
            'guard.contract_expiring' => [
                'permissions' => ['guards.manage', 'hr.leaves_manage'],
                'subject' => 'Guard contract expiring soon',
                'headline' => 'Guard employment contract renewal due',
                'action_label' => 'View guard profile',
            ],
            'guard.contract_expired' => [
                'permissions' => ['guards.manage', 'hr.leaves_manage'],
                'subject' => 'Guard contract expired',
                'headline' => 'Guard employment contract expired',
                'action_label' => 'View guard profile',
            ],
            'desertion.reported' => [
                'permissions' => ['hr.desertions_manage'],
                'subject' => 'Desertion reported',
                'headline' => 'Desertion case requires HR follow-up',
                'action_label' => 'View desertion case',
            ],
            'work_order.created' => [
                'permissions' => ['operations.work_orders_manage'],
                'subject' => 'New work order',
                'headline' => 'A new work order was created',
                'action_label' => 'Open work order',
            ],
            'work_order.assigned' => [
                'permissions' => ['operations.work_orders_manage'],
                'subject' => 'Work order assigned',
                'headline' => 'A work order was assigned',
                'action_label' => 'Open work order',
            ],
            'work_order.completed' => [
                'permissions' => ['operations.work_orders_manage'],
                'subject' => 'Work order completed',
                'headline' => 'A work order was marked complete',
                'action_label' => 'View work order',
            ],
            'backup.completed' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Database backup completed',
                'headline' => 'Automated / manual database backup succeeded',
                'action_label' => 'Open backup console',
            ],
            'backup.failed' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Database backup failed',
                'headline' => 'Database backup failed — investigate immediately',
                'action_label' => 'Open backup console',
            ],
            'backup.verify_failed' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Backup integrity check failed',
                'headline' => 'A database backup failed checksum verification',
                'action_label' => 'Open backup console',
            ],
            'backup.restored' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Database restored from backup',
                'headline' => 'Live database was restored from a backup',
                'action_label' => 'Open backup console',
            ],
            'backup.restore_failed' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Database restore failed',
                'headline' => 'Database restore failed after safety backup',
                'action_label' => 'Open backup console',
            ],
        ];
    }

    public static function has(string $action): bool
    {
        return array_key_exists($action, self::definitions());
    }

    /** @return array{permissions?: list<string>, roles?: list<\App\Enums\UserRole>, subject: string, headline: string, action_label: string, include_stakeholders?: bool}|null */
    public static function find(string $action): ?array
    {
        return self::definitions()[$action] ?? null;
    }

    public static function findForAudit(string $action, array $context = []): ?array
    {
        if ($action === 'shift.status_changed' && ($context['status'] ?? null) === 'missed') {
            return self::find('shift.missed');
        }

        return self::find($action);
    }
}
