<?php

namespace App\Support\Navigation;

use App\Enums\DeploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\Finance\ProfitabilityService;
use App\Support\Money;

class RoleNavigation
{
    /**
     * @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}>
     */
    public static function for(User $user): array
    {
        $items = match ($user->role) {
            UserRole::SuperAdmin => self::superAdmin(),
            UserRole::ManagingDirector => self::managingDirector(),
            UserRole::OperationsManager => self::operations(),
            UserRole::HrManager => self::hr(),
            UserRole::ShiftManager => self::shift(),
            UserRole::FinanceManager => self::finance(),
            UserRole::RegionSupervisor => self::regionSupervisor(),
            default => self::fallback(),
        };

        return NavigationAccess::filter($user, $items);
    }

    /**
     * @return list<array{title: string, description: string, icon: string, href: string, badge?: string|null, tone: string}>
     */
    public static function modules(User $user): array
    {
        return match ($user->role) {
            UserRole::SuperAdmin => [
                self::module('Users & Access', 'Manage system users, roles and permissions.', 'users', 'slate', route('users.index')),
                self::module('Organization', 'Regions, supervisors, clients and security sites.', 'building', 'indigo', route('organization.index')),
                self::module('Ops Dashboards', 'Company, region, site and guard operational views.', 'chart', 'brand', route('ops-dashboards.company')),
                self::module('Manpower Coverage', 'Required vs deployed staffing across sites.', 'chart', 'amber', route('manpower.coverage')),
                self::module('Guards', 'Company-wide guard registry and employment records.', 'shield', 'sky', route('guards.index')),
                self::module('Site Postings', 'Active site postings, transfers and relief.', 'map', 'emerald', route('deployments.index')),
                self::module('Shifts', 'Schedules, calendar and shift validation oversight.', 'calendar', 'amber', route('shifts.index')),
                self::module('Replacements', 'Link original shifts to covering guards.', 'swap', 'indigo', route('replacements.index')),
                self::module('Leave', 'Leave requests, approvals and conflict handling.', 'leave', 'sky', route('leaves.index')),
                self::module('Assets & uniforms', 'Issue kit, radios, boots and weapons. Track returns and cost recovery.', 'shield', 'indigo', route('assets.index')),
                self::module('Absences', 'Daily absence recording and follow-up.', 'alert', 'amber', route('absences.index')),
                self::module('Occurrence Book', 'Daily site incident log and client reports.', 'report', 'rose', route('incidents.index')),
                self::module('Work orders', 'Assignable tasks from alerts — staffing, contracts, HR follow-up.', 'report', 'indigo', route('work-orders.index')),
                self::module('Finance', 'Client billing, invoices, payments and profitability.', 'wallet', 'emerald', route('billing.index')),
                self::module('Reports', 'Operational, HR and financial report exports.', 'chart', 'violet', route('reports.index')),
                self::module('Audit Logs', 'Immutable trail of critical system actions.', 'audit', 'rose', route('audit.index')),
                self::module('Archived Records', 'Deletion backups that can be restored by administrators.', 'audit', 'amber', route('archived.index')),
                self::module('Database Backups', 'Create, verify, download and restore full database backups.', 'settings', 'rose', route('backups.index')),
                self::module('Platform Settings', 'White-label branding, finance defaults, shift times and backup policy.', 'settings', 'violet', route('settings.index')),
                self::module('Bulk Import / Export', 'CSV migration for guards, sites, opening balances and accounting exports.', 'report', 'sky', route('data-import.index')),
                self::module('Roles & Permissions', 'Configure role capabilities and access control.', 'settings', 'violet', route('roles.index')),
            ],
            UserRole::ManagingDirector => [
                self::module('Users & Access', 'Manage system user accounts.', 'users', 'slate', route('users.index')),
                self::module('Organization', 'Regions, supervisors, clients and security sites.', 'building', 'indigo', route('organization.index')),
                self::module('Ops Dashboards', 'Company, region, site and guard operational views.', 'chart', 'brand', route('ops-dashboards.company')),
                self::module('Manpower Coverage', 'Required vs deployed staffing across sites.', 'chart', 'amber', route('manpower.coverage')),
                self::module('Guards', 'Company-wide guard registry and employment records.', 'shield', 'sky', route('guards.index')),
                self::module('Site Postings', 'Active site postings, transfers and relief.', 'map', 'emerald', route('deployments.index')),
                self::module('Shifts', 'Schedules, calendar and shift validation oversight.', 'calendar', 'amber', route('shifts.index')),
                self::module('Replacements', 'Link original shifts to covering guards.', 'swap', 'indigo', route('replacements.index')),
                self::module('Leave', 'Leave requests, approvals and conflict handling.', 'leave', 'sky', route('leaves.index')),
                self::module('Absences', 'Daily absence recording and follow-up.', 'alert', 'amber', route('absences.index')),
                self::module('Desertions', 'Desertion cases and HR follow-up.', 'warning', 'rose', route('desertions.index')),
                self::module('Occurrence Book', 'Daily site incident log and client reports.', 'report', 'rose', route('incidents.index')),
                self::module('Work orders', 'Assignable tasks from alerts — staffing, contracts, HR follow-up.', 'report', 'indigo', route('work-orders.index')),
                self::module('Attendance', 'Attendance records and field check-ins.', 'calendar', 'brand', route('attendances.index')),
                self::module('Finance', 'Client billing, invoices, payments and profitability.', 'wallet', 'emerald', route('billing.index')),
                self::module('Reports', 'Operational, HR and financial report exports.', 'chart', 'violet', route('reports.index')),
                self::module('Audit Logs', 'Immutable trail of critical system actions.', 'audit', 'rose', route('audit.index')),
                self::module('Archived Records', 'Deletion backups that can be restored by administrators.', 'audit', 'amber', route('archived.index')),
            ],
            UserRole::OperationsManager => [
                self::module('Ops Dashboards', 'Company and regional operational command views.', 'chart', 'brand', route('ops-dashboards.company')),
                self::module('Organization', 'Regions, supervisors, clients and sites.', 'building', 'indigo', route('organization.index')),
                self::module('Manpower Coverage', 'Site staffing levels, shortages and surplus.', 'chart', 'amber', route('manpower.coverage')),
                self::module('Guards', 'Review employment and operational guard status.', 'shield', 'sky', route('guards.index')),
                self::module('Sites', 'Security sites and manpower requirements.', 'map', 'brand', route('sites.index')),
                self::module('Site Posting Board', 'Post guards to client sites (day / night / rotating cover).', 'map', 'emerald', route('deployments.board')),
                self::module('Duty Roster', 'Confirm daily Day/Night duties from posted guards.', 'calendar', 'amber', route('shifts.allocate')),
                self::module('Duty Register', 'Live duties — in progress, completed, missed (payroll source).', 'calendar', 'brand', route('shifts.index')),
                self::module('Site Postings', 'Active postings, transfers, letters and corrections.', 'map', 'emerald', route('deployments.index')),
                self::module('Replacements', 'Track original vs covering guards on duty.', 'swap', 'indigo', route('replacements.index')),
                self::module('Operational Reports', 'Shift, overtime and coverage reports.', 'report', 'violet', route('reports.index')),
                self::module('Occurrence Book', 'Daily site incident log and client reports.', 'report', 'rose', route('incidents.index')),
                self::module('Work orders', 'Assignable tasks from alerts — staffing, contracts, HR follow-up.', 'report', 'indigo', route('work-orders.index')),
                self::module('Assets & uniforms', 'Issue kit, radios, boots and weapons. Track returns and cost recovery.', 'shield', 'indigo', route('assets.index')),
                self::module('Audit Logs', 'Overrides and critical operational events.', 'audit', 'rose', route('audit.index')),
            ],
            UserRole::HrManager => [
                self::module('All Guards', 'Register and maintain guard employment records.', 'shield', 'brand', route('guards.index')),
                self::module('Staff', 'Register salaried office and admin employees.', 'users', 'indigo', route('staff.index')),
                self::module('Organization', 'View regions, sites and supervisors.', 'building', 'indigo', route('organization.index')),
                self::module('Leave Management', 'Approve leave and detect schedule conflicts.', 'leave', 'sky', route('leaves.index')),
                self::module('Absences', 'Record absences and trigger replacements.', 'alert', 'amber', route('absences.index')),
                self::module('Assets & uniforms', 'Issue kit, radios, boots and weapons. Track returns and cost recovery.', 'shield', 'indigo', route('assets.index')),
                self::module('Desertions', 'Track deserted guards and HR follow-up.', 'warning', 'rose', route('desertions.index')),
                self::module('Occurrence Book', 'Daily site incident log and client reports.', 'report', 'rose', route('incidents.index')),
                self::module('Work orders', 'Assignable tasks from alerts — staffing, contracts, HR follow-up.', 'report', 'indigo', route('work-orders.index')),
                self::module('Attendance', 'Manual attendance events with status history preserved.', 'calendar', 'brand', route('attendances.index')),
                self::module('HR Reports', 'Employment, leave, absence and work summaries.', 'report', 'violet', route('reports.hr')),
            ],
            UserRole::ShiftManager => [
                self::module('Site Posting Board', 'Post undeployed guards to client sites.', 'map', 'emerald', route('deployments.board')),
                self::module('Duty Roster', 'Daily parade roster — who works Day or Night.', 'plus', 'brand', route('shifts.allocate')),
                self::module('Guards', 'Check availability and operational status.', 'shield', 'sky', route('guards.index')),
                self::module('Sites & Manpower', 'Review site posts before rostering.', 'chart', 'amber', route('manpower.coverage')),
                self::module('Organization', 'Regions, supervisors and site structure.', 'building', 'indigo', route('organization.index')),
                self::module('Duty Register', 'Monitor and correct the live duty log.', 'calendar', 'sky', route('shifts.index')),
                self::module('Replacements', 'Cover unavailable guards and keep reports accurate.', 'swap', 'indigo', route('replacements.index')),
                self::module('Absences', 'Record no-shows and trigger replacement review.', 'alert', 'amber', route('absences.index')),
                self::module('Desertions', 'Report deserted guards for HR follow-up.', 'warning', 'rose', route('desertions.index')),
                self::module('Occurrence Book', 'Log site incidents and export daily reports.', 'report', 'rose', route('incidents.index')),
                self::module('Shift Reports', 'Daily and monthly shift and overtime exports.', 'report', 'violet', route('reports.index')),
            ],
            UserRole::FinanceManager => [
                self::module('Clients & Sites', 'Contracts and site structure for billing context.', 'building', 'indigo', route('organization.index')),
                self::module('Guards', 'Read-only employment context for payroll reporting.', 'shield', 'sky', route('guards.index')),
                self::module('Staff', 'Salaried employees included in monthly payroll.', 'users', 'indigo', route('staff.index')),
                self::module('Client Billing', 'Contracts, billing rates and client revenue.', 'wallet', 'brand', route('billing.index')),
                self::module('Invoices', 'Create, approve and track client invoices.', 'invoice', 'indigo', route('invoices.index')),
                self::module('Payments', 'Record payments and outstanding balances.', 'payment', 'emerald', route('payments.index')),
                self::module('Profitability', 'Client, site and region profitability analysis.', 'chart', 'violet', route('profitability.index')),
                self::module('Payroll', 'Monthly payroll runs, payslips and bank payment files.', 'payroll', 'emerald', route('payroll.index')),
                self::module('Advances', 'Guard and staff salary advances recovered through payroll.', 'wallet', 'amber', route('advances.index')),
                self::module('General ledger', 'Chart of accounts, journals, VAT pack, bank reconciliation and period close.', 'wallet', 'indigo', route('ledger.index')),
                self::module('Purchases', 'Supplier bills with input VAT posting to accounts payable.', 'invoice', 'violet', route('ledger.purchases.index')),
                self::module('Assets & uniforms', 'Issue kit and track replacement cost recovery on payroll.', 'shield', 'indigo', route('assets.index')),
                self::module('Monthly Shift Exports', 'Payroll-ready normal and overtime shift totals.', 'report', 'sky', route('reports.monthly-shifts')),
            ],
            UserRole::RegionSupervisor => [
                self::module('Site Posting Board', 'Post guards to sites in your region.', 'map', 'emerald', route('deployments.board')),
                self::module('Absences', 'Record absent guards from the field.', 'alert', 'amber', route('absences.index')),
                self::module('Desertions', 'Report deserted guards for HR follow-up.', 'warning', 'rose', route('desertions.index')),
                self::module('Occurrence Book', 'Log site incidents from the field.', 'report', 'rose', route('incidents.index')),
                self::module('Guards', 'Review guards available in your region.', 'shield', 'sky', route('guards.index')),
                self::module('Sites & Manpower', 'Coverage vs requirement for your sites.', 'chart', 'amber', route('manpower.coverage')),
                self::module('Duty Register', 'Read-only regional duty log.', 'calendar', 'brand', route('shifts.index')),
                self::module('Ops Dashboard', 'Regional operational snapshot.', 'chart', 'indigo', route('ops-dashboards.company')),
            ],
            default => [],
        };
    }

    /**
     * @return list<array{label: string, value: string, hint: string, tone: string}>
     */
    public static function kpis(User $user): array
    {
        $regionId = $user->regionId();
        $regionScoped = $user->mustStayInOwnRegion();

        $regionCount = Region::query()->count();
        $siteQuery = Site::query()->active();
        $guardQuery = Guard::query()->activeEmployment();
        $deploymentQuery = Deployment::query()->where('status', DeploymentStatus::Active);

        if ($regionScoped) {
            $siteQuery->where('region_id', $regionId);
            $guardQuery->where('region_id', $regionId);
            $deploymentQuery->where('region_id', $regionId);
        }

        $siteCount = $siteQuery->count();
        $clientCount = Client::query()->count();
        $supervisorCount = Supervisor::query()->active()->count();
        $activeGuards = $guardQuery->count();
        $activeDeployments = $deploymentQuery->count();
        $onLeave = Guard::query()
            ->when($regionScoped, fn ($q) => $q->where('region_id', $regionId))
            ->where('operational_status', OperationalStatus::OnLeave)
            ->count();
        $absent = Guard::query()
            ->when($regionScoped, fn ($q) => $q->where('region_id', $regionId))
            ->where('operational_status', OperationalStatus::Absent)
            ->count();

        return match ($user->role) {
            UserRole::SuperAdmin => [
                ['label' => 'Regions', 'value' => (string) $regionCount, 'hint' => 'Configured operating regions', 'tone' => 'brand'],
                ['label' => 'Active Sites', 'value' => (string) $siteCount, 'hint' => 'Live security sites', 'tone' => 'indigo'],
                ['label' => 'Active Guards', 'value' => (string) $activeGuards, 'hint' => 'Employment active', 'tone' => 'emerald'],
                ['label' => 'Clients', 'value' => (string) $clientCount, 'hint' => 'Registered clients', 'tone' => 'amber'],
            ],
            UserRole::ManagingDirector => [
                ['label' => 'Active Sites', 'value' => (string) $siteCount, 'hint' => 'Operational sites', 'tone' => 'brand'],
                ['label' => 'Deployed', 'value' => (string) $activeDeployments, 'hint' => 'Guards on active postings', 'tone' => 'emerald'],
                ['label' => 'Outstanding', 'value' => Money::format(self::financeTotals()['outstanding']), 'hint' => 'Open invoice balance', 'tone' => 'amber'],
                ['label' => 'Overdue', 'value' => (string) self::financeTotals()['overdue_count'], 'hint' => 'Past-due invoices', 'tone' => 'rose'],
            ],
            UserRole::OperationsManager => [
                ['label' => 'Active Sites', 'value' => (string) $siteCount, 'hint' => 'Operational sites', 'tone' => 'brand'],
                ['label' => 'Active Guards', 'value' => (string) $activeGuards, 'hint' => 'Available workforce', 'tone' => 'emerald'],
                ['label' => 'Supervisors', 'value' => (string) $supervisorCount, 'hint' => 'Field leadership', 'tone' => 'indigo'],
                ['label' => 'Clients', 'value' => (string) $clientCount, 'hint' => 'Active client base', 'tone' => 'sky'],
            ],
            UserRole::HrManager => [
                ['label' => 'Active Guards', 'value' => (string) $activeGuards, 'hint' => 'Employment active', 'tone' => 'brand'],
                ['label' => 'Sites', 'value' => (string) $siteCount, 'hint' => 'Deployment locations', 'tone' => 'indigo'],
                ['label' => 'On Leave', 'value' => (string) $onLeave, 'hint' => 'Currently on leave', 'tone' => 'sky'],
                ['label' => 'Absent', 'value' => (string) $absent, 'hint' => 'Recorded absences', 'tone' => 'amber'],
            ],
            UserRole::ShiftManager => [
                ['label' => 'Active Sites', 'value' => (string) $siteCount, 'hint' => 'Ready for scheduling', 'tone' => 'brand'],
                ['label' => 'Active Guards', 'value' => (string) $activeGuards, 'hint' => 'Employment active', 'tone' => 'emerald'],
                ['label' => 'On Leave', 'value' => (string) $onLeave, 'hint' => 'Unavailable for shifts', 'tone' => 'sky'],
                ['label' => 'Absent', 'value' => (string) $absent, 'hint' => 'Needs replacement review', 'tone' => 'amber'],
            ],
            UserRole::FinanceManager => [
                ['label' => 'Clients', 'value' => (string) $clientCount, 'hint' => 'Billing accounts', 'tone' => 'brand'],
                ['label' => 'Outstanding', 'value' => Money::format(self::financeTotals()['outstanding']), 'hint' => 'Open invoice balance', 'tone' => 'amber'],
                ['label' => 'Month collected', 'value' => Money::format(self::financeTotals()['month_collected']), 'hint' => 'Payments this month', 'tone' => 'emerald'],
                ['label' => 'Overdue', 'value' => (string) self::financeTotals()['overdue_count'], 'hint' => 'Past-due invoices', 'tone' => 'rose'],
            ],
            UserRole::RegionSupervisor => [
                ['label' => 'Sites', 'value' => (string) $siteCount, 'hint' => 'Sites in your region', 'tone' => 'brand'],
                ['label' => 'Deployed', 'value' => (string) $activeDeployments, 'hint' => 'Active deployments', 'tone' => 'emerald'],
                ['label' => 'Guards', 'value' => (string) $activeGuards, 'hint' => 'Active employment in region', 'tone' => 'sky'],
                ['label' => 'Absent', 'value' => (string) $absent, 'hint' => 'Needs field follow-up', 'tone' => 'amber'],
            ],
            default => [],
        };
    }

    /** @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}> */
    private static function superAdmin(): array
    {
        return [
            self::nav('Dashboard', 'home', route('dashboard'), 'dashboard'),
            self::nav('Ops Dashboard', 'chart', route('ops-dashboards.company'), 'ops-dashboards.*'),
            self::nav('Organization', 'building', route('organization.index'), 'organization.*|regions.*|supervisors.*|clients.*|sites.*|manpower.*', [
                ['label' => 'Overview', 'href' => route('organization.index')],
                ['label' => 'Regions', 'href' => route('regions.index')],
                ['label' => 'Supervisors', 'href' => route('supervisors.index')],
                ['label' => 'Clients', 'href' => route('clients.index')],
                ['label' => 'Sites', 'href' => route('sites.index')],
                ['label' => 'Manpower Coverage', 'href' => route('manpower.coverage')],
            ]),
            self::nav('Administration', 'settings', route('users.index'), 'users.*|roles.*|audit.*|archived.*|backups.*|settings.*', [
                ['label' => 'Users', 'href' => route('users.index')],
                ['label' => 'Roles & Permissions', 'href' => route('roles.index')],
                ['label' => 'Audit Logs', 'href' => route('audit.index')],
                ['label' => 'Archived Records', 'href' => route('archived.index')],
                ['label' => 'Database Backups', 'href' => route('backups.index')],
                ['label' => 'Platform Settings', 'href' => route('settings.index')],
                ['label' => 'Bulk Import / Export', 'href' => route('data-import.index')],
            ]),
            self::nav('Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Staff', 'users', route('staff.index'), 'staff.*'),
            self::nav('Operations', 'ops', route('deployments.index'), 'deployments.*|shifts.*|replacements.*', [
                ['label' => 'Site posting board', 'href' => route('deployments.board')],
                ['label' => 'Site postings', 'href' => route('deployments.index')],
                ['label' => 'Duty roster', 'href' => route('shifts.allocate')],
                ['label' => 'Duty register', 'href' => route('shifts.index')],
                ['label' => 'Calendar', 'href' => route('shifts.calendar')],
                ['label' => 'Leave', 'href' => route('leaves.index')],
                ['label' => 'Absences', 'href' => route('absences.index')],
                ['label' => 'Occurrence book', 'href' => route('incidents.index')],
                ['label' => 'Replacements', 'href' => route('replacements.index')],
            ]),
            self::nav('Finance', 'wallet', route('billing.index'), 'billing.*|invoices.*|payments.*|profitability.*|payroll.*|advances.*|ledger.*', [
                ['label' => 'Client Billing', 'href' => route('billing.index')],
                ['label' => 'Invoices', 'href' => route('invoices.index')],
                ['label' => 'Payments', 'href' => route('payments.index')],
                ['label' => 'Profitability', 'href' => route('profitability.index')],
                ['label' => 'Payroll', 'href' => route('payroll.index')],
                ['label' => 'Advances', 'href' => route('advances.index')],
                ['label' => 'General ledger', 'href' => route('ledger.index')],
                ['label' => 'Purchases', 'href' => route('ledger.purchases.index')],
            ]),
            self::nav('Reports', 'chart', route('reports.index'), 'reports.*'),
        ];
    }

    /** @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}> */
    private static function managingDirector(): array
    {
        return [
            self::nav('Dashboard', 'home', route('dashboard'), 'dashboard'),
            self::nav('Ops Dashboard', 'chart', route('ops-dashboards.company'), 'ops-dashboards.*'),
            self::nav('Organization', 'building', route('organization.index'), 'organization.*|regions.*|supervisors.*|clients.*|sites.*|manpower.*', [
                ['label' => 'Overview', 'href' => route('organization.index')],
                ['label' => 'Regions', 'href' => route('regions.index')],
                ['label' => 'Supervisors', 'href' => route('supervisors.index')],
                ['label' => 'Clients', 'href' => route('clients.index')],
                ['label' => 'Sites', 'href' => route('sites.index')],
                ['label' => 'Manpower Coverage', 'href' => route('manpower.coverage')],
            ]),
            self::nav('Administration', 'settings', route('users.index'), 'users.*|audit.*', [
                ['label' => 'Users', 'href' => route('users.index')],
                ['label' => 'Audit Logs', 'href' => route('audit.index')],
            ]),
            self::nav('Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Staff', 'users', route('staff.index'), 'staff.*'),
            self::nav('Operations', 'ops', route('deployments.index'), 'deployments.*|shifts.*|replacements.*', [
                ['label' => 'Site posting board', 'href' => route('deployments.board')],
                ['label' => 'Site postings', 'href' => route('deployments.index')],
                ['label' => 'Duty roster', 'href' => route('shifts.allocate')],
                ['label' => 'Duty register', 'href' => route('shifts.index')],
                ['label' => 'Calendar', 'href' => route('shifts.calendar')],
                ['label' => 'Leave', 'href' => route('leaves.index')],
                ['label' => 'Absences', 'href' => route('absences.index')],
                ['label' => 'Desertions', 'href' => route('desertions.index')],
                ['label' => 'Occurrence book', 'href' => route('incidents.index')],
                ['label' => 'Attendance', 'href' => route('attendances.index')],
                ['label' => 'Replacements', 'href' => route('replacements.index')],
            ]),
            self::nav('Finance', 'wallet', route('billing.index'), 'billing.*|invoices.*|payments.*|profitability.*|payroll.*|advances.*|ledger.*', [
                ['label' => 'Client Billing', 'href' => route('billing.index')],
                ['label' => 'Invoices', 'href' => route('invoices.index')],
                ['label' => 'Payments', 'href' => route('payments.index')],
                ['label' => 'Profitability', 'href' => route('profitability.index')],
                ['label' => 'Payroll', 'href' => route('payroll.index')],
                ['label' => 'Advances', 'href' => route('advances.index')],
                ['label' => 'General ledger', 'href' => route('ledger.index')],
                ['label' => 'Purchases', 'href' => route('ledger.purchases.index')],
            ]),
            self::nav('Reports', 'chart', route('reports.index'), 'reports.*'),
        ];
    }

    /** @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}> */
    private static function operations(): array
    {
        return [
            self::nav('Dashboard', 'home', route('dashboard'), 'dashboard'),
            self::nav('Ops Dashboard', 'chart', route('ops-dashboards.company'), 'ops-dashboards.*'),
            self::nav('Audit Logs', 'audit', route('audit.index'), 'audit.*'),
            self::nav('Organization', 'building', route('organization.index'), 'organization.*|regions.*|supervisors.*|clients.*|sites.*|manpower.*', [
                ['label' => 'Overview', 'href' => route('organization.index')],
                ['label' => 'Regions', 'href' => route('regions.index')],
                ['label' => 'Supervisors', 'href' => route('supervisors.index')],
                ['label' => 'Sites', 'href' => route('sites.index')],
                ['label' => 'Clients', 'href' => route('clients.index')],
                ['label' => 'Manpower Coverage', 'href' => route('manpower.coverage')],
            ]),
            self::nav('Duty Register', 'calendar', route('shifts.index'), 'shifts.*'),
            self::nav('Duty Roster', 'plus', route('shifts.allocate'), 'shifts.allocate*'),
            self::nav('Site Postings', 'map', route('deployments.index'), 'deployments.index|deployments.show|deployments.transfer*|deployments.end|deployments.edit|deployments.update'),
            self::nav('Posting Board', 'ops', route('deployments.board'), 'deployments.board*|deployments.create|deployments.store'),
            self::nav('Leave', 'leave', route('leaves.index'), 'leaves.*'),
            self::nav('Occurrence Book', 'report', route('incidents.index'), 'incidents.*'),
            self::nav('Work orders', 'report', route('work-orders.index'), 'work-orders.*'),
            self::nav('Absences', 'alert', route('absences.index'), 'absences.*'),
            self::nav('Assets & uniforms', 'shield', route('assets.index'), 'assets.*'),
            self::nav('Replacements', 'swap', route('replacements.index'), 'replacements.*'),
            self::nav('Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Reports', 'report', route('reports.index'), 'reports.*'),
        ];
    }

    /** @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}> */
    private static function hr(): array
    {
        return [
            self::nav('Dashboard', 'home', route('dashboard'), 'dashboard'),
            self::nav('All Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Staff', 'users', route('staff.index'), 'staff.*'),
            self::nav('Ops Dashboard', 'chart', route('ops-dashboards.company'), 'ops-dashboards.*'),
            self::nav('Deployments', 'map', route('deployments.index'), 'deployments.*'),
            self::nav('Organization', 'building', route('organization.index'), 'organization.*|regions.*|supervisors.*|clients.*|sites.*', [
                ['label' => 'Overview', 'href' => route('organization.index')],
                ['label' => 'Regions', 'href' => route('regions.index')],
                ['label' => 'Sites', 'href' => route('sites.index')],
                ['label' => 'Supervisors', 'href' => route('supervisors.index')],
            ]),
            self::nav('Leave', 'leave', route('leaves.index'), 'leaves.*'),
            self::nav('Absence', 'alert', route('absences.index'), 'absences.*'),
            self::nav('Assets & uniforms', 'shield', route('assets.index'), 'assets.*'),
            self::nav('Desertion', 'warning', route('desertions.index'), 'desertions.*'),
            self::nav('Occurrence Book', 'report', route('incidents.index'), 'incidents.*'),
            self::nav('Work orders', 'report', route('work-orders.index'), 'work-orders.*'),
            self::nav('Attendance', 'calendar', route('attendances.index'), 'attendances.*'),
            self::nav('HR Reports', 'report', route('reports.hr'), 'reports.*'),
        ];
    }

    /** @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}> */
    private static function shift(): array
    {
        return [
            self::nav('Dashboard', 'home', route('dashboard'), 'dashboard'),
            self::nav('Ops Dashboard', 'chart', route('ops-dashboards.company'), 'ops-dashboards.*'),
            self::nav('Duty Register', 'calendar', route('shifts.index'), 'shifts.index|shifts.show|shifts.calendar|shifts.status'),
            self::nav('Duty Roster', 'plus', route('shifts.allocate'), 'shifts.allocate|shifts.allocate.store|shifts.create|shifts.store|shifts.recurring.*|shifts.edit|shifts.update'),
            self::nav('Site Postings', 'map', route('deployments.index'), 'deployments.index|deployments.show|deployments.transfer*|deployments.end|deployments.edit|deployments.update'),
            self::nav('Posting Board', 'ops', route('deployments.board'), 'deployments.board|deployments.board.store|deployments.create|deployments.store'),
            self::nav('Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Organization', 'building', route('organization.index'), 'organization.*|regions.*|supervisors.*|clients.*|sites.*|manpower.*', [
                ['label' => 'Sites', 'href' => route('sites.index')],
                ['label' => 'Manpower Coverage', 'href' => route('manpower.coverage')],
                ['label' => 'Regions', 'href' => route('regions.index')],
                ['label' => 'Supervisors', 'href' => route('supervisors.index')],
            ]),
            self::nav('Replacements', 'swap', route('replacements.index'), 'replacements.*'),
            self::nav('Absences', 'alert', route('absences.index'), 'absences.*'),
            self::nav('Occurrence Book', 'report', route('incidents.index'), 'incidents.*'),
            self::nav('Work orders', 'report', route('work-orders.index'), 'work-orders.*'),
            self::nav('Desertions', 'warning', route('desertions.index'), 'desertions.*'),
            self::nav('Shift Reports', 'report', route('reports.index'), 'reports.*'),
        ];
    }

    /** @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}> */
    private static function finance(): array
    {
        return [
            self::nav('Dashboard', 'home', route('dashboard'), 'dashboard'),
            self::nav('Ops Dashboard', 'chart', route('ops-dashboards.company'), 'ops-dashboards.*'),
            self::nav('Organization', 'building', route('organization.index'), 'organization.*|regions.*|clients.*|sites.*', [
                ['label' => 'Overview', 'href' => route('organization.index')],
                ['label' => 'Clients', 'href' => route('clients.index')],
                ['label' => 'Sites', 'href' => route('sites.index')],
                ['label' => 'Regions', 'href' => route('regions.index')],
            ]),
            self::nav('Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Assets & uniforms', 'shield', route('assets.index'), 'assets.*'),
            self::nav('Staff', 'users', route('staff.index'), 'staff.*'),
            self::nav('Deployments', 'map', route('deployments.index'), 'deployments.*'),
            self::nav('Client Billing', 'wallet', route('billing.index'), 'billing.*'),
            self::nav('Invoices', 'invoice', route('invoices.index'), 'invoices.*'),
            self::nav('Payments', 'payment', route('payments.index'), 'payments.*'),
            self::nav('Profitability', 'chart', route('profitability.index'), 'profitability.*'),
            self::nav('Payroll', 'payroll', route('payroll.index'), 'payroll.*'),
            self::nav('Advances', 'wallet', route('advances.index'), 'advances.*'),
            self::nav('General ledger', 'wallet', route('ledger.index'), 'ledger.*'),
            self::nav('Shift Exports', 'report', route('reports.monthly-shifts'), 'reports.*'),
        ];
    }

    /** @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}> */
    private static function regionSupervisor(): array
    {
        return [
            self::nav('Dashboard', 'home', route('dashboard'), 'dashboard'),
            self::nav('Ops Dashboard', 'chart', route('ops-dashboards.company'), 'ops-dashboards.*'),
            self::nav('Site Postings', 'map', route('deployments.index'), 'deployments.index|deployments.show|deployments.transfer*|deployments.end|deployments.edit|deployments.update'),
            self::nav('Posting Board', 'ops', route('deployments.board'), 'deployments.board*|deployments.create|deployments.store'),
            self::nav('Absences', 'alert', route('absences.index'), 'absences.*'),
            self::nav('Occurrence Book', 'report', route('incidents.index'), 'incidents.*'),
            self::nav('Work orders', 'report', route('work-orders.index'), 'work-orders.*'),
            self::nav('Desertions', 'warning', route('desertions.index'), 'desertions.*'),
            self::nav('Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Sites', 'building', route('sites.index'), 'sites.*|manpower.*', [
                ['label' => 'Sites', 'href' => route('sites.index')],
                ['label' => 'Manpower Coverage', 'href' => route('manpower.coverage')],
            ]),
            self::nav('Duty Register', 'calendar', route('shifts.index'), 'shifts.index|shifts.show|shifts.calendar'),
        ];
    }

    /** @return array<string, float|int> */
    private static function financeTotals(): array
    {
        static $cache = null;

        if ($cache === null) {
            try {
                $cache = app(ProfitabilityService::class)->dashboardTotals();
            } catch (\Throwable) {
                $cache = [
                    'outstanding' => 0,
                    'month_collected' => 0,
                    'overdue_count' => 0,
                ];
            }
        }

        return $cache;
    }

    /** @return list<array{label: string, icon: string, href: string, active: bool}> */
    private static function fallback(): array
    {
        return [
            self::nav('Dashboard', 'home', route('dashboard'), 'dashboard'),
        ];
    }

    /**
     * @param  list<array{label: string, href: string}>|null  $children
     * @return array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active: bool}>}
     */
    private static function nav(string $label, string $icon, string $href, ?string $routePattern = null, ?array $children = null): array
    {
        $childItems = null;
        $childActive = false;

        if ($children !== null) {
            $childItems = array_map(function (array $child) use (&$childActive) {
                $active = $child['href'] !== '#' && (
                    request()->url() === $child['href']
                    || str_starts_with(request()->url(), rtrim($child['href'], '/').'/')
                );
                $childActive = $childActive || $active;

                return [
                    'label' => $child['label'],
                    'href' => $child['href'],
                    'active' => $active,
                ];
            }, $children);
        }

        $active = $childActive;
        if (! $active && $routePattern) {
            $patterns = explode('|', $routePattern);
            $active = request()->routeIs(...$patterns);
        } elseif (! $active && $href !== '#') {
            $active = request()->url() === $href;
        }

        $item = [
            'label' => $label,
            'icon' => $icon,
            'href' => $href,
            'active' => $active,
            'route_pattern' => $routePattern,
        ];

        if ($childItems !== null) {
            $item['children'] = $childItems;
        }

        return $item;
    }

    /**
     * @return array{title: string, description: string, icon: string, href: string, badge: string|null, tone: string}
     */
    private static function module(string $title, string $description, string $icon, string $tone, ?string $href = null): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'icon' => $icon,
            'href' => $href ?? '#',
            'badge' => $href ? null : 'Coming soon',
            'tone' => $tone,
        ];
    }
}
