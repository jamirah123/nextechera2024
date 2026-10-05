<?php

namespace App\Support\Historical;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Shared rules for past-date / historical operational recording.
 *
 * Operational date = when the activity happened.
 * Entry date = when the row was saved (created_at) — never confuse the two.
 */
final class HistoricalDates
{
    public static function today(): Carbon
    {
        return now()->copy()->startOfDay();
    }

    public static function parseDate(string|CarbonInterface|null $date, ?string $fallback = null): Carbon
    {
        if ($date instanceof CarbonInterface) {
            return Carbon::instance($date)->startOfDay();
        }

        $raw = $date ?: ($fallback ?? now()->toDateString());

        return Carbon::parse($raw)->startOfDay();
    }

    /**
     * True when every duty date in the range is strictly before today.
     * Past-only ranges must be stored as history and must not flip current On Duty.
     */
    public static function isHistoricalDutyRange(
        string|CarbonInterface|null $from,
        string|CarbonInterface|null $to = null,
    ): bool {
        $start = self::parseDate($from);
        $end = $to !== null && $to !== ''
            ? self::parseDate($to)
            : $start->copy();

        if ($end->lt($start)) {
            $end = $start->copy();
        }

        return $end->lt(self::today());
    }

    /**
     * True when a single timestamp/date falls strictly before today.
     */
    public static function isPastCalendarDay(string|CarbonInterface|null $dateTime): bool
    {
        if ($dateTime === null || $dateTime === '') {
            return false;
        }

        return self::parseDate($dateTime)->lt(self::today());
    }

    /**
     * True when the date covers "today" (inclusive start/end).
     */
    public static function coversToday(
        string|CarbonInterface|null $from,
        string|CarbonInterface|null $to = null,
    ): bool {
        $start = self::parseDate($from);
        $end = $to !== null && $to !== ''
            ? self::parseDate($to)
            : $start->copy()->addYears(50);

        $today = self::today();

        return $start->lte($today) && $end->gte($today);
    }

    /**
     * Last instant of a calendar day.
     * A DATE or DATETIME value stored as midnight still compares as that day
     * on MySQL and on sqlite, without wrapping the column in DATE().
     */
    public static function endOfCalendarDay(string|CarbonInterface $date): string
    {
        return self::parseDate($date)->endOfDay()->toDateTimeString();
    }

    public static function nextCalendarDay(string|CarbonInterface $date): string
    {
        return self::parseDate($date)->addDay()->toDateString();
    }
}
