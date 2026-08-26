<?php

namespace App\Support\Navigation;

use App\Enums\UserRole;
use App\Models\User;

class RoleNavigation
{
    /**
     * @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active?: bool}>}>
     */
    public static function for(User $user): array
    {
        return match ($user->role) {
            UserRole::SuperAdmin => self::superAdmin(),
            UserRole::OperationsManager => self::operations(),
            UserRole::HrManager => self::hr(),
            UserRole::ShiftManager => self::shift(),
            UserRole::FinanceManager => self::finance(),
            default => self::fallback(),
        };
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
                self::module('Deployments', 'Active deployments, transfers and replacements.', 'map', 'emerald', route('deployments.index')),
                self::module('Shifts', 'Schedules, calendar and shift validation oversight.', 'calendar', 'amber', route('shifts.index')),
                self::module('Replacements', 'Link original shifts to covering guards.', 'swap', 'indigo', route('replacements.index')),
                self::module('Leave', 'Leave requests, approvals and conflict handling.', 'leave', 'sky', route('leaves.index')),
                self::module('Absences', 'Daily absence recording and follow-up.', 'alert', 'amber', route('absences.index')),
                self::module('Reports', 'Operational, HR and financial report exports.', 'chart', 'violet', route('reports.index')),
                self::module('Audit Logs', 'Immutable trail of critical system actions.', 'audit', 'rose', route('audit.index')),
                self::module('System Settings', 'Company profile, finance defaults, shift times and backups.', 'settings', 'violet', route('settings.index')),
            ],
            UserRole::OperationsManager => [
                self::module('Ops Dashboards', 'Company and regional operational command views.', 'chart', 'brand', route('ops-dashboards.company')),
                self::module('Organization', 'Regions, supervisors, clients and sites.', 'building', 'indigo', route('organization.index')),
                self::module('Manpower Coverage', 'Site staffing levels, shortages and surplus.', 'chart', 'amber', route('manpower.coverage')),
                self::module('Guards', 'Review employment and operational guard status.', 'shield', 'sky', route('guards.index')),
                self::module('Sites', 'Security sites and manpower requirements.', 'map', 'brand', route('sites.index')),
                self::module('Today\'s Shifts', 'Monitor scheduled, in-progress and missed shifts.', 'calendar', 'brand', route('shifts.index')),
                self::module('Deployments', 'Guard-to-site assignments across regions.', 'map', 'emerald', route('deployments.index')),
                self::module('Replacements', 'Track original vs covering guards on duty.', 'swap', 'indigo', route('replacements.index')),
                self::module('Operational Reports', 'Shift, overtime and coverage reports.', 'report', 'violet', route('reports.index')),
                self::module('Audit Logs', 'Overrides and critical operational events.', 'audit', 'rose', route('audit.index')),
            ],
            UserRole::HrManager => [
                self::module('All Guards', 'Register and maintain guard employment records.', 'shield', 'brand', route('guards.index')),
                self::module('Organization', 'View regions, sites and supervisors.', 'building', 'indigo', route('organization.index')),
                self::module('Leave Management', 'Approve leave and detect schedule conflicts.', 'leave', 'sky', route('leaves.index')),
                self::module('Absences', 'Record absences and trigger replacements.', 'alert', 'amber', route('absences.index')),
                self::module('Desertions', 'Track deserted guards and HR follow-up.', 'warning', 'rose', route('desertions.index')),
                self::module('Attendance', 'Manual attendance events with status history preserved.', 'calendar', 'brand', route('attendances.index')),
                self::module('HR Reports', 'Employment, leave, absence and work summaries.', 'report', 'violet', route('reports.hr')),
            ],
            UserRole::ShiftManager => [
                self::module('Create Shifts', 'Fast shift entry with validation and conflict checks.', 'plus', 'brand', route('shifts.create')),
                self::module('Guards', 'Check availability and operational status.', 'shield', 'sky', route('guards.index')),
                self::module('Sites & Manpower', 'Review site requirements before scheduling.', 'chart', 'amber', route('manpower.coverage')),
                self::module('Organization', 'Regions, supervisors and site structure.', 'building', 'indigo', route('organization.index')),
                self::module('Deployments', 'Assign guards to sites before scheduling.', 'map', 'emerald', route('deployments.index')),
                self::module('Today\'s Shifts', 'Monitor and update the live schedule.', 'calendar', 'sky', route('shifts.index')),
                self::module('Replacements', 'Cover unavailable guards and keep reports accurate.', 'swap', 'indigo', route('replacements.index')),
                self::module('Shift Reports', 'Daily and monthly shift and overtime exports.', 'report', 'violet', route('reports.index')),
            ],
            UserRole::FinanceManager => [
                self::module('Clients & Sites', 'Contracts and site structure for billing context.', 'building', 'indigo', route('organization.index')),
                self::module('Guards', 'Read-only employment context for payroll reporting.', 'shield', 'sky', route('guards.index')),
                self::module('Client Billing', 'Contracts, billing rates and client revenue.', 'wallet', 'brand', route('billing.index')),
                self::module('Invoices', 'Create, approve and track client invoices.', 'invoice', 'indigo', route('invoices.index')),
                self::module('Payments', 'Record payments and outstanding balances.', 'payment', 'emerald', route('payments.index')),
                self::module('Profitability', 'Client, site and region profitability analysis.', 'chart', 'violet', route('profitability.index')),
                self::module('Monthly Shift Exports', 'Payroll-ready normal and overtime shift totals.', 'report', 'sky', route('reports.monthly-shifts')),
            ],
            default => [],
        };
    }

    /**
     * @return list<array{label: string, value: string, hint: string, tone: string}>
     */
    public static function kpis(User $user): array
    {
        $regionCount = \App\Models\Region::query()->count();
        $siteCount = \App\Models\Site::query()->active()->count();
        $clientCount = \App\Models\Client::query()->count();
        $supervisorCount = \App\Models\Supervisor::query()->active()->count();
        $activeGuards = \App\Models\Guard::query()->activeEmployment()->count();
        $onLeave = \App\Models\Guard::query()->where('operational_status', \App\Enums\OperationalStatus::OnLeave)->count();
        $absent = \App\Models\Guard::query()->where('operational_status', \App\Enums\OperationalStatus::Absent)->count();

        return match ($user->role) {
            UserRole::SuperAdmin => [
                ['label' => 'Regions', 'value' => (string) $regionCount, 'hint' => 'Configured operating regions', 'tone' => 'brand'],
                ['label' => 'Active Sites', 'value' => (string) $siteCount, 'hint' => 'Live security sites', 'tone' => 'indigo'],
                ['label' => 'Active Guards', 'value' => (string) $activeGuards, 'hint' => 'Employment active', 'tone' => 'emerald'],
                ['label' => 'Clients', 'value' => (string) $clientCount, 'hint' => 'Registered clients', 'tone' => 'amber'],
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
                ['label' => 'Outstanding', 'value' => \App\Support\Money::format(self::financeTotals()['outstanding']), 'hint' => 'Open invoice balance', 'tone' => 'amber'],
                ['label' => 'Month collected', 'value' => \App\Support\Money::format(self::financeTotals()['month_collected']), 'hint' => 'Payments this month', 'tone' => 'emerald'],
                ['label' => 'Overdue', 'value' => (string) self::financeTotals()['overdue_count'], 'hint' => 'Past-due invoices', 'tone' => 'rose'],
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
            self::nav('Administration', 'settings', route('users.index'), 'users.*|roles.*|audit.*|settings.*', [
                ['label' => 'Users', 'href' => route('users.index')],
                ['label' => 'Roles & Permissions', 'href' => route('roles.index')],
                ['label' => 'Audit Logs', 'href' => route('audit.index')],
                ['label' => 'System Settings', 'href' => route('settings.index')],
            ]),
            self::nav('Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Operations', 'ops', route('deployments.index'), 'deployments.*|shifts.*|replacements.*', [
                ['label' => 'Deployments', 'href' => route('deployments.index')],
                ['label' => 'Shifts', 'href' => route('shifts.index')],
                ['label' => 'Calendar', 'href' => route('shifts.calendar')],
                ['label' => 'Leave', 'href' => route('leaves.index')],
                ['label' => 'Absences', 'href' => route('absences.index')],
                ['label' => 'Replacements', 'href' => route('replacements.index')],
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
            self::nav('Today\'s Shifts', 'calendar', route('shifts.index'), 'shifts.*'),
            self::nav('Deployments', 'map', route('deployments.index'), 'deployments.*'),
            self::nav('Leave', 'leave', route('leaves.index'), 'leaves.*'),
            self::nav('Absences', 'alert', route('absences.index'), 'absences.*'),
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
            self::nav('Desertion', 'warning', route('desertions.index'), 'desertions.*'),
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
            self::nav('Today\'s Shifts', 'calendar', route('shifts.index'), 'shifts.index|shifts.show|shifts.calendar'),
            self::nav('Create Shift', 'plus', route('shifts.create'), 'shifts.create|shifts.store|shifts.recurring.*|shifts.edit|shifts.update'),
            self::nav('Guards', 'shield', route('guards.index'), 'guards.*'),
            self::nav('Deployments', 'map', route('deployments.index'), 'deployments.*'),
            self::nav('Organization', 'building', route('organization.index'), 'organization.*|regions.*|supervisors.*|clients.*|sites.*|manpower.*', [
                ['label' => 'Sites', 'href' => route('sites.index')],
                ['label' => 'Manpower Coverage', 'href' => route('manpower.coverage')],
                ['label' => 'Regions', 'href' => route('regions.index')],
                ['label' => 'Supervisors', 'href' => route('supervisors.index')],
            ]),
            self::nav('Replacements', 'swap', route('replacements.index'), 'replacements.*'),
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
            self::nav('Deployments', 'map', route('deployments.index'), 'deployments.*'),
            self::nav('Client Billing', 'wallet', route('billing.index'), 'billing.*'),
            self::nav('Invoices', 'invoice', route('invoices.index'), 'invoices.*'),
            self::nav('Payments', 'payment', route('payments.index'), 'payments.*'),
            self::nav('Profitability', 'chart', route('profitability.index'), 'profitability.*'),
            self::nav('Shift Exports', 'report', route('reports.monthly-shifts'), 'reports.*'),
        ];
    }

    /** @return array<string, float|int> */
    private static function financeTotals(): array
    {
        static $cache = null;

        if ($cache === null) {
            try {
                $cache = app(\App\Services\Finance\ProfitabilityService::class)->dashboardTotals();
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
