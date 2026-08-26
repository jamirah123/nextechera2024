<?php

namespace App\Enums;

enum CoverageStatus: string
{
    case FullyStaffed = 'fully_staffed';
    case Understaffed = 'understaffed';
    case Overstaffed = 'overstaffed';
    case Unconfigured = 'unconfigured';

    public function label(): string
    {
        return match ($this) {
            self::FullyStaffed => 'Fully Staffed',
            self::Understaffed => 'Understaffed',
            self::Overstaffed => 'Overstaffed',
            self::Unconfigured => 'Not Configured',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::FullyStaffed => 'emerald',
            self::Understaffed => 'rose',
            self::Overstaffed => 'amber',
            self::Unconfigured => 'slate',
        };
    }
}
