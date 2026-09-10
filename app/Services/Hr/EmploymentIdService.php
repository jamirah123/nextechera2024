<?php

namespace App\Services\Hr;

use App\Models\Guard;
use App\Models\Staff;

class EmploymentIdService
{
    public const PREFIX = 'PSG';

    /** Minimum digit width: PSG001 … PSG999, then PSG1000. */
    public const MIN_DIGITS = 3;

    public const PATTERN = '/^PSG\d{3,}$/i';

    public function normalize(string $employmentId): string
    {
        return strtoupper(trim($employmentId));
    }

    public function isValidFormat(string $employmentId): bool
    {
        return (bool) preg_match(self::PATTERN, $this->normalize($employmentId));
    }

    public function format(int $number): string
    {
        $number = max(1, $number);
        $width = max(self::MIN_DIGITS, strlen((string) $number));

        return self::PREFIX.str_pad((string) $number, $width, '0', STR_PAD_LEFT);
    }

    /**
     * Next continuous PSG ID across guards and staff (never resets yearly).
     * Supervisors share this sequence through their linked guard payroll profile.
     */
    public function next(): string
    {
        return $this->format($this->currentMaxSequence() + 1);
    }

    public function currentMaxSequence(): int
    {
        $max = 0;

        foreach ([Guard::class, Staff::class] as $model) {
            $ids = $model::withTrashed()
                ->where('employment_id', 'like', self::PREFIX.'%')
                ->pluck('employment_id');

            foreach ($ids as $id) {
                if (preg_match('/^'.self::PREFIX.'(\d+)$/i', (string) $id, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }
        }

        return $max;
    }

    public function isTaken(
        string $employmentId,
        ?int $ignoreGuardId = null,
        ?int $ignoreStaffId = null,
    ): bool {
        $employmentId = $this->normalize($employmentId);

        $guardTaken = Guard::withTrashed()
            ->where('employment_id', $employmentId)
            ->when($ignoreGuardId !== null, fn ($q) => $q->where('id', '!=', $ignoreGuardId))
            ->exists();

        if ($guardTaken) {
            return true;
        }

        return Staff::withTrashed()
            ->where('employment_id', $employmentId)
            ->when($ignoreStaffId !== null, fn ($q) => $q->where('id', '!=', $ignoreStaffId))
            ->exists();
    }
}
