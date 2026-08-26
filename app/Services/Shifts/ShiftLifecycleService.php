<?php

namespace App\Services\Shifts;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Services\ShiftService;

class ShiftLifecycleService
{
    public function __construct(private ShiftService $shifts)
    {
    }

    /**
     * @return array{started: int, missed: int}
     */
    public function sync(): array
    {
        $now = now();
        $started = 0;
        $missed = 0;

        Shift::query()
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed])
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderBy('id')
            ->each(function (Shift $shift) use (&$started): void {
                $this->shifts->updateStatus($shift, ShiftStatus::InProgress);
                $started++;
            });

        Shift::query()
            ->whereIn('status', [
                ShiftStatus::Scheduled,
                ShiftStatus::Confirmed,
                ShiftStatus::InProgress,
            ])
            ->where('ends_at', '<=', $now)
            ->orderBy('id')
            ->each(function (Shift $shift) use (&$missed): void {
                $this->shifts->updateStatus($shift, ShiftStatus::Missed);
                $missed++;
            });

        return [
            'started' => $started,
            'missed' => $missed,
        ];
    }
}
