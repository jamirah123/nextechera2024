<?php

namespace App\Enums;

enum BackupType: string
{
    case Manual = 'manual';
    case ScheduledDaily = 'scheduled_daily';
    case ScheduledWeekly = 'scheduled_weekly';
    case ScheduledMonthly = 'scheduled_monthly';
    case SafetyPreRestore = 'safety_pre_restore';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Manual',
            self::ScheduledDaily => 'Scheduled (daily)',
            self::ScheduledWeekly => 'Scheduled (weekly)',
            self::ScheduledMonthly => 'Scheduled (monthly)',
            self::SafetyPreRestore => 'Safety (pre-restore)',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Manual => 'brand',
            self::ScheduledDaily => 'sky',
            self::ScheduledWeekly => 'indigo',
            self::ScheduledMonthly => 'violet',
            self::SafetyPreRestore => 'amber',
        };
    }

    public function retentionBucket(): string
    {
        return match ($this) {
            self::ScheduledWeekly => 'weekly',
            self::ScheduledMonthly => 'monthly',
            self::Manual, self::ScheduledDaily, self::SafetyPreRestore => 'daily',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
