<?php

namespace App\Support\Deployments;

use App\Enums\DeploymentShiftType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class DeploymentShiftSchedule
{
    private function __construct(
        private int $dayStartMinutes,
        private int $dayEndMinutes,
        private int $nightStartMinutes,
        private int $nightEndMinutes,
        private string $dayStartTime,
        private string $dayEndTime,
        private string $nightStartTime,
        private string $nightEndTime,
    ) {
    }

    public static function fromConfig(?CarbonInterface $at = null): self
    {
        $dayStart = (string) config('psg.shift_defaults.day.start', '06:00');
        $dayEnd = (string) config('psg.shift_defaults.day.end', '18:00');
        $nightStart = (string) config('psg.shift_defaults.night.start', '18:00');
        $nightEnd = (string) config('psg.shift_defaults.night.end', '06:00');

        return new self(
            dayStartMinutes: self::minutesFromTime($dayStart),
            dayEndMinutes: self::minutesFromTime($dayEnd),
            nightStartMinutes: self::minutesFromTime($nightStart),
            nightEndMinutes: self::minutesFromTime($nightEnd),
            dayStartTime: self::sqlTime($dayStart),
            dayEndTime: self::sqlTime($dayEnd),
            nightStartTime: self::sqlTime($nightStart),
            nightEndTime: self::sqlTime($nightEnd),
        );
    }

    public static function isOnShift(DeploymentShiftType $shiftType, ?CarbonInterface $at = null): bool
    {
        return self::fromConfig($at)->isShiftActive($shiftType, self::normalize($at));
    }

    public static function isAvailableForDeploymentBoard(DeploymentShiftType $shiftType, ?CarbonInterface $at = null): bool
    {
        return self::fromConfig($at)->canAppearOnDeploymentBoard($shiftType, self::normalize($at));
    }

    public function isShiftActive(DeploymentShiftType $shiftType, CarbonInterface $at): bool
    {
        return ! $this->canAppearOnDeploymentBoard($shiftType, $at);
    }

    public function canAppearOnDeploymentBoard(DeploymentShiftType $shiftType, CarbonInterface $at): bool
    {
        $minutes = self::minutesFrom($at);

        return match ($shiftType) {
            DeploymentShiftType::Day => $minutes < $this->dayStartMinutes || $minutes >= $this->dayEndMinutes,
            DeploymentShiftType::Night => $minutes >= $this->nightEndMinutes && $minutes < $this->nightStartMinutes,
            DeploymentShiftType::Rotating => true,
        };
    }

    /**
     * Exclude guards who are currently within their posted shift window.
     *
     * @param  Builder<\App\Models\Guard>  $query
     */
    public function scopeWithoutOnShiftDeployment(Builder $query, ?CarbonInterface $at = null): void
    {
        $at = self::normalize($at);
        $timeLiteral = "'".$at->format('H:i:s')."'";
        $dayOnShift = "({$timeLiteral} >= '{$this->dayStartTime}' AND {$timeLiteral} < '{$this->dayEndTime}')";
        $nightOnShift = "({$timeLiteral} >= '{$this->nightStartTime}' OR {$timeLiteral} < '{$this->nightEndTime}')";

        $query->whereDoesntHave('deployments', function (Builder $deployment) use ($dayOnShift, $nightOnShift): void {
            $deployment->current()->where(function (Builder $inner) use ($dayOnShift, $nightOnShift): void {
                $inner->where(function (Builder $day) use ($dayOnShift): void {
                    $day->where('shift_type', DeploymentShiftType::Day->value)
                        ->whereRaw($dayOnShift);
                })->orWhere(function (Builder $night) use ($nightOnShift): void {
                    $night->where('shift_type', DeploymentShiftType::Night->value)
                        ->whereRaw($nightOnShift);
                });
            });
        });
    }

    /** @return array{day: string, night: string, day_available: string, night_available: string} */
    public function labels(): array
    {
        $dayStart = config('psg.shift_defaults.day.start', '06:00');
        $dayEnd = config('psg.shift_defaults.day.end', '18:00');
        $nightEnd = config('psg.shift_defaults.night.end', '06:00');
        $nightStart = config('psg.shift_defaults.night.start', '18:00');

        return [
            'day' => $dayStart.' – '.$dayEnd,
            'night' => $nightStart.' – '.$nightEnd,
            'day_available' => $dayEnd.' – '.$dayStart,
            'night_available' => $nightEnd.' – '.$nightStart,
        ];
    }

    private static function normalize(?CarbonInterface $at): CarbonInterface
    {
        return ($at ?? now())->copy()->startOfMinute();
    }

    private static function minutesFrom(CarbonInterface $at): int
    {
        return ($at->hour * 60) + $at->minute;
    }

    private static function minutesFromTime(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour * 60) + $minute;
    }

    private static function sqlTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
