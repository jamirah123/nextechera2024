<?php

namespace App\Support\Access;

use App\Enums\UserRole;

class RoleAccessMatrix
{
    /**
     * Capability catalog for the Roles & Permissions reference screen.
     * Enforcement remains in policies/gates — this is documentation only.
     *
     * @return list<array{group: string, capability: string, roles: list<string>}>
     */
    public static function rows(): array
    {
        $all = UserRole::values();
        $ops = [
            UserRole::SuperAdmin->value,
            UserRole::OperationsManager->value,
            UserRole::ShiftManager->value,
        ];
        $hrOps = [
            UserRole::SuperAdmin->value,
            UserRole::OperationsManager->value,
            UserRole::HrManager->value,
            UserRole::ShiftManager->value,
        ];
        $hrCore = [
            UserRole::SuperAdmin->value,
            UserRole::HrManager->value,
        ];
        $admin = [UserRole::SuperAdmin->value];
        $audit = [
            UserRole::SuperAdmin->value,
            UserRole::OperationsManager->value,
        ];

        return [
            ['group' => 'Administration', 'capability' => 'Manage users & account access', 'roles' => $admin],
            ['group' => 'Administration', 'capability' => 'View roles & permission matrix', 'roles' => $admin],
            ['group' => 'Administration', 'capability' => 'Manage system settings & backups', 'roles' => $admin],
            ['group' => 'Administration', 'capability' => 'View audit logs', 'roles' => $audit],
            ['group' => 'Organization', 'capability' => 'Manage regions, sites, clients, supervisors', 'roles' => [
                UserRole::SuperAdmin->value,
                UserRole::OperationsManager->value,
            ]],
            ['group' => 'Organization', 'capability' => 'View organization structure', 'roles' => $all],
            ['group' => 'Guards', 'capability' => 'Register / edit guards', 'roles' => $hrCore],
            ['group' => 'Guards', 'capability' => 'View guard registry', 'roles' => $all],
            ['group' => 'Operations', 'capability' => 'Manage deployments & transfers', 'roles' => $ops],
            ['group' => 'Operations', 'capability' => 'Create & edit shifts', 'roles' => $ops],
            ['group' => 'Operations', 'capability' => 'Authorize critical shift overrides', 'roles' => $audit],
            ['group' => 'Operations', 'capability' => 'Record replacements', 'roles' => $ops],
            ['group' => 'HR', 'capability' => 'Approve / reject leave', 'roles' => $hrCore],
            ['group' => 'HR', 'capability' => 'Record absences & attendance', 'roles' => $hrOps],
            ['group' => 'HR', 'capability' => 'Manage desertions', 'roles' => [
                UserRole::SuperAdmin->value,
                UserRole::OperationsManager->value,
                UserRole::HrManager->value,
            ]],
            ['group' => 'Reporting', 'capability' => 'View & export operational / HR reports', 'roles' => $all],
            ['group' => 'Dashboards', 'capability' => 'View operational dashboards', 'roles' => $all],
        ];
    }
}
