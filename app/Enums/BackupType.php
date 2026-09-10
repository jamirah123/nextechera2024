<?php

namespace App\Enums;

enum BackupType: string
{
    case Manual = 'manual';
    case ScheduledDaily = 'scheduled_daily';
    case ScheduledWeekly = 'scheduled_weekly';
    case SafetyPreRestore = 'safety_pre_restore';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::ScheduledDaily => 'Scheduled (daily)',
            self::ScheduledWeekly => 'Scheduled (weekly)',
            self::SafetyPreRestore => 'Safety (pre-restore)',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Manual => 'brand',
            self::ScheduledDaily => 'sky',
            self::ScheduledWeekly => 'indigo',
            self::SafetyPreRestore => 'amber',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
