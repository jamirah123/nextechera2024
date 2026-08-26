<?php

namespace App\Enums;

enum OperationalStatus: string
{
    case OnDuty = 'on_duty';
    case OffDuty = 'off_duty';
    case OnLeave = 'on_leave';
    case Absent = 'absent';
    case Deserted = 'deserted';
    case SickUnavailable = 'sick_unavailable';
    case Training = 'training';
    case Suspended = 'suspended';
    case AwaitingDeployment = 'awaiting_deployment';

    public function label(): string
    {
        return match ($this) {
            self::OnDuty => 'On Duty',
            self::OffDuty => 'Off Duty',
            self::OnLeave => 'On Leave',
            self::Absent => 'Absent',
            self::Deserted => 'Deserted',
            self::SickUnavailable => 'Sick / Unavailable',
            self::Training => 'Training',
            self::Suspended => 'Suspended',
            self::AwaitingDeployment => 'Awaiting Deployment',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::OnDuty => 'emerald',
            self::OffDuty => 'slate',
            self::OnLeave => 'sky',
            self::Absent => 'amber',
            self::Deserted => 'rose',
            self::SickUnavailable => 'amber',
            self::Training => 'indigo',
            self::Suspended => 'violet',
            self::AwaitingDeployment => 'brand',
        };
    }

    public function isAvailableForDuty(): bool
    {
        return in_array($this, [
            self::OffDuty,
            self::AwaitingDeployment,
            self::OnDuty,
        ], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
