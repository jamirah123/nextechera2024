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
     * @return array{started: int, completed: int, missed: int}
     */
    public function sync(): array
    {
        $now = now();
        $started = 0;
        $completed = 0;
        $missed = 0;

        Shift::query()
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed])
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->orderBy('id')
            ->each(function (Shift $shift) use (&$started): void {
                $this->shifts->updateStatus($shift, ShiftStatus::InProgress, automatic: true);
                $started++;
            });

        Shift::query()
            ->where('status', ShiftStatus::InProgress)
            ->where('ends_at', '<=', $now)
            ->orderBy('id')
            ->each(function (Shift $shift) use (&$completed): void {
                $this->shifts->updateStatus($shift, ShiftStatus::Completed, automatic: true);
                $completed++;
            });

        Shift::query()
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed])
            ->where('ends_at', '<=', $now)
            ->orderBy('id')
            ->each(function (Shift $shift) use (&$missed): void {
                $this->shifts->updateStatus($shift, ShiftStatus::Missed, automatic: true);
                $missed++;
            });

        return [
            'started' => $started,
            'completed' => $completed,
            'missed' => $missed,
        ];
    }
}
