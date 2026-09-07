<?php

namespace App\Enums;

enum GlJournalSource: string
{
    case Invoice = 'invoice';
    case Payment = 'payment';
    case Purchase = 'purchase';
    case PayrollAccrual = 'payroll_accrual';
    case PayrollPayment = 'payroll_payment';
    case Manual = 'manual';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => 'Invoice',
            self::Payment => 'Payment',
            self::Purchase => 'Purchase',
            self::PayrollAccrual => 'Payroll accrual',
            self::PayrollPayment => 'Payroll payment',
            self::Manual => 'Manual',
            self::Reversal => 'Reversal',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Invoice => 'indigo',
            self::Payment => 'emerald',
            self::Purchase => 'violet',
            self::PayrollAccrual, self::PayrollPayment => 'amber',
            self::Manual => 'sky',
            self::Reversal => 'rose',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
