<?php

namespace App\Support\Access;

use App\Enums\UserRole;

class PermissionCatalog
{
    /**
     * @return list<array{key: string, group: string, label: string, description: string, roles: list<string>}>
     */
    public static function definitions(): array
    {
        $all = UserRole::values();
        $ops = [
            UserRole::SuperAdmin->value,
            UserRole::OperationsManager->value,
            UserRole::ShiftManager->value,
        ];
        $deploy = [
            UserRole::SuperAdmin->value,
            UserRole::OperationsManager->value,
            UserRole::ShiftManager->value,
            UserRole::RegionSupervisor->value,
        ];
        $hrOps = [
            UserRole::SuperAdmin->value,
            UserRole::OperationsManager->value,
            UserRole::HrManager->value,
            UserRole::ShiftManager->value,
            UserRole::RegionSupervisor->value,
        ];
        $hrCore = [
            UserRole::SuperAdmin->value,
            UserRole::HrManager->value,
        ];
        $admin = [UserRole::SuperAdmin->value];
        $executiveUsers = [
            UserRole::SuperAdmin->value,
            UserRole::ManagingDirector->value,
        ];
        $audit = [
            UserRole::SuperAdmin->value,
            UserRole::ManagingDirector->value,
            UserRole::OperationsManager->value,
        ];
        $financeView = [
            UserRole::SuperAdmin->value,
            UserRole::ManagingDirector->value,
            UserRole::FinanceManager->value,
            UserRole::OperationsManager->value,
        ];
        $financeManage = [
            UserRole::SuperAdmin->value,
            UserRole::ManagingDirector->value,
            UserRole::FinanceManager->value,
        ];

        return [
            [
                'key' => 'admin.users_manage',
                'group' => 'Administration',
                'label' => 'Manage users & account access',
                'description' => 'Create, edit and deactivate system user accounts.',
                'roles' => $executiveUsers,
            ],
            [
                'key' => 'admin.roles_manage',
                'group' => 'Administration',
                'label' => 'Manage roles & permissions',
                'description' => 'View and configure the role permission matrix.',
                'roles' => $admin,
            ],
            [
                'key' => 'admin.settings_manage',
                'group' => 'Administration',
                'label' => 'Manage platform settings',
                'description' => 'White-label branding, finance defaults, shift templates and backup retention settings.',
                'roles' => $admin,
            ],
            [
                'key' => 'admin.backups_manage',
                'group' => 'Administration',
                'label' => 'Manage backups & disaster recovery',
                'description' => 'Create, download, verify, test-restore, and restore database + file backups. Restricted critical infrastructure access.',
                'roles' => $admin,
            ],
            [
                'key' => 'admin.data_import',
                'group' => 'Administration',
                'label' => 'Bulk data import & accounting exports',
                'description' => 'CSV import for guards, sites and opening balances. Scheduled accounting journal exports.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::ManagingDirector->value,
                    UserRole::HrManager->value,
                    UserRole::OperationsManager->value,
                    UserRole::FinanceManager->value,
                ],
            ],
            [
                'key' => 'admin.audit_view',
                'group' => 'Administration',
                'label' => 'View audit logs',
                'description' => 'Read the immutable audit trail for critical actions.',
                'roles' => $audit,
            ],
            [
                'key' => 'admin.records_restore',
                'group' => 'Administration',
                'label' => 'Restore archived records',
                'description' => 'View deletion backups and restore mistakenly removed records.',
                'roles' => $executiveUsers,
            ],
            [
                'key' => 'organization.manage',
                'group' => 'Organization',
                'label' => 'Manage regions, sites, clients & supervisors',
                'description' => 'Create and edit the organization structure.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::ManagingDirector->value,
                    UserRole::OperationsManager->value,
                ],
            ],
            [
                'key' => 'clients.manage',
                'group' => 'Organization',
                'label' => 'Create and edit clients',
                'description' => 'Register clients and update their contract details.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::ManagingDirector->value,
                    UserRole::OperationsManager->value,
                    UserRole::HrManager->value,
                ],
            ],
            [
                'key' => 'organization.view',
                'group' => 'Organization',
                'label' => 'View organization structure',
                'description' => 'Browse regions, sites, clients and supervisors.',
                'roles' => $all,
            ],
            [
                'key' => 'employees.correct_employment_id',
                'group' => 'HR',
                'label' => 'Correct employment IDs',
                'description' => 'Change a permanent Employment ID after registration. Changes are audited.',
                'roles' => $admin,
            ],
            [
                'key' => 'guards.manage',
                'group' => 'Guards',
                'label' => 'Register & edit guards',
                'description' => 'Create and update guard employment records.',
                'roles' => $hrCore,
            ],
            [
                'key' => 'guards.view',
                'group' => 'Guards',
                'label' => 'View guard registry',
                'description' => 'Browse guard profiles and operational status.',
                'roles' => $all,
            ],
            [
                'key' => 'staff.manage',
                'group' => 'Staff',
                'label' => 'Register & edit staff',
                'description' => 'Create and update salaried office employee records.',
                'roles' => $hrCore,
            ],
            [
                'key' => 'hr.promotions_manage',
                'group' => 'Staff',
                'label' => 'Promote employees',
                'description' => 'Change an employee position and salary without creating a new employee record.',
                'roles' => $hrCore,
            ],
            [
                'key' => 'hr.positions_manage',
                'group' => 'Staff',
                'label' => 'Manage positions',
                'description' => 'Configure whether a position is guard, staff, supervisor, or management, and how it is paid.',
                'roles' => $hrCore,
            ],
            [
                'key' => 'staff.salary_manage',
                'group' => 'Staff',
                'label' => 'Manage staff salaries',
                'description' => 'Record salary changes, promotions and demotions. Does not rewrite earlier pay.',
                'roles' => $hrCore,
            ],
            [
                'key' => 'staff.view',
                'group' => 'Staff',
                'label' => 'View staff registry',
                'description' => 'Browse salaried employee profiles for payroll.',
                'roles' => array_values(array_unique([...$all, ...$financeView])),
            ],
            [
                'key' => 'operations.deployments_manage',
                'group' => 'Operations',
                'label' => 'Manage deployments & transfers',
                'description' => 'Assign, transfer and end deployments. Region supervisors are region-scoped.',
                'roles' => $deploy,
            ],
            [
                'key' => 'operations.deploy_board',
                'group' => 'Operations',
                'label' => 'Use deploy board',
                'description' => 'Bulk deploy guards between shifts and sites.',
                'roles' => $deploy,
            ],
            [
                'key' => 'operations.shifts_manage',
                'group' => 'Operations',
                'label' => 'Create & edit shifts',
                'description' => 'Schedule shifts and manage shift lifecycle.',
                'roles' => $ops,
            ],
            [
                'key' => 'operations.shifts_override',
                'group' => 'Operations',
                'label' => 'Authorize critical shift overrides',
                'description' => 'Approve exceptions that bypass shift validation rules.',
                'roles' => $audit,
            ],
            [
                'key' => 'operations.replacements_record',
                'group' => 'Operations',
                'label' => 'Record shift replacements',
                'description' => 'Link covering guards to original shifts.',
                'roles' => $ops,
            ],
            [
                'key' => 'operations.incidents_manage',
                'group' => 'Operations',
                'label' => 'Log & manage occurrence book',
                'description' => 'Record site incidents, attach photos and assign follow-up. Region supervisors are region-scoped.',
                'roles' => $hrOps,
            ],
            [
                'key' => 'operations.work_orders_manage',
                'group' => 'Operations',
                'label' => 'Manage work orders & tasks',
                'description' => 'Assign and complete tasks created from proactive alerts or manual follow-ups.',
                'roles' => $hrOps,
            ],
            [
                'key' => 'operations.periods_manage',
                'group' => 'Operations',
                'label' => 'Finalize operational periods',
                'description' => 'Close or re-open monthly operational periods so historical duty records cannot be casually changed after month-end.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::ManagingDirector->value,
                    UserRole::OperationsManager->value,
                ],
            ],
            [
                'key' => 'operations.historical_correct',
                'group' => 'Operations',
                'label' => 'Correct finalized historical records',
                'description' => 'Enter or correct past-dated operational records inside a finalized month when a correction reason is provided.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::ManagingDirector->value,
                    UserRole::OperationsManager->value,
                    UserRole::ShiftManager->value,
                ],
            ],
            [
                'key' => 'hr.leave_types_manage',
                'group' => 'HR',
                'label' => 'Manage leave types',
                'description' => 'Configure leave types, pay rules, and yearly entitlements.',
                'roles' => $hrCore,
            ],
            [
                'key' => 'hr.leaves_manage',
                'group' => 'HR',
                'label' => 'Create & update leave requests',
                'description' => 'Record and edit guard leave requests before approval.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::HrManager->value,
                    UserRole::OperationsManager->value,
                    UserRole::ShiftManager->value,
                ],
            ],
            [
                'key' => 'hr.leaves_approve',
                'group' => 'HR',
                'label' => 'Approve / reject leave',
                'description' => 'Approve or reject guard leave requests.',
                'roles' => $hrCore,
            ],
            [
                'key' => 'hr.absences_attendance_record',
                'group' => 'HR',
                'label' => 'Record absences & attendance',
                'description' => 'Daily absence and attendance workflows.',
                'roles' => $hrOps,
            ],
            [
                'key' => 'hr.desertions_manage',
                'group' => 'HR',
                'label' => 'Manage desertions',
                'description' => 'Record and follow up desertion cases. Region supervisors are region-scoped.',
                'roles' => $hrOps,
            ],
            [
                'key' => 'hr.salary_manage',
                'group' => 'HR',
                'label' => 'Manage guard salaries',
                'description' => 'Record salary increments and view salary history. Does not rewrite earlier pay.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::ManagingDirector->value,
                    UserRole::HrManager->value,
                    UserRole::FinanceManager->value,
                ],
            ],
            [
                'key' => 'hr.uniform_exemptions_manage',
                'group' => 'HR',
                'label' => 'Manage uniform charge exemptions',
                'description' => 'Record effective-dated uniform charge exemptions for individual guards. Does not change the company charge or finalized payroll.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::HrManager->value,
                    UserRole::FinanceManager->value,
                ],
            ],
            [
                'key' => 'hr.assets_manage',
                'group' => 'HR',
                'label' => 'Issue & return assets / uniforms',
                'description' => 'Track uniforms, radios, boots and weapons issued to guards. Record returns and replacement cost recovery.',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::HrManager->value,
                    UserRole::FinanceManager->value,
                    UserRole::OperationsManager->value,
                    UserRole::ProcurementOfficer->value,
                ],
            ],
            [
                'key' => 'finance.view',
                'group' => 'Finance',
                'label' => 'View finance module',
                'description' => 'Billing, invoices, payments, payroll, ledger and profitability reports.',
                'roles' => $financeView,
            ],
            [
                'key' => 'finance.manage',
                'group' => 'Finance',
                'label' => 'Manage finance records',
                'description' => 'Create and edit billing, invoices, payments, GL accounts, bank reconciliation and period close.',
                'roles' => $financeManage,
            ],
            [
                'key' => 'finance.purchases_manage',
                'group' => 'Finance',
                'label' => 'Manage supplier purchases',
                'description' => 'Create, edit, post and cancel supplier purchase bills (input VAT / accounts payable).',
                'roles' => [
                    UserRole::SuperAdmin->value,
                    UserRole::ManagingDirector->value,
                    UserRole::FinanceManager->value,
                    UserRole::ProcurementOfficer->value,
                ],
            ],
            [
                'key' => 'finance.payroll.approve',
                'group' => 'Finance',
                'label' => 'Approve & reject payroll',
                'description' => 'Executive approval or rejection of submitted payroll runs.',
                'roles' => $executiveUsers,
            ],
            [
                'key' => 'reporting.view_export',
                'group' => 'Reporting',
                'label' => 'View & export reports',
                'description' => 'Operational, HR and financial report exports.',
                'roles' => $all,
            ],
            [
                'key' => 'dashboards.operational_view',
                'group' => 'Dashboards',
                'label' => 'View operational dashboards',
                'description' => 'Company, region, site and guard dashboard views.',
                'roles' => $all,
            ],
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_column(self::definitions(), 'key');
    }

    public static function isValidKey(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    /** @return list<string> */
    public static function defaultRolesFor(string $key): array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['key'] === $key) {
                return $definition['roles'];
            }
        }

        return [];
    }
}
