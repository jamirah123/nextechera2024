<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\DeploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\StaffSalaryChangeType;
use App\Models\EmployeePromotion;
use App\Models\Guard;
use App\Models\Position;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Support\Finance\PayrollRates;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class EmployeePromotionService
{
    public function __construct(
        private AuditService $audit,
        private GuardSalaryService $guardSalaries,
        private StaffSalaryService $staffSalaries,
        private StaffService $staff,
        private GuardService $guards,
        private OrganizationService $organization,
    ) {}

    public function schedule(
        Guard $guard,
        Position $position,
        float $newSalary,
        CarbonInterface $effectiveFrom,
        string $reason,
        ?User $actor = null,
        ?string $reference = null,
        ?string $remarks = null,
        ?int $regionId = null,
        ?UploadedFile $document = null,
    ): EmployeePromotion {
        if (! $position->is_active) {
            throw new InvalidArgumentException('Choose an active position.');
        }

        if ($newSalary < 0) {
            throw new InvalidArgumentException('Salary cannot be negative.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A promotion reason is required.');
        }

        if ($position->is_supervisor_position && $regionId === null) {
            throw new InvalidArgumentException('Assign a region when promoting an employee to a supervisor position.');
        }

        $effectiveFrom = $effectiveFrom->copy()->startOfDay();

        return DB::transaction(function () use ($guard, $position, $newSalary, $effectiveFrom, $reason, $actor, $reference, $remarks, $regionId, $document) {
            $guard = Guard::query()->lockForUpdate()->findOrFail($guard->id);

            $open = $guard->promotions()->where('status', 'scheduled')->exists();
            if ($open) {
                throw new InvalidArgumentException('This employee already has a promotion waiting for its effective date.');
            }

            $latest = $guard->promotions()->reorder()->orderByDesc('effective_from')->orderByDesc('id')->first();
            if ($latest !== null && $effectiveFrom->lessThanOrEqualTo($latest->effective_from->copy()->startOfDay())) {
                throw new InvalidArgumentException('The effective date must be after the previous position change ('.$latest->effective_from->toDateString().').');
            }

            $previousSalary = PayrollRates::salaryOn($guard, $effectiveFrom->copy()->subDay());
            if ($previousSalary <= 0) {
                $previousSalary = (float) $guard->base_shift_rate;
            }

            $promotion = EmployeePromotion::query()->create([
                'guard_id' => $guard->id,
                'previous_position_id' => $guard->position_id,
                'position_id' => $position->id,
                'previous_position' => $guard->position?->name ?? $guard->rank_designation ?? 'Guard',
                'previous_salary' => round($previousSalary, 2),
                'new_salary' => round($newSalary, 2),
                'effective_from' => $effectiveFrom->toDateString(),
                'reason' => $reason,
                'reference' => $reference,
                'remarks' => $remarks,
                'region_id' => $regionId,
                'status' => 'scheduled',
                'approved_by' => $actor?->id,
                'approved_at' => $actor ? now() : null,
                'created_by' => $actor?->id,
            ]);

            if ($document !== null) {
                $promotion->update([
                    'document_path' => $document->store('promotions/'.$guard->id, 'local'),
                ]);
            }

            $this->audit->log(
                action: 'employee.promotion_scheduled',
                summary: $guard->employment_id.' promotion to '.$position->name.' from '.$effectiveFrom->toDateString().'.',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Notice,
                subject: $guard,
                context: $this->auditContext($promotion, $position),
                actor: $actor,
            );

            if ($effectiveFrom->lessThanOrEqualTo(now()->startOfDay())) {
                $this->apply($promotion->fresh(), $actor);
            }

            return $promotion->fresh(['position', 'approver', 'creator']);
        });
    }

    public function applyDue(?User $actor = null): int
    {
        $due = EmployeePromotion::query()
            ->where('status', 'scheduled')
            ->whereDate('effective_from', '<=', now()->toDateString())
            ->orderBy('effective_from')
            ->orderBy('id')
            ->get();

        foreach ($due as $promotion) {
            $this->apply($promotion, $actor);
        }

        return $due->count();
    }

    public function apply(EmployeePromotion $promotion, ?User $actor = null): EmployeePromotion
    {
        if ($promotion->status === 'applied') {
            return $promotion;
        }

        return DB::transaction(function () use ($promotion, $actor) {
            $promotion = EmployeePromotion::query()->lockForUpdate()->findOrFail($promotion->id);
            if ($promotion->status === 'applied') {
                return $promotion;
            }

            $guard = Guard::query()->lockForUpdate()->findOrFail($promotion->guard_id);
            $position = $promotion->position()->firstOrFail();
            $effective = $promotion->effective_from->copy()->startOfDay();
            $this->preserveGuardSalary($guard, $actor);

            if ($position->leavesGuardRoster()) {
                $staff = $this->linkStaffProfile($guard, $position, (float) $promotion->new_salary, $effective, $promotion->region_id, $actor);
                $supervisor = $position->is_supervisor_position
                    ? $this->linkSupervisor($guard, $staff, $position, $effective, (int) $promotion->region_id, $promotion->reason)
                    : $guard->supervisorProfile;

                $this->guards->updateGuard($guard, [
                    'position_id' => $position->id,
                    'rank_designation' => $position->name,
                    'guard_pay_until' => $effective->copy()->subDay()->toDateString(),
                    'operational_status' => OperationalStatus::OffDuty->value,
                    'region_id' => $promotion->region_id ?? $guard->region_id,
                ], 'employee_promoted');

                $this->endActiveDeployments($guard->fresh(), $effective);

                $promotion->update([
                    'staff_id' => $staff->id,
                    'supervisor_id' => $supervisor?->id,
                ]);
            } else {
                $this->guardSalaries->increment(
                    $guard,
                    (float) $promotion->new_salary,
                    $effective,
                    \App\Enums\SalaryChangeReason::Promotion,
                    $actor,
                    $promotion->reason,
                );
                $this->guards->updateGuard($guard->fresh(), [
                    'position_id' => $position->id,
                    'rank_designation' => $position->name,
                ], 'employee_promoted');
            }

            if ($promotion->previous_position !== null) {
                EmployeePromotion::query()
                    ->where('guard_id', $guard->id)
                    ->where('status', 'applied')
                    ->whereNull('effective_to')
                    ->whereKeyNot($promotion->id)
                    ->update(['effective_to' => $effective->copy()->subDay()->toDateString()]);
            }

            $promotion->update([
                'status' => 'applied',
                'applied_at' => now(),
            ]);

            $this->audit->log(
                action: 'employee.promoted',
                summary: $guard->employment_id.' is now '.$position->name.' from '.$effective->toDateString().'.',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Notice,
                subject: $guard,
                context: $this->auditContext($promotion->fresh(), $position),
                actor: $actor ?? $promotion->approver,
            );

            return $promotion->fresh();
        });
    }

    private function preserveGuardSalary(Guard $guard, ?User $actor): void
    {
        if ($guard->salaryRevisions()->exists() || (float) $guard->base_shift_rate <= 0) {
            return;
        }

        $from = $guard->date_employed?->copy()->startOfDay() ?? now()->startOfDay();
        $this->guardSalaries->recordOpening(
            $guard,
            (float) $guard->base_shift_rate,
            $from,
            $actor,
            'Salary in force before the position change.',
        );
    }

    private function linkStaffProfile(
        Guard $guard,
        Position $position,
        float $salary,
        CarbonInterface $effective,
        ?int $regionId,
        ?User $actor,
    ): Staff {
        $staff = Staff::query()->where('employment_id', $guard->employment_id)->first()
            ?? $guard->linkedStaff;

        if ($staff === null) {
            $staff = $this->staff->createStaff([
                'employment_id' => $guard->employment_id,
                'guard_id' => $guard->id,
                'position_id' => $position->id,
                'first_name' => $guard->first_name,
                'middle_name' => $guard->middle_name,
                'last_name' => $guard->last_name,
                'gender' => $guard->gender?->value,
                'date_of_birth' => optional($guard->date_of_birth)->toDateString(),
                'phone' => $guard->phone,
                'email' => $guard->email,
                'alternative_phone' => $guard->alternative_phone,
                'address' => $guard->address,
                'national_id' => $guard->national_id,
                'date_employed' => optional($guard->date_employed)->toDateString(),
                'employment_status' => $guard->employment_status?->value,
                'job_title' => $position->name,
                'region_id' => $regionId ?? $guard->region_id,
                'monthly_salary' => 0,
                'compensation_from' => $effective->toDateString(),
                'bank_name' => $guard->bank_name,
                'bank_account' => $guard->bank_account,
                'nssf_number' => $guard->nssf_number,
                'employee_type' => 'staff',
                'notes' => 'Same employee as '.$guard->employment_id.'. Promoted from '.($guard->rank_designation ?: 'Guard').'.',
            ]);
        } else {
            $staff->update([
                'guard_id' => $staff->guard_id ?: $guard->id,
                'position_id' => $position->id,
                'job_title' => $position->name,
                'region_id' => $regionId ?? $staff->region_id ?? $guard->region_id,
                'compensation_from' => $staff->compensation_from?->toDateString() ?? $effective->toDateString(),
            ]);
        }

        $staff = $staff->fresh();
        if (! $staff->salaryRevisions()->exists()) {
            $this->staffSalaries->recordOpening($staff, $salary, $effective, $actor, $position->name, null, 'Promotion salary.');
        } else {
            $this->staffSalaries->change(
                $staff,
                $salary,
                $effective,
                $position->is_supervisor_position ? StaffSalaryChangeType::Promotion : StaffSalaryChangeType::Other,
                'Promotion to '.$position->name,
                $actor,
                $position->name,
            );
        }

        return $staff->fresh();
    }

    private function linkSupervisor(
        Guard $guard,
        Staff $staff,
        Position $position,
        CarbonInterface $effective,
        int $regionId,
        string $reason,
    ): Supervisor {
        $existing = Supervisor::query()->where('guard_id', $guard->id)->first()
            ?? $staff->supervisorProfile;

        if ($existing !== null) {
            $previousRegion = (int) $existing->region_id;
            $existing->update([
                'guard_id' => $guard->id,
                'staff_id' => $staff->id,
                'region_id' => $regionId,
                'name' => $guard->full_name,
            ]);
            $this->organization->recordSupervisorAssignment(
                $existing->fresh(),
                $previousRegion === $regionId ? null : $previousRegion,
                $regionId,
                'promotion_assignment',
                $reason,
                'Region assigned with the promotion to '.$position->name.'.',
                null,
                $effective,
            );

            return $existing->fresh();
        }

        $supervisor = Supervisor::query()->create([
            'supervisor_code' => $this->organization->nextSupervisorCode(),
            'name' => $guard->full_name,
            'phone' => $guard->phone,
            'email' => $guard->email,
            'region_id' => $regionId,
            'guard_id' => $guard->id,
            'staff_id' => $staff->id,
            'status' => 'active',
            'assignment_date' => $effective->toDateString(),
            'notes' => 'Promoted from '.($guard->rank_designation ?: 'Guard').' without a new employee record.',
        ]);

        $this->organization->recordSupervisorAssignment(
            $supervisor,
            null,
            $regionId,
            'initial_assignment',
            $reason,
            'Region assigned with the promotion to '.$position->name.'.',
            null,
            $effective,
        );

        return $supervisor;
    }

    private function endActiveDeployments(Guard $guard, CarbonInterface $effective): void
    {
        $endOn = $effective->copy()->subDay()->toDateString();

        foreach ($guard->deployments()->current()->get() as $deployment) {
            $deployment->update([
                'status' => DeploymentStatus::Ended,
                'is_current' => false,
                'end_date' => $endOn,
            ]);

            $this->audit->log(
                action: 'deployment.ended',
                summary: 'Deployment ended for '.$guard->employment_id.' on promotion.',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Notice,
                subject: $deployment,
                context: ['guard_id' => $guard->id, 'end_date' => $endOn],
            );
        }

        if ($guard->current_site_id !== null) {
            $guard->update([
                'current_site_id' => null,
                'current_supervisor_id' => null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function auditContext(EmployeePromotion $promotion, Position $position): array
    {
        return [
            'promotion_id' => $promotion->id,
            'previous_position' => $promotion->previous_position,
            'new_position' => $position->name,
            'previous_salary' => $promotion->previous_salary,
            'new_salary' => $promotion->new_salary,
            'effective_from' => $promotion->effective_from->toDateString(),
            'region_id' => $promotion->region_id,
            'reason' => $promotion->reason,
            'reference' => $promotion->reference,
            'approved_by' => $promotion->approved_by,
        ];
    }
}
