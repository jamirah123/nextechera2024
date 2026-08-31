<?php

namespace App\Enums;

enum PayrollRunStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Calculated => 'Calculated',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Calculated => 'sky',
            self::Submitted => 'amber',
            self::Approved => 'indigo',
            self::Paid => 'emerald',
            self::Cancelled => 'rose',
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function canCalculate(): bool
    {
        return in_array($this, [self::Draft, self::Calculated], true);
    }

    public function canSubmit(): bool
    {
        return $this === self::Calculated;
    }

    public function canAddDeductions(): bool
    {
        return $this === self::Calculated;
    }

    public function canApprove(): bool
    {
        return $this === self::Submitted;
    }

    public function canCancel(): bool
    {
        return $this !== self::Cancelled;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
