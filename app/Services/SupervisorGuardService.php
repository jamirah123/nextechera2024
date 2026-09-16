<?php

namespace App\Services;

use App\Enums\CompensationType;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\SupervisorStatus;
use App\Models\Guard;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Services\Hr\EmploymentIdService;
use Illuminate\Support\Facades\DB;

class SupervisorGuardService
{
    public function __construct(
        private GuardService $guards,
        private StaffService $staff,
    ) {}

    /**
     * Ensure the supervisor has both a guard (shift cover) and staff (HR registry) profile
     * sharing the same Employment ID.
     *
     * @return array{guard: Guard, staff: Staff}
     */
    public function ensureEmployeeProfiles(Supervisor $supervisor, ?string $employmentId = null): array
    {
        return DB::transaction(function () use ($supervisor, $employmentId) {
            $guard = $this->ensureGuardProfile($supervisor, $employmentId);
            $staffMember = $this->ensureStaffProfile($supervisor, $guard->employment_id);
            $this->syncGuardSalaryFromStaff($guard->fresh(), $staffMember->fresh());

            return ['guard' => $guard->fresh(), 'staff' => $staffMember->fresh()];
        });
    }

    public function ensureGuardProfile(Supervisor $supervisor, ?string $employmentId = null): Guard
    {
        $supervisor->loadMissing('guardProfile');

        if ($supervisor->guardProfile) {
            $this->syncGuardRegion($supervisor, $supervisor->guardProfile);
            $this->ensureSalaryCompensation($supervisor->guardProfile);

            return $supervisor->guardProfile->fresh();
        }

        return DB::transaction(function () use ($supervisor, $employmentId) {
            [$firstName, $lastName] = $this->splitName($supervisor->name);

            $payload = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $supervisor->phone,
                'email' => $supervisor->email,
                'region_id' => $supervisor->region_id,
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::OffDuty->value,
                'rank_designation' => 'Supervisor',
                // Fixed-salary employee; cover shifts are history unless marked overtime.
                'compensation_type' => CompensationType::Salary->value,
                'date_employed' => optional($supervisor->assignment_date)->toDateString() ?? now()->toDateString(),
                'notes' => 'Shift cover profile for supervisor '.$supervisor->supervisor_code.'. Fixed salary is paid via staff payroll; overtime only when duty type is overtime.',
            ];

            if (filled($employmentId)) {
                $payload['employment_id'] = $employmentId;
            }

            $guard = $this->guards->createGuard($payload);

            $supervisor->update(['guard_id' => $guard->id]);

            return $guard->fresh();
        });
    }

    public function ensureStaffProfile(Supervisor $supervisor, ?string $employmentId = null): Staff
    {
        $supervisor->loadMissing(['staffProfile', 'guardProfile']);

        if ($supervisor->staffProfile) {
            return $supervisor->staffProfile;
        }

        return DB::transaction(function () use ($supervisor, $employmentId) {
            [$firstName, $lastName] = $this->splitName($supervisor->name);
            $employmentId ??= $supervisor->guardProfile?->employment_id;

            $staffMember = $this->staff->createStaff([
                'employment_id' => $employmentId,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'phone' => $supervisor->phone,
                'email' => $supervisor->email,
                'region_id' => $supervisor->region_id,
                'employment_status' => EmploymentStatus::Active->value,
                'job_title' => 'Supervisor',
                'department' => 'Operations',
                'date_employed' => optional($supervisor->assignment_date)->toDateString() ?? now()->toDateString(),
                'monthly_salary' => 0,
                'notes' => 'Auto-created from supervisor '.$supervisor->supervisor_code.'. Set monthly salary when ready for salaried payroll.',
                'employee_type' => 'staff', // already creating staff only; avoid recursive supervisor create
            ]);

            $supervisor->update(['staff_id' => $staffMember->id]);

            return $staffMember->fresh();
        });
    }

    /**
     * Link a newly registered staff member as a field supervisor (guard + supervisor records).
     *
     * @param  array{status?: string, assignment_date?: string|null, reason?: string|null, notes?: string|null}  $data
     */
    public function createSupervisorForStaff(Staff $staff, array $data = []): Supervisor
    {
        return DB::transaction(function () use ($staff, $data) {
            if ($staff->supervisorProfile) {
                return $staff->supervisorProfile;
            }

            if (! $staff->region_id) {
                throw new \InvalidArgumentException('A region is required when registering a supervisor.');
            }

            $organization = app(OrganizationService::class);

            $supervisor = Supervisor::query()->create([
                'supervisor_code' => $organization->nextSupervisorCode(),
                'name' => $staff->full_name,
                'phone' => $staff->phone,
                'email' => $staff->email,
                'region_id' => $staff->region_id,
                'staff_id' => $staff->id,
                'status' => $data['status'] ?? SupervisorStatus::Active->value,
                'assignment_date' => $data['assignment_date']
                    ?? optional($staff->date_employed)->toDateString()
                    ?? now()->toDateString(),
                'notes' => $data['notes'] ?? $staff->notes,
            ]);

            $organization->recordSupervisorAssignment(
                $supervisor,
                null,
                (int) $supervisor->region_id,
                'initial_assignment',
                $data['reason'] ?? null,
                'Supervisor registered via staff registration.',
            );

            $this->ensureGuardProfile($supervisor, $staff->employment_id);
            $this->syncGuardSalaryFromStaff($supervisor->fresh()->guardProfile, $staff);

            return $supervisor->fresh(['guardProfile', 'staffProfile']);
        });
    }

    public function syncFromSupervisor(Supervisor $supervisor): void
    {
        $profiles = $this->ensureEmployeeProfiles($supervisor);
        [$firstName, $lastName] = $this->splitName($supervisor->name);

        $this->guards->updateGuard($profiles['guard'], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $supervisor->phone,
            'email' => $supervisor->email,
            'region_id' => $supervisor->region_id,
        ], 'supervisor_profile_sync');

        $this->staff->updateStaff($profiles['staff'], [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $supervisor->phone,
            'email' => $supervisor->email,
            'region_id' => $supervisor->region_id,
        ]);

        $this->syncGuardSalaryFromStaff($profiles['guard']->fresh(), $profiles['staff']->fresh());
    }

    public function syncLinkedEmploymentId(Supervisor $supervisor, string $employmentId, ?string $reason = null): void
    {
        $profiles = $this->ensureEmployeeProfiles($supervisor);
        $employmentId = app(EmploymentIdService::class)->normalize($employmentId);

        if ($profiles['guard']->employment_id !== $employmentId) {
            $this->guards->updateGuard(
                $profiles['guard'],
                ['employment_id' => $employmentId],
                $reason,
            );
        }

        if ($profiles['staff']->fresh()->employment_id !== $employmentId) {
            // Same person may hold this ID on both guard and staff rows.
            $profiles['staff']->update(['employment_id' => $employmentId]);
        }
    }

    private function ensureSalaryCompensation(Guard $guard): void
    {
        if ($guard->isSalaryStaff()) {
            return;
        }

        $this->guards->updateGuard($guard, [
            'compensation_type' => CompensationType::Salary->value,
        ], 'supervisor_salary_compensation');
    }

    public function syncGuardSalaryFromStaff(Guard $guard, Staff $staff): void
    {
        $monthly = (float) $staff->monthly_salary;

        $payload = [
            'compensation_type' => CompensationType::Salary->value,
        ];

        if ($monthly > 0 && (float) $guard->base_shift_rate !== $monthly) {
            $payload['base_shift_rate'] = $monthly;
        }

        $this->guards->updateGuard($guard, $payload, 'supervisor_salary_sync');
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
