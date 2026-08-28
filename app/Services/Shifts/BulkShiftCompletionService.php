<?php

namespace App\Services\Shifts;

use App\Enums\ShiftStatus;
use App\Models\Shift;
use App\Services\ShiftService;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Throwable;

class BulkShiftCompletionService
{
    /** @var list<ShiftStatus> */
    private array $eligible = [
        ShiftStatus::Scheduled,
        ShiftStatus::Confirmed,
        ShiftStatus::InProgress,
        ShiftStatus::Missed,
    ];

    public function __construct(private ShiftService $shifts)
    {
    }

    /**
     * @param  list<int>  $shiftIds
     * @return array{completed: int, skipped: int, errors: list<string>}
     */
    public function complete(array $shiftIds, ?string $notes = null): array
    {
        $completed = 0;
        $skipped = 0;
        $errors = [];

        /** @var Collection<int, Shift> $shifts */
        $shifts = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name'])
            ->whereIn('id', $shiftIds)
            ->get()
            ->keyBy('id');

        foreach ($shiftIds as $shiftId) {
            $shift = $shifts->get((int) $shiftId);
            if (! $shift) {
                $skipped++;
                $errors[] = 'Shift #'.$shiftId.' was not found.';

                continue;
            }

            if (! in_array($shift->status, $this->eligible, true)) {
                $skipped++;
                $errors[] = ($shift->reference ?: '#'.$shift->id).': already '.$shift->status->label().'.';

                continue;
            }

            try {
                $this->shifts->updateStatus($shift, ShiftStatus::Completed, $notes);
                $completed++;
            } catch (InvalidArgumentException $e) {
                $skipped++;
                $errors[] = ($shift->reference ?: '#'.$shift->id).': '.$e->getMessage();
            } catch (Throwable) {
                $skipped++;
                $errors[] = ($shift->reference ?: '#'.$shift->id).': could not mark completed.';
            }
        }

        return compact('completed', 'skipped', 'errors');
    }

    public static function isCompletable(Shift $shift): bool
    {
        return in_array($shift->status, [
            ShiftStatus::Scheduled,
            ShiftStatus::Confirmed,
            ShiftStatus::InProgress,
            ShiftStatus::Missed,
        ], true);
    }
}
