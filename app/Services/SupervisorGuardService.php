<?php

namespace App\Services;

use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\Supervisor;
use Illuminate\Support\Facades\DB;

class SupervisorGuardService
{
    public function __construct(private GuardService $guards)
    {
    }

    public function ensureGuardProfile(Supervisor $supervisor): Guard
    {
        $supervisor->loadMissing('guardProfile');

        if ($supervisor->guardProfile) {
            $this->syncGuardRegion($supervisor, $supervisor->guardProfile);

            return $supervisor->guardProfile;
        }

        return DB::transaction(function () use ($supervisor) {
            [$firstName, $lastName] = $this->splitName($supervisor->name);

            $guard = $this->guards->createGuard([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $supervisor->phone,
                'region_id' => $supervisor->region_id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::OffDuty->value,
                'rank_designation' => 'Supervisor',
                'date_employed' => optional($supervisor->assignment_date)->toDateString() ?? now()->toDateString(),
                'notes' => 'Shift payroll profile for supervisor '.$supervisor->supervisor_code.'.',
            ]);

            $supervisor->update(['guard_id' => $guard->id]);

            return $guard->fresh();
        });
    }

    private function syncGuardRegion(Supervisor $supervisor, Guard $guard): void
    {
        if ((int) $guard->region_id === (int) $supervisor->region_id) {
            return;
        }

        $this->guards->updateGuard($guard, [
            'region_id' => $supervisor->region_id,
        ], 'supervisor_region_sync');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), 2) ?: [];

        return [
            $parts[0] ?? 'Supervisor',
            $parts[1] ?? $parts[0] ?? 'Profile',
        ];
    }
}
