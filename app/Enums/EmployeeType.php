<?php

namespace App\Enums;

enum EmployeeType: string
{
    case Staff = 'staff';
    case Supervisor = 'supervisor';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::Supervisor => 'Supervisor',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
