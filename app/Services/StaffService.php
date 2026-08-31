<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Models\Staff;
use Illuminate\Support\Facades\DB;

class StaffService
{
    public function composeFullName(?string $first, ?string $middle, ?string $last): string
    {
        return collect([$first, $middle, $last])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(' ');
    }

    public function nextEmploymentId(): string
    {
        $latest = Staff::withTrashed()
            ->where('employment_id', 'like', 'STF%')
            ->orderByDesc('id')
            ->value('employment_id');

        $number = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $number = ((int) $matches[1]) + 1;
        }

        return 'STF'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
    }

    /** @param  array<string, mixed>  $data */
    public function createStaff(array $data): Staff
    {
        return DB::transaction(function () use ($data) {
            $data['employment_id'] = $data['employment_id'] ?? $this->nextEmploymentId();
            $data['full_name'] = $this->composeFullName(
                $data['first_name'] ?? null,
                $data['middle_name'] ?? null,
                $data['last_name'] ?? null,
            );
            $data['employment_status'] = $data['employment_status'] ?? EmploymentStatus::Active->value;
            $data['date_employed'] = $data['date_employed'] ?? now()->toDateString();

            return Staff::query()->create($data);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateStaff(Staff $staff, array $data): Staff
    {
        return DB::transaction(function () use ($staff, $data) {
            if (isset($data['first_name']) || isset($data['middle_name']) || isset($data['last_name'])) {
                $data['full_name'] = $this->composeFullName(
                    $data['first_name'] ?? $staff->first_name,
                    array_key_exists('middle_name', $data) ? $data['middle_name'] : $staff->middle_name,
                    $data['last_name'] ?? $staff->last_name,
                );
            }

            $staff->update($data);

            return $staff->refresh();
        });
    }
}
