<?php

namespace App\Enums;

enum ShiftStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Recorded = 'recorded';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Missed = 'missed';
    case Incomplete = 'incomplete';
    case Replaced = 'replaced';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Scheduled',
            self::Confirmed => 'Confirmed',
            self::InProgress => 'In Progress',
            self::Recorded => 'Shift recorded',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Missed => 'Absent / No-show',
            self::Incomplete => 'Incomplete',
            self::Replaced => 'Replaced',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Scheduled => 'slate',
            self::Confirmed => 'sky',
            self::InProgress => 'brand',
            self::Recorded => 'sky',
            self::Completed => 'emerald',
            self::Cancelled => 'rose',
            self::Missed => 'amber',
            self::Incomplete => 'violet',
            self::Replaced => 'indigo',
        };
    }

    public function blocksCalendarSlot(): bool
    {
        return in_array($this, [
            self::Scheduled,
            self::Confirmed,
            self::InProgress,
            self::Recorded,
            self::Completed,
        ], true);
    }

    public function countsAsWorked(): bool
    {
        return $this === self::Recorded;
    }

    /** Statuses that count toward payroll / monthly shift totals. */
    public static function payable(): array
    {
        return [self::Recorded];
    }

    /** @return list<string> */
    public static function payableValues(): array
    {
        return array_map(static fn (self $status) => $status->value, self::payable());
    }

    /** Shift already allocated for the date — hide from the allocation board. */
    public function blocksAllocation(): bool
    {
        return in_array($this, self::blockingAllocation(), true);
    }

    /** @return list<self> */
    public static function blockingAllocation(): array
    {
        return [
            self::Scheduled,
            self::Confirmed,
            self::InProgress,
            self::Recorded,
            self::Completed,
        ];
    }

    /** @return list<string> */
    public static function blockingAllocationValues(): array
    {
        return array_map(static fn (self $status) => $status->value, self::blockingAllocation());
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Statuses shift managers may set manually (including corrections). */
    public static function manuallySettable(): array
    {
        return [
            self::Recorded,
            self::Completed,
            self::Cancelled,
            self::Missed,
            self::Incomplete,
            self::Scheduled,
            self::Confirmed,
        ];
    }

    /** @return list<string> */
    public static function manuallySettableValues(): array
    {
        return array_map(static fn (self $status) => $status->value, self::manuallySettable());
    }
}
