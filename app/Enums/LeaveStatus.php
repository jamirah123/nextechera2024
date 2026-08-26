<?php

namespace App\Enums;

enum LeaveStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Completed => 'Completed',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Approved => 'emerald',
            self::Rejected => 'rose',
            self::Cancelled => 'slate',
            self::Completed => 'sky',
        };
    }

    public function isActiveLeave(): bool
    {
        return $this === self::Approved;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
