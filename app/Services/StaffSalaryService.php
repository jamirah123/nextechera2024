<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\StaffSalaryChangeType;
use App\Models\Staff;
use App\Models\StaffSalaryRevision;
use App\Models\User;
use App\Support\Finance\PayrollRates;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class StaffSalaryService
{
    public function __construct(private AuditService $audit) {}

    public function recordOpening(
        Staff $staff,
        float $salary,
        CarbonInterface $effectiveFrom,
        ?User $actor = null,
        ?string $jobTitle = null,
        ?string $grade = null,
        ?string $notes = null,
    ): StaffSalaryRevision {
        return DB::transaction(function () use ($staff, $salary, $effectiveFrom, $actor, $jobTitle, $grade, $notes) {
            $staff = Staff::query()->lockForUpdate()->findOrFail($staff->id);

            if ($staff->salaryRevisions()->exists()) {
                throw new InvalidArgumentException('This employee already has a salary history. Record a salary change instead of a new opening salary.');
            }

            $revision = $this->insertRevision(
                $staff,
                previousSalary: null,
                salary: $salary,
                previousTitle: null,
                jobTitle: $jobTitle ?? $staff->job_title,
                previousGrade: null,
                grade: $grade ?? $staff->job_grade,
                effectiveFrom: $effectiveFrom,
                effectiveTo: null,
                changeType: StaffSalaryChangeType::Initial,
                reason: 'Opening salary',
                actor: $actor,
                notes: $notes,
            );

            $this->syncCurrent($staff);
            $this->auditChange($staff, $revision, $actor, 'Opening salary recorded.');

            return $revision;
        });
    }

    public function change(
        Staff $staff,
        float $salary,
        CarbonInterface $effectiveFrom,
        StaffSalaryChangeType $changeType,
        string $reason,
        ?User $actor = null,
        ?string $jobTitle = null,
        ?string $grade = null,
        ?string $notes = null,
    ): StaffSalaryRevision {
        if ($salary < 0) {
            throw new InvalidArgumentException('Salary cannot be negative.');
        }

        if ($changeType === StaffSalaryChangeType::Initial) {
            throw new InvalidArgumentException('Opening salary is recorded when the employee is registered.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reason is required for every salary change.');
        }

        return DB::transaction(function () use ($staff, $salary, $effectiveFrom, $changeType, $reason, $actor, $jobTitle, $grade, $notes) {
            $staff = Staff::query()->lockForUpdate()->findOrFail($staff->id);
            $effectiveFrom = $effectiveFrom->copy()->startOfDay();
            $jobTitle = $this->blankToNull($jobTitle) ?? $staff->job_title;
            $grade = $this->blankToNull($grade) ?? $staff->job_grade;

            $latest = $staff->salaryRevisions()->reorder()->orderByDesc('effective_from')->orderByDesc('id')->first();

            if ($latest === null) {
                $current = (float) $staff->monthly_salary;
                $openingFrom = $staff->date_employed?->copy()->startOfDay();

                if ($current > 0 && $openingFrom !== null && $openingFrom->lessThan($effectiveFrom)) {
                    $this->insertRevision(
                        $staff,
                        previousSalary: null,
                        salary: $current,
                        previousTitle: null,
                        jobTitle: $staff->job_title,
                        previousGrade: null,
                        grade: $staff->job_grade,
                        effectiveFrom: $openingFrom,
                        effectiveTo: $effectiveFrom->copy()->subDay(),
                        changeType: StaffSalaryChangeType::Initial,
                        reason: 'Opening salary taken from the employee profile.',
                        actor: $actor,
                        notes: null,
                    );
                }

                $revision = $this->insertRevision(
                    $staff,
                    previousSalary: $current > 0 ? $current : null,
                    salary: $salary,
                    previousTitle: $staff->job_title,
                    jobTitle: $jobTitle,
                    previousGrade: $staff->job_grade,
                    grade: $grade,
                    effectiveFrom: $effectiveFrom,
                    effectiveTo: null,
                    changeType: $changeType,
                    reason: $reason,
                    actor: $actor,
                    notes: $notes,
                );

                $this->syncCurrent($staff);
                $this->auditChange($staff, $revision, $actor, 'Salary change recorded.');

                return $revision;
            }

            if ($effectiveFrom->lessThanOrEqualTo($latest->effective_from->copy()->startOfDay())) {
                throw new InvalidArgumentException(
                    'The effective date must be after the current salary start date ('.$latest->effective_from->toDateString().').'
                );
            }

            if ($latest->effective_to !== null && $effectiveFrom->lessThanOrEqualTo($latest->effective_to->copy()->startOfDay())) {
                throw new InvalidArgumentException('That effective date overlaps an existing salary record.');
            }

            $sameSalary = abs(((float) $latest->salary) - $salary) < 0.009;
            $sameTitle = (string) $latest->job_title === (string) $jobTitle;
            $sameGrade = (string) $latest->grade === (string) $grade;

            if ($sameSalary && $sameTitle && $sameGrade) {
                throw new InvalidArgumentException('Change the salary, position, or grade. A position change does not change salary unless a new salary is entered.');
            }

            if ($latest->effective_to === null) {
                $closeOn = $effectiveFrom->copy()->subDay();
                if ($closeOn->lessThan($latest->effective_from->copy()->startOfDay())) {
                    throw new InvalidArgumentException('That effective date overlaps an existing salary record.');
                }
                $latest->update(['effective_to' => $closeOn->toDateString()]);
            }

            $revision = $this->insertRevision(
                $staff,
                previousSalary: (float) $latest->salary,
                salary: $salary,
                previousTitle: $latest->job_title,
                jobTitle: $jobTitle,
                previousGrade: $latest->grade,
                grade: $grade,
                effectiveFrom: $effectiveFrom,
                effectiveTo: null,
                changeType: $changeType,
                reason: $reason,
                actor: $actor,
                notes: $notes,
            );

            $this->syncCurrent($staff);
            $this->auditChange($staff, $revision, $actor, 'Salary change recorded.');

            return $revision;
        });
    }

    private function insertRevision(
        Staff $staff,
        ?float $previousSalary,
        float $salary,
        ?string $previousTitle,
        ?string $jobTitle,
        ?string $previousGrade,
        ?string $grade,
        CarbonInterface $effectiveFrom,
        ?CarbonInterface $effectiveTo,
        StaffSalaryChangeType $changeType,
        ?string $reason,
        ?User $actor,
        ?string $notes,
    ): StaffSalaryRevision {
        if ($salary < 0) {
            throw new InvalidArgumentException('Salary cannot be negative.');
        }

        return StaffSalaryRevision::query()->create([
            'staff_id' => $staff->id,
            'previous_salary' => $previousSalary,
            'salary' => round($salary, 2),
            'previous_job_title' => $previousTitle,
            'job_title' => $jobTitle,
            'previous_grade' => $previousGrade,
            'grade' => $grade,
            'effective_from' => $effectiveFrom->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'change_type' => $changeType,
            'reason' => $reason,
            'approved_by' => $actor?->id,
            'approved_at' => $actor ? now() : null,
            'notes' => $notes,
            'created_by' => $actor?->id,
        ]);
    }

    private function syncCurrent(Staff $staff): void
    {
        $staff->unsetRelation('salaryRevisions');
        $today = now()->startOfDay();
        $current = $staff->salaryRevisions()
            ->reorder()
            ->whereDate('effective_from', '<=', $today->toDateString())
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_to')
                    ->orWhereDate('effective_to', '>=', $today->toDateString());
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        $staff->monthly_salary = PayrollRates::staffSalaryOn($staff, $today);

        if ($current !== null) {
            if (filled($current->job_title)) {
                $staff->job_title = $current->job_title;
            }
            $staff->job_grade = $current->grade;
        }

        $staff->save();
        $this->syncSupervisorGuard($staff);
    }

    private function syncSupervisorGuard(Staff $staff): void
    {
        $staff->loadMissing('supervisorProfile.guardProfile');
        $guard = $staff->supervisorProfile?->guardProfile;

        if ($guard === null) {
            return;
        }

        app(SupervisorGuardService::class)->syncGuardSalaryFromStaff($guard, $staff);
    }

    private function auditChange(Staff $staff, StaffSalaryRevision $revision, ?User $actor, string $summary): void
    {
        $this->audit->log(
            action: 'staff.salary_changed',
            summary: $summary.' '.$staff->employment_id.' '.number_format((float) $revision->salary, 2).' from '.$revision->effective_from->toDateString().'.',
            category: AuditCategory::Hr,
            severity: AuditSeverity::Notice,
            subject: $staff,
            context: [
                'revision_id' => $revision->id,
                'previous_salary' => $revision->previous_salary,
                'salary' => $revision->salary,
                'previous_job_title' => $revision->previous_job_title,
                'job_title' => $revision->job_title,
                'previous_grade' => $revision->previous_grade,
                'grade' => $revision->grade,
                'effective_from' => $revision->effective_from->toDateString(),
                'effective_to' => $revision->effective_to?->toDateString(),
                'change_type' => $revision->change_type->value,
                'reason' => $revision->reason,
                'approved_by' => $revision->approved_by,
                'approved_at' => $revision->approved_at?->toDateTimeString(),
                'notes' => $revision->notes,
            ],
            actor: $actor,
        );
    }

    private function blankToNull(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
