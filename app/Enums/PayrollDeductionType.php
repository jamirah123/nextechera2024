<?php

namespace App\Enums;

enum PayrollDeductionType: string
{
    case Paye = 'paye';
    case Nssf = 'nssf';
    case Advance = 'advance';
    case Uniform = 'uniform';
    case AssetRecovery = 'asset_recovery';
    case Penalty = 'penalty';
    case UnpaidLeave = 'unpaid_leave';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Paye => 'PAYE (tax)',
            self::Nssf => 'NSSF',
            self::Advance => 'Salary advance',
            self::Uniform => 'Uniform charge',
            self::AssetRecovery => 'Asset replacement',
            self::Penalty => 'Penalty',
            self::UnpaidLeave => 'Unpaid leave',
            self::Other => 'Other deduction',
        };
    }

    public function isStatutory(): bool
    {
        return in_array($this, [self::Paye, self::Nssf], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
