<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\EmployeeType;
use App\Enums\EmploymentStatus;
use App\Models\Staff;
use App\Services\Hr\EmploymentIdService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StaffService
{
    public function __construct(private EmploymentIdService $employmentIds) {}

    public function composeFullName(?string $first, ?string $middle, ?string $last): string
    {
        return collect([$first, $middle, $last])
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(' ');
    }

    public function nextEmploymentId(): string
    {
        return $this->employmentIds->next();
    }

    /** @param  array<string, mixed>  $data */
    public function createStaff(array $data): Staff
    {
        return DB::transaction(function () use ($data) {
            $employeeType = EmployeeType::tryFrom((string) ($data['employee_type'] ?? EmployeeType::Staff->value))
                ?? EmployeeType::Staff;

            $supervisorPayload = [
                'status' => $data['supervisor_status'] ?? null,
                'assignment_date' => $data['assignment_date'] ?? null,
                'reason' => $data['assignment_reason'] ?? $data['reason'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            unset(
                $data['employee_type'],
                $data['supervisor_status'],
                $data['assignment_date'],
                $data['assignment_reason'],
                $data['reason'],
            );

            if (! empty($data['employment_id'])) {
                $data['employment_id'] = $this->employmentIds->normalize((string) $data['employment_id']);
            } else {
                $data['employment_id'] = $this->nextEmploymentId();
            }

            if ($employeeType === EmployeeType::Supervisor) {
                $data['job_title'] = filled($data['job_title'] ?? null) ? $data['job_title'] : 'Supervisor';
                $data['department'] = filled($data['department'] ?? null) ? $data['department'] : 'Operations';
            }

            $data['full_name'] = $this->composeFullName(
                $data['first_name'] ?? null,
                $data['middle_name'] ?? null,
                $data['last_name'] ?? null,
            );
            $data['employment_status'] = $data['employment_status'] ?? EmploymentStatus::Active->value;
            $data['date_employed'] = $data['date_employed'] ?? now()->toDateString();

            $staff = Staff::query()->create($data);

            if ($employeeType === EmployeeType::Supervisor) {
                app(SupervisorGuardService::class)->createSupervisorForStaff($staff, [
                    'status' => $supervisorPayload['status'],
                    'assignment_date' => $supervisorPayload['assignment_date'] ?? $staff->date_employed?->toDateString(),
                    'reason' => $supervisorPayload['reason'],
                    'notes' => $supervisorPayload['notes'],
                ]);
            }

            if ((float) $staff->monthly_salary > 0) {
                app(StaffSalaryService::class)->recordOpening(
                    $staff,
                    (float) $staff->monthly_salary,
                    $staff->date_employed ?? Carbon::today(),
                    Auth::user(),
                    $staff->job_title,
                    $staff->job_grade,
                );
            }

            return $staff->fresh(['supervisorProfile.guardProfile']);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function updateStaff(Staff $staff, array $data, ?string $reason = null): Staff
    {
        return DB::transaction(function () use ($staff, $data, $reason) {
            $previousEmploymentId = $staff->employment_id;

            unset(
                $data['employee_type'],
                $data['supervisor_status'],
                $data['assignment_date'],
                $data['assignment_reason'],
            );

            if (array_key_exists('employment_id', $data)) {
                $user = Auth::user();
                if (! $user?->can('correctEmploymentId', $staff)) {
                    unset($data['employment_id']);
                } else {
                    $data['employment_id'] = $this->employmentIds->normalize((string) $data['employment_id']);
                    if ($data['employment_id'] === $previousEmploymentId) {
                        unset($data['employment_id']);
                    }
                }
            }

            if (isset($data['first_name']) || isset($data['middle_name']) || isset($data['last_name'])) {
                $data['full_name'] = $this->composeFullName(
                    $data['first_name'] ?? $staff->first_name,
                    array_key_exists('middle_name', $data) ? $data['middle_name'] : $staff->middle_name,
                    $data['last_name'] ?? $staff->last_name,
                );
            }

            if ($staff->salaryRevisions()->exists()) {
                unset($data['monthly_salary'], $data['job_title'], $data['job_grade']);
            }

            $staff->update($data);
            $staff->refresh();

            $staff->loadMissing('supervisorProfile.guardProfile');
            if ($staff->supervisorProfile?->guardProfile) {
                app(SupervisorGuardService::class)->syncGuardSalaryFromStaff(
                    $staff->supervisorProfile->guardProfile,
                    $staff,
                );
            }

            if (isset($data['employment_id']) && $data['employment_id'] !== $previousEmploymentId) {
                app(AuditService::class)->logOverride(
                    action: 'employment_id.corrected',
                    summary: "Employment ID corrected from {$previousEmploymentId} to {$data['employment_id']}.",
                    subject: $staff,
                    reason: $reason ?: 'employment_id_correction',
                    context: [
                        'from' => $previousEmploymentId,
                        'to' => $data['employment_id'],
                        'employee_type' => 'staff',
                    ],
                    category: AuditCategory::Hr,
                );

                if ($staff->supervisorProfile) {
                    app(SupervisorGuardService::class)->syncLinkedEmploymentId(
                        $staff->supervisorProfile,
                        $data['employment_id'],
                        $reason,
                    );
                }
            }

            return $staff;
        });
    }
}
