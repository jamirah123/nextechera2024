<?php

namespace App\Support\Navigation;

use App\Models\User;

class NavigationAccess
{
    /** @var array<string, string> */
    private const ROUTE_PERMISSIONS = [
        'dashboard' => 'dashboards.operational_view',
        'ops-dashboards.company' => 'dashboards.operational_view',
        'ops-dashboards.region' => 'dashboards.operational_view',
        'ops-dashboards.site' => 'dashboards.operational_view',
        'ops-dashboards.guard' => 'dashboards.operational_view',
        'organization.index' => 'organization.view',
        'regions.index' => 'organization.view',
        'supervisors.index' => 'organization.view',
        'clients.index' => 'organization.view',
        'sites.index' => 'organization.view',
        'manpower.coverage' => 'organization.view',
        'users.index' => 'admin.users_manage',
        'roles.index' => 'admin.roles_manage',
        'audit.index' => 'admin.audit_view',
        'archived.index' => 'admin.records_restore',
        'settings.index' => 'admin.settings_manage',
        'data-import.index' => 'admin.data_import',
        'guards.index' => 'guards.view',
        'staff.index' => 'staff.view',
        'deployments.index' => 'organization.view',
        'deployments.board' => 'operations.deploy_board',
        'deployments.create' => 'operations.deployments_manage',
        'shifts.index' => 'organization.view',
        'shifts.calendar' => 'organization.view',
        'shifts.allocate' => 'operations.shifts_manage',
        'shifts.create' => 'operations.shifts_manage',
        'replacements.index' => 'organization.view',
        'leaves.index' => 'organization.view',
        'absences.index' => 'organization.view',
        'assets.index' => 'organization.view',
        'desertions.index' => 'organization.view',
        'incidents.index' => 'organization.view',
        'work-orders.index' => 'organization.view',
        'attendances.index' => 'organization.view',
        'billing.index' => 'finance.view',
        'invoices.index' => 'finance.view',
        'payments.index' => 'finance.view',
        'profitability.index' => 'finance.view',
        'payroll.index' => 'finance.view',
        'advances.index' => 'finance.view',
        'ledger.index' => 'finance.view',
        'ledger.accounts.index' => 'finance.view',
        'ledger.journals.index' => 'finance.view',
        'ledger.periods.index' => 'finance.view',
        'ledger.vat.index' => 'finance.view',
        'ledger.bank.index' => 'finance.view',
        'reports.index' => 'reporting.view_export',
        'reports.hr' => 'reporting.view_export',
        'reports.monthly-shifts' => 'reporting.view_export',
    ];

    /** @var array<string, list<string>> */
    private const PATTERN_PERMISSIONS = [
        'dashboard' => ['dashboards.operational_view'],
        'ops-dashboards.*' => ['dashboards.operational_view'],
        'organization.*' => ['organization.view'],
        'regions.*' => ['organization.view'],
        'supervisors.*' => ['organization.view'],
        'clients.*' => ['organization.view'],
        'sites.*' => ['organization.view'],
        'manpower.*' => ['organization.view'],
        'users.*' => ['admin.users_manage'],
        'roles.*' => ['admin.roles_manage'],
        'audit.*' => ['admin.audit_view'],
        'archived.*' => ['admin.records_restore'],
        'settings.*' => ['admin.settings_manage'],
        'data-import.*' => ['admin.data_import'],
        'guards.*' => ['guards.view'],
        'staff.*' => ['staff.view'],
        'deployments.board*' => ['operations.deploy_board'],
        'deployments.create' => ['operations.deployments_manage'],
        'deployments.store' => ['operations.deployments_manage'],
        'deployments.index' => ['organization.view'],
        'deployments.show' => ['organization.view'],
        'deployments.edit' => ['operations.deployments_manage'],
        'deployments.update' => ['operations.deployments_manage'],
        'deployments.transfer*' => ['operations.deployments_manage'],
        'deployments.end' => ['operations.deployments_manage'],
        'deployments.*' => ['organization.view', 'operations.deployments_manage', 'operations.deploy_board'],
        'shifts.allocate*' => ['operations.shifts_manage'],
        'shifts.create' => ['operations.shifts_manage'],
        'shifts.store' => ['operations.shifts_manage'],
        'shifts.recurring.*' => ['operations.shifts_manage'],
        'shifts.edit' => ['operations.shifts_manage'],
        'shifts.update' => ['operations.shifts_manage'],
        'shifts.status' => ['operations.shifts_manage'],
        'shifts.*' => ['organization.view', 'operations.shifts_manage'],
        'replacements.*' => ['organization.view', 'operations.replacements_record'],
        'leaves.*' => ['organization.view', 'hr.leaves_manage', 'hr.leaves_approve'],
        'absences.*' => ['organization.view', 'hr.absences_attendance_record'],
        'assets.*' => ['organization.view', 'hr.assets_manage'],
        'desertions.*' => ['organization.view', 'hr.desertions_manage'],
        'incidents.*' => ['organization.view', 'operations.incidents_manage'],
        'work-orders.index' => 'organization.view',
        'work-orders.show' => 'organization.view',
        'work-orders.*' => ['organization.view', 'operations.work_orders_manage'],
        'attendances.*' => ['organization.view', 'hr.absences_attendance_record'],
        'billing.*' => ['finance.view'],
        'invoices.*' => ['finance.view'],
        'payments.*' => ['finance.view'],
        'profitability.*' => ['finance.view'],
        'payroll.*' => ['finance.view', 'finance.manage'],
        'advances.*' => ['finance.view'],
        'ledger.*' => ['finance.view', 'finance.manage'],
        'reports.*' => ['reporting.view_export'],
    ];

    /**
     * @param  list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active: bool}>}>  $items
     * @return list<array{label: string, icon: string, href: string, active: bool, children?: list<array{label: string, href: string, active: bool}>}>
     */
    public static function filter(User $user, array $items): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (! empty($item['children'])) {
                $children = array_values(array_filter(
                    $item['children'],
                    fn (array $child) => self::canSeeHref($user, $child['href']),
                ));

                if ($children === [] && ! self::canSeeItem($user, $item)) {
                    continue;
                }

                $item['children'] = $children;
                $item['active'] = ($item['active'] ?? false)
                    || collect($children)->contains(fn (array $child) => $child['active'] ?? false);

                if ($children !== [] || self::canSeeItem($user, $item)) {
                    $filtered[] = $item;
                }

                continue;
            }

            if (self::canSeeItem($user, $item)) {
                $filtered[] = $item;
            }
        }

        return $filtered;
    }

    /** @param  array{label: string, icon: string, href: string, active: bool, children?: mixed}  $item */
    private static function canSeeItem(User $user, array $item): bool
    {
        if (self::canSeeHref($user, $item['href'])) {
            return true;
        }

        $pattern = $item['route_pattern'] ?? null;

        return $pattern ? self::canSeePattern($user, $pattern) : true;
    }

    public static function canSeeHref(User $user, string $href): bool
    {
        if ($href === '#') {
            return false;
        }

        $routeName = self::routeNameFromHref($href);

        if ($routeName === null) {
            return true;
        }

        $permission = self::ROUTE_PERMISSIONS[$routeName] ?? null;

        if ($permission === null) {
            return self::canSeePattern($user, $routeName);
        }

        return \App\Support\Access\Access::userCan($user, $permission);
    }

    private static function canSeePattern(User $user, string $pattern): bool
    {
        foreach (explode('|', $pattern) as $segment) {
            $segment = trim($segment);

            foreach (self::permissionsForPattern($segment) as $permission) {
                if (\App\Support\Access\Access::userCan($user, $permission)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    private static function permissionsForPattern(string $pattern): array
    {
        if (isset(self::PATTERN_PERMISSIONS[$pattern])) {
            return self::PATTERN_PERMISSIONS[$pattern];
        }

        foreach (self::PATTERN_PERMISSIONS as $key => $permissions) {
            if (! str_contains($key, '*')) {
                continue;
            }

            $prefix = rtrim($key, '*');

            if (str_starts_with($pattern, $prefix)) {
                return $permissions;
            }
        }

        return [];
    }

    private static function routeNameFromHref(string $href): ?string
    {
        static $map = null;

        if ($map === null) {
            $map = [];

            foreach (array_keys(self::ROUTE_PERMISSIONS) as $routeName) {
                try {
                    $path = parse_url(route($routeName), PHP_URL_PATH) ?: route($routeName);
                    $map[rtrim($path, '/')] = $routeName;
                } catch (\Throwable) {
                    // Route may be unavailable during some CLI contexts.
                }
            }
        }

        $path = parse_url($href, PHP_URL_PATH) ?: $href;
        $path = rtrim($path, '/');

        return $map[$path] ?? null;
    }
}
