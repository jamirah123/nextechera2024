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
            'leave.cancelled' => [
                'permissions' => ['hr.leaves_manage'],
                'subject' => 'Leave request cancelled',
                'headline' => 'Leave request cancelled',
                'action_label' => 'View leave request',
                'include_stakeholders' => true,
            ],
            'leave.shift_affected' => [
                'permissions' => ['hr.leaves_manage'],
                'subject' => 'Approved leave affects a scheduled shift',
                'headline' => 'A guard on leave still has a scheduled shift',
                'action_label' => 'Arrange a replacement',
            ],
            'leave.starting_soon' => [
                'permissions' => ['hr.leaves_manage'],
                'subject' => 'Approved leave starts tomorrow',
                'headline' => 'An employee starts leave tomorrow',
                'action_label' => 'View leave request',
                'include_stakeholders' => true,
            ],
            'leave.return_due' => [
                'permissions' => ['hr.leaves_manage'],
                'subject' => 'Employee is due back from leave',
                'headline' => 'An employee is due to return today',
                'action_label' => 'View leave request',
                'include_stakeholders' => true,
            ],
            'leave.completed' => [
                'permissions' => ['hr.leaves_manage'],
                'subject' => 'Leave completed',
                'headline' => 'Leave has ended and the record is complete',
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
                'subject' => 'Application backup completed',
                'headline' => 'Automated / manual application backup succeeded',
                'action_label' => 'Open backup console',
            ],
            'backup.failed' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Application backup failed',
                'headline' => 'Application backup failed — investigate immediately',
                'action_label' => 'Open backup console',
            ],
            'backup.verify_failed' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Backup integrity check failed',
                'headline' => 'A backup failed checksum verification',
                'action_label' => 'Open backup console',
            ],
            'backup.missed' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Missed or stale backup',
                'headline' => 'No successful backup within the configured freshness window',
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
            'backup.files_restored' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Files restored from backup',
                'headline' => 'Private files were restored from a backup archive',
                'action_label' => 'Open backup console',
            ],
            'backup.offsite_failed' => [
                'permissions' => ['admin.backups_manage'],
                'subject' => 'Off-site backup copy failed',
                'headline' => 'The backup could not be copied off this server',
                'action_label' => 'Open backup console',
                'priority' => 'critical',
            ],
            'payroll.calculated' => [
                'roles' => $payrollRoles,
                'subject' => 'Payroll calculation completed',
                'headline' => 'Payroll calculation completed',
                'action_label' => 'Review payroll run',
                'channel' => 'in_app',
            ],
            'payroll.run_created' => [
                'roles' => $payrollRoles,
                'subject' => 'Payroll period opened',
                'headline' => 'A payroll period was opened',
                'action_label' => 'Open payroll run',
            ],
            'payroll.cancelled' => [
                'roles' => $payrollRoles,
                'subject' => 'Payroll run cancelled',
                'headline' => 'A payroll run was cancelled',
                'action_label' => 'Review payroll run',
                'priority' => 'important',
            ],
            'employee.promoted' => [
                'permissions' => ['staff.manage', 'staff.salary_manage'],
                'subject' => 'Employee promoted',
                'headline' => 'An employee was promoted',
                'action_label' => 'View employee',
                'priority' => 'important',
            ],
            'guard.salary_changed' => [
                'permissions' => ['hr.salary_manage', 'staff.salary_manage', 'finance.view'],
                'subject' => 'Guard salary changed',
                'headline' => 'A guard salary change was recorded',
                'action_label' => 'View guard profile',
                'priority' => 'important',
            ],
            'staff.salary_changed' => [
                'permissions' => ['hr.salary_manage', 'staff.salary_manage', 'finance.view'],
                'subject' => 'Staff salary changed',
                'headline' => 'A staff salary change was recorded',
                'action_label' => 'View employee',
                'priority' => 'important',
            ],
            'deployment.transferred' => [
                'roles' => $opsRoles,
                'subject' => 'Guard transferred between sites',
                'headline' => 'A guard was transferred to another site',
                'action_label' => 'View deployment',
                'priority' => 'important',
            ],
            'deployment.corrected' => [
                'roles' => $opsRoles,
                'subject' => 'Deployment corrected',
                'headline' => 'A historical deployment was corrected',
                'action_label' => 'View deployment',
                'priority' => 'important',
            ],
            'deployment.overtime_coverage_created' => [
                'roles' => $opsRoles,
                'subject' => 'Overtime coverage deployed',
                'headline' => 'Temporary overtime coverage was deployed',
                'action_label' => 'View deployment',
                'priority' => 'important',
            ],
            'deployment.temporary_coverage_created' => [
                'roles' => $opsRoles,
                'subject' => 'Temporary coverage deployed',
                'headline' => 'Temporary coverage was deployed for a manpower gap',
                'action_label' => 'View deployment',
                'priority' => 'important',
            ],
            'shift.replacement_recorded' => [
                'roles' => $opsRoles,
                'subject' => 'Guard replacement recorded',
                'headline' => 'A guard was replaced on a shift',
                'action_label' => 'View shift',
                'priority' => 'important',
            ],
            'deployment.shift_reassigned' => [
                'roles' => $opsRoles,
                'subject' => 'Shift reassigned',
                'headline' => 'A deployment shift was reassigned',
                'action_label' => 'View deployment',
                'priority' => 'important',
            ],
            'finance.invoice_cancelled' => [
                'permissions' => ['finance.view'],
                'subject' => 'Invoice cancelled',
                'headline' => 'A client invoice was cancelled',
                'action_label' => 'View invoice',
                'priority' => 'important',
            ],
            'finance.payment_recorded' => [
                'permissions' => ['finance.view'],
                'subject' => 'Payment received',
                'headline' => 'A client payment was recorded',
                'action_label' => 'View payment',
                'priority' => 'important',
            ],
            'user.created' => [
                'permissions' => ['admin.settings_manage'],
                'subject' => 'User account created',
                'headline' => 'A user account was created',
                'action_label' => 'View users',
                'priority' => 'important',
            ],
            'user.updated' => [
                'permissions' => ['admin.settings_manage'],
                'subject' => 'User access changed',
                'headline' => 'A user role or account status changed',
                'action_label' => 'View users',
                'priority' => 'important',
            ],
            'user.deleted' => [
                'permissions' => ['admin.settings_manage'],
                'subject' => 'User account removed',
                'headline' => 'A user account was removed',
                'action_label' => 'View users',
                'priority' => 'critical',
            ],
            'user.password_reset' => [
                'permissions' => ['admin.settings_manage'],
                'subject' => 'Password reset by an administrator',
                'headline' => 'An administrator reset a user password',
                'action_label' => 'View users',
                'priority' => 'critical',
            ],
            'auth.password_reset' => [
                'permissions' => ['admin.settings_manage'],
                'subject' => 'Password was reset',
                'headline' => 'A user completed a password reset',
                'action_label' => 'View users',
                'priority' => 'critical',
            ],
            'system.settings_updated' => [
                'permissions' => ['admin.settings_manage'],
                'subject' => 'System settings changed',
                'headline' => 'Platform settings were changed',
                'action_label' => 'Open platform settings',
                'priority' => 'important',
            ],
            'queue.unhealthy' => [
                'permissions' => ['admin.settings_manage'],
                'subject' => 'Queue needs attention',
                'headline' => 'Background jobs are failing repeatedly',
                'action_label' => 'Open email delivery history',
                'priority' => 'critical',
            ],
            'leave.return_missed' => [
                'permissions' => ['hr.leaves_manage'],
                'subject' => 'Employee has not returned from leave',
                'headline' => 'An employee is still on leave after the return date',
                'action_label' => 'View leave request',
                'priority' => 'critical',
                'include_stakeholders' => true,
            ],
        ];
    }

    public static function priority(string $action, array $context = []): string
    {
        if ($action === 'user.created') {
            return ($context['role'] ?? null) === UserRole::SuperAdmin->value ? 'critical' : 'important';
        }

        $explicit = self::find($action)['priority'] ?? null;

        if (is_string($explicit)) {
            return $explicit;
        }

        return match ($action) {
            'backup.failed', 'backup.verify_failed', 'backup.restore_failed', 'backup.missed' => 'critical',
            'payroll.submitted', 'payroll.rejected', 'leave.requested', 'leave.rejected', 'leave.shift_affected', 'leave.pending_reminder', 'site.understaffed', 'site.sla_breach', 'finance.invoice_overdue', 'guard.document_expired', 'desertion.reported' => 'important',
            default => 'normal',
        };
    }

    public static function channel(string $action): string
    {
        $explicit = self::find($action)['channel'] ?? null;

        return is_string($explicit) ? $explicit : 'both';
    }

    public static function sensitive(string $action): bool
    {
        return str_starts_with($action, 'payroll.') || str_ends_with($action, '.salary_changed');
    }

    /** @return list<UserRole>|null */
    public static function audienceRoles(string $audience): ?array
    {
        return match ($audience) {
            'hr' => [UserRole::SuperAdmin, UserRole::ManagingDirector, UserRole::HrManager],
            'operations' => self::operationsNotificationRoles(),
            'finance' => [UserRole::SuperAdmin, UserRole::ManagingDirector, UserRole::FinanceManager],
            'administrators' => [UserRole::SuperAdmin, UserRole::ManagingDirector],
            default => null,
        };
    }

    public static function has(string $action): bool
    {
        return array_key_exists($action, self::definitions());
    }

    /** @return array{permissions?: list<string>, roles?: list<UserRole>, subject: string, headline: string, action_label: string, include_stakeholders?: bool}|null */
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
