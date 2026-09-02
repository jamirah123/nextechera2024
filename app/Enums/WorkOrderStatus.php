<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Assigned => 'Assigned',
            self::InProgress => 'In progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'sky',
            self::Assigned => 'indigo',
            self::InProgress => 'amber',
            self::Completed => 'emerald',
            self::Cancelled => 'slate',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Open, self::Assigned, self::InProgress], true);
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
