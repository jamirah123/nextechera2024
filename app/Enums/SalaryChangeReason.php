<?php

namespace App\Enums;

enum SalaryChangeReason: string
{
    case Initial = 'initial';
    case LengthOfService = 'length_of_service';
    case Promotion = 'promotion';
    case Performance = 'performance';
    case ContractChange = 'contract_change';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Opening salary',
            self::LengthOfService => 'Length of service',
            self::Promotion => 'Promotion',
            self::Performance => 'Performance',
            self::ContractChange => 'Contract change',
            self::Other => 'Other approved decision',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
