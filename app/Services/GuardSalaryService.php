<?php

namespace App\Services;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\SalaryChangeReason;
use App\Models\Guard;
use App\Models\GuardSalaryRevision;
use App\Models\User;
use App\Support\Finance\PayrollRates;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GuardSalaryService
{
    public function __construct(private AuditService $audit) {}

    public function recordOpening(
        Guard $guard,
        float $salary,
        CarbonInterface $effectiveFrom,
        ?User $actor = null,
        ?string $notes = null,
        SalaryChangeReason $reason = SalaryChangeReason::Initial,
    ): GuardSalaryRevision {
        return DB::transaction(function () use ($guard, $salary, $effectiveFrom, $actor, $notes, $reason) {
            $guard = Guard::query()->lockForUpdate()->findOrFail($guard->id);

            if ($guard->salaryRevisions()->exists()) {
                throw new InvalidArgumentException('This guard already has a salary history. Record an increment instead of a new opening salary.');
            }

            $revision = $this->insertRevision(
                $guard,
                previousSalary: null,
                salary: $salary,
                effectiveFrom: $effectiveFrom,
                effectiveTo: null,
                reason: $reason,
                actor: $actor,
                notes: $notes,
            );

            $this->syncCurrentSalary($guard);
            $this->auditChange($guard, $revision, $actor, 'Opening salary recorded.');

            return $revision;
        });
    }

    public function increment(
        Guard $guard,
        float $salary,
        CarbonInterface $effectiveFrom,
        SalaryChangeReason $reason,
        ?User $actor = null,
        ?string $notes = null,
    ): GuardSalaryRevision {
        if ($salary < 0) {
            throw new InvalidArgumentException('Salary cannot be negative.');
        }

        return DB::transaction(function () use ($guard, $salary, $effectiveFrom, $reason, $actor, $notes) {
            $guard = Guard::query()->lockForUpdate()->findOrFail($guard->id);
            $effectiveFrom = $effectiveFrom->copy()->startOfDay();

            $latest = $guard->salaryRevisions()->reorder()->orderByDesc('effective_from')->orderByDesc('id')->first();

            if ($latest === null) {
                $current = (float) $guard->base_shift_rate;
                $openingFrom = $guard->date_employed?->copy()->startOfDay();

                if ($current > 0 && $openingFrom !== null && $openingFrom->lessThan($effectiveFrom) && abs($current - $salary) > 0.009) {
                    $this->insertRevision(
                        $guard,
                        previousSalary: null,
                        salary: $current,
                        effectiveFrom: $openingFrom,
                        effectiveTo: $effectiveFrom->copy()->subDay(),
                        reason: SalaryChangeReason::Initial,
                        actor: $actor,
                        notes: 'Opening salary taken from the guard profile.',
                    );
                }

                $revision = $this->insertRevision(
                    $guard,
                    previousSalary: $current > 0 ? $current : null,
                    salary: $salary,
                    effectiveFrom: $effectiveFrom,
                    effectiveTo: null,
                    reason: $reason,
                    actor: $actor,
                    notes: $notes,
                );

                $this->syncCurrentSalary($guard);
                $this->auditChange($guard, $revision, $actor, 'Salary change recorded.');

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

            if ($latest->effective_to === null) {
                $closeOn = $effectiveFrom->copy()->subDay();

                if ($closeOn->lessThan($latest->effective_from->copy()->startOfDay())) {
                    throw new InvalidArgumentException('That effective date overlaps an existing salary record.');
                }

                $latest->update(['effective_to' => $closeOn->toDateString()]);
            }

            $revision = $this->insertRevision(
                $guard,
                previousSalary: (float) $latest->salary,
                salary: $salary,
                effectiveFrom: $effectiveFrom,
                effectiveTo: null,
                reason: $reason,
                actor: $actor,
                notes: $notes,
            );

            $this->syncCurrentSalary($guard);
            $this->auditChange($guard, $revision, $actor, 'Salary increment recorded.');

            return $revision;
        });
    }

    private function insertRevision(
        Guard $guard,
        ?float $previousSalary,
        float $salary,
        CarbonInterface $effectiveFrom,
        ?CarbonInterface $effectiveTo,
        SalaryChangeReason $reason,
        ?User $actor,
        ?string $notes,
    ): GuardSalaryRevision {
        if ($salary < 0) {
            throw new InvalidArgumentException('Salary cannot be negative.');
        }

        return GuardSalaryRevision::query()->create([
            'guard_id' => $guard->id,
            'previous_salary' => $previousSalary,
            'salary' => round($salary, 2),
            'effective_from' => $effectiveFrom->toDateString(),
            'effective_to' => $effectiveTo?->toDateString(),
            'reason' => $reason,
            'approved_by' => $actor?->id,
            'approved_at' => $actor ? now() : null,
            'notes' => $notes,
            'created_by' => $actor?->id,
        ]);
    }

    private function syncCurrentSalary(Guard $guard): void
    {
        $guard->unsetRelation('salaryRevisions');
        $guard->base_shift_rate = PayrollRates::salaryOn($guard, now());
        $guard->save();
    }

    private function auditChange(Guard $guard, GuardSalaryRevision $revision, ?User $actor, string $summary): void
    {
        $this->audit->log(
            action: 'guard.salary_changed',
            summary: $summary.' '.$guard->employment_id.' '.number_format((float) $revision->salary, 2).' from '.$revision->effective_from->toDateString().'.',
            category: AuditCategory::Hr,
            severity: AuditSeverity::Notice,
            subject: $guard,
            context: [
                'revision_id' => $revision->id,
                'previous_salary' => $revision->previous_salary,
                'salary' => $revision->salary,
                'effective_from' => $revision->effective_from->toDateString(),
                'effective_to' => $revision->effective_to?->toDateString(),
                'reason' => $revision->reason->value,
                'approved_by' => $revision->approved_by,
                'approved_at' => $revision->approved_at?->toDateTimeString(),
                'notes' => $revision->notes,
            ],
            actor: $actor,
        );
    }
}
