<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Issued => 'Issued',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Overdue => 'Overdue',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::Issued => 'sky',
            self::PartiallyPaid => 'amber',
            self::Paid => 'emerald',
            self::Overdue => 'rose',
            self::Cancelled => 'slate',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Issued, self::PartiallyPaid, self::Overdue], true);
    }

    /** @return list<array{value: string, label: string}> */
    public static function lifecycleSteps(): array
    {
        return [
            ['value' => self::Draft->value, 'label' => 'Draft'],
            ['value' => self::Issued->value, 'label' => 'Issued'],
            ['value' => self::PartiallyPaid->value, 'label' => 'Partially paid'],
            ['value' => self::Paid->value, 'label' => 'Paid'],
        ];
    }

    public function lifecycleStep(): int
    {
        return match ($this) {
            self::Draft => 0,
            self::Issued, self::Overdue => 1,
            self::PartiallyPaid => 2,
            self::Paid => 3,
            self::Cancelled => -1,
        };
    }

    public function isTerminalLifecycle(): bool
    {
        return in_array($this, [self::Paid, self::Cancelled], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
