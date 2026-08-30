<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case OperationsManager = 'operations_manager';
    case HrManager = 'hr_manager';
    case ShiftManager = 'shift_manager';
    case FinanceManager = 'finance_manager';
    case RegionSupervisor = 'region_supervisor';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::OperationsManager => 'Operations Manager',
            self::HrManager => 'HR Manager',
            self::ShiftManager => 'Shift Manager',
            self::FinanceManager => 'Finance Manager',
            self::RegionSupervisor => 'Region Supervisor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Full system administration and configuration',
            self::OperationsManager => 'Company-wide operational oversight',
            self::HrManager => 'Guard employment and HR records',
            self::ShiftManager => 'Shift scheduling and deployments',
            self::FinanceManager => 'Billing, payments and financial reporting',
            self::RegionSupervisor => 'Field deployments, absences and desertions for an assigned region',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::SuperAdmin => 'rose',
            self::OperationsManager => 'brand',
            self::HrManager => 'sky',
            self::ShiftManager => 'amber',
            self::FinanceManager => 'emerald',
            self::RegionSupervisor => 'indigo',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function documentGuidance(): string
    {
        return match ($this) {
            self::SuperAdmin => 'System policies, admin authorizations, or compliance records.',
            self::OperationsManager => 'Operational authorizations, site access letters, or management certificates.',
            self::HrManager => 'HR certifications, employment contracts, or policy acknowledgements.',
            self::ShiftManager => 'Scheduling authorizations, training certificates, or ID copies.',
            self::FinanceManager => 'Tax certificates, banking documents, or finance compliance files.',
            self::RegionSupervisor => 'Supervisor ID, field authorizations, or regional appointment letters.',
        };
    }
}
