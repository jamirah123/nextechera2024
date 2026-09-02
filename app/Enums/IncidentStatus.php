<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Reported = 'reported';
    case Investigating = 'investigating';
    case FollowUp = 'follow_up';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported',
            self::Investigating => 'Investigating',
            self::FollowUp => 'Follow-up',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Reported => 'sky',
            self::Investigating => 'amber',
            self::FollowUp => 'violet',
            self::Resolved => 'emerald',
            self::Closed => 'slate',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Resolved, self::Closed], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
