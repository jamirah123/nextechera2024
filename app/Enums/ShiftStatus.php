<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Missed = 'missed';
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Missed => 'Missed',
            self::Replaced => 'Replaced',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'slate',
            self::Confirmed => 'sky',
            self::InProgress => 'brand',
            self::Completed => 'emerald',
            self::Cancelled => 'rose',
            self::Missed => 'amber',
            self::Replaced => 'indigo',
        };
    }

    public function blocksCalendarSlot(): bool
    {
        return in_array($this, [
            self::Scheduled,
            self::Confirmed,
            self::InProgress,
            self::Completed,
        ], true);
    }

    public function countsAsWorked(): bool
    {
        return $this === self::Completed;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
