<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\CompensationType;
use App\Enums\PayrollDeductionType;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Guard;
use App\Models\GuardAssetRecovery;
use App\Models\GuardSalaryAdvance;
use App\Models\PayrollDeduction;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Staff;
use App\Services\ArchiveService;
use App\Services\AuditService;
use App\Services\Reports\MonthlyShiftCalculationService;
use App\Support\Finance\PayrollPayeCalculator;
use App\Support\Finance\PayrollRates;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class PayrollCalculationService
{
    public function __construct(
        private MonthlyShiftCalculationService $shiftTotals,
        private AuditService $audit,
        private ArchiveService $archive,
    ) {}

    /**
     * Recalculate any open (draft/calculated) payroll runs that cover this duty's period.
     * No-op when the month has no open run yet.
     */
    public function refreshOpenRunsForShift(Shift $shift): int
    {
        $date = $shift->shift_date;
        if ($date === null) {
            return 0;
        }

        $year = (int) $date->format('Y');
        $month = (int) $date->format('m');
        $refreshed = 0;

        PayrollRun::query()
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->whereIn('status', [PayrollRunStatus::Draft->value, PayrollRunStatus::Calculated->value])
            ->where(function ($query) use ($shift): void {
                $query->whereNull('region_id')
                    ->orWhere('region_id', $shift->region_id);
            })
            ->where(function ($query) use ($shift): void {
                $query->whereNull('site_id')
                    ->orWhere('site_id', $shift->site_id);
            })
            ->orderBy('id')
            ->each(function (PayrollRun $run) use (&$refreshed): void {
                try {
                    $this->calculate($run);
                    $refreshed++;
                } catch (Throwable) {
                    // Leave the run unchanged if recalculation is blocked mid-flow.
                }
            });

        return $refreshed;
    }

    public function calculate(PayrollRun $run): PayrollRun
    {
        if (! $run->status->canCalculate()) {
            throw new InvalidArgumentException('This payroll run cannot be calculated in its current status.');
        }

        return DB::transaction(function () use ($run) {
            $this->clearPayslips($run);

            $filters = [
                'year' => (int) $run->period_year,
                'month' => (int) $run->period_month,
                'region_id' => $run->region_id,
                'site_id' => $run->site_id,
            ];

            $rows = $this->shiftTotals->calculate($filters)
                ->filter(fn (array $row) => (int) $row['total_shifts'] > 0);

            $start = Carbon::parse($run->period_start)->toDateString();
            $end = Carbon::parse($run->period_end)->toDateString();

            $grossTotal = 0.0;
            $deductionsTotal = 0.0;
            $netTotal = 0.0;
            $count = 0;

            foreach ($rows as $row) {
                $guard = Guard::query()->find($row['guard_id']);

                if ($guard === null || $guard->isSalaryStaff()) {
                    continue;
                }

                $payslip = $this->createShiftPayslip($run, $guard, $row, $start, $end);

                $grossTotal += (float) $payslip->gross_pay;
                $deductionsTotal += (float) $payslip->total_deductions;
                $netTotal += (float) $payslip->net_pay;
                $count++;
            }

            foreach ($this->salaryGuardsForRun($run) as $guard) {
                $payslip = $this->createSalaryGuardPayslip($run, $guard);

                $grossTotal += (float) $payslip->gross_pay;
                $deductionsTotal += (float) $payslip->total_deductions;
                $netTotal += (float) $payslip->net_pay;
                $count++;
            }

            foreach ($this->staffForRun($run) as $member) {
                $payslip = $this->createStaffPayslip($run, $member);

                $grossTotal += (float) $payslip->gross_pay;
                $deductionsTotal += (float) $payslip->total_deductions;
                $netTotal += (float) $payslip->net_pay;
                $count++;
            }

            $run->update([
                'status' => PayrollRunStatus::Calculated,
                'guard_count' => $count,
                'gross_total' => round($grossTotal, 2),
                'deductions_total' => round($deductionsTotal, 2),
                'net_total' => round($netTotal, 2),
                'calculated_at' => now(),
            ]);

            $this->audit->log(
                action: 'payroll.calculated',
                summary: 'Payroll run '.$run->reference.' calculated for '.$count.' staff.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $run,
                context: [
                    'guard_count' => $count,
                    'gross_total' => $run->gross_total,
                    'net_total' => $run->net_total,
                ],
            );

            return $run->fresh(['payslips.deductions', 'region', 'site']);
        });
    }

    /**
     * @param  array{type: string, label?: string|null, amount: float|int|string}  $data
     */
    public function addManualDeduction(PayrollPayslip $payslip, array $data): PayrollPayslip
    {
        $run = $payslip->run;

        if (! $run->status->canAddDeductions()) {
            throw new InvalidArgumentException('Deductions can only be added while the payroll run is in Calculated status.');
        }

        $type = PayrollDeductionType::from($data['type']);
        $amount = round((float) $data['amount'], 2);

        if ($amount <= 0) {
            throw new InvalidArgumentException('Deduction amount must be greater than zero.');
        }

        return DB::transaction(function () use ($payslip, $type, $amount, $data, $run) {
            PayrollDeduction::query()->create([
                'payroll_payslip_id' => $payslip->id,
                'type' => $type,
                'label' => $data['label'] ?? $type->label(),
                'amount' => $amount,
                'is_statutory' => false,
            ]);

            $this->recalculatePayslipTotals($payslip);
            $this->recalculateRunTotals($run);

            return $payslip->fresh(['deductions', 'run']);
        });
    }

    public function removeDeduction(PayrollDeduction $deduction): void
    {
        $payslip = $deduction->payslip()->with('run')->firstOrFail();
        $run = $payslip->run;

        if (! $run->status->canAddDeductions()) {
            throw new InvalidArgumentException('Deductions can only be removed while the payroll run is in Calculated status.');
        }

        if ($deduction->is_statutory) {
            throw new InvalidArgumentException('Statutory deductions cannot be removed individually. Recalculate the payroll run instead.');
        }

        if ($deduction->guard_advance_id) {
            throw new InvalidArgumentException('Advance recoveries are managed automatically. Recalculate the payroll run to adjust.');
        }

        DB::transaction(function () use ($deduction, $payslip, $run): void {
            $deduction->delete();
            $this->recalculatePayslipTotals($payslip);
            $this->recalculateRunTotals($run);
        });
    }

    private function applyStatutoryDeductions(PayrollPayslip $payslip, float $gross, bool $includeUniform = true): void
    {
        $nssfRate = (float) config('psg.payroll.nssf_employee_rate', 5);
        $paye = $this->calculatePaye($gross);

        if ($paye > 0) {
            PayrollDeduction::query()->create([
                'payroll_payslip_id' => $payslip->id,
                'type' => PayrollDeductionType::Paye,
                'label' => config('psg.payroll.use_progressive_paye', true)
                    ? PayrollPayeCalculator::label()
                    : 'PAYE ('.rtrim(rtrim(number_format((float) config('psg.payroll.paye_rate', 0), 2), '0'), '.').'%)',
                'amount' => $paye,
                'is_statutory' => true,
            ]);
        }

        if ($nssfRate > 0 && $gross > 0) {
            PayrollDeduction::query()->create([
                'payroll_payslip_id' => $payslip->id,
                'type' => PayrollDeductionType::Nssf,
                'label' => 'NSSF employee ('.rtrim(rtrim(number_format($nssfRate, 2), '0'), '.').'%)',
                'amount' => round($gross * ($nssfRate / 100), 2),
                'is_statutory' => true,
            ]);
        }

        $uniformCharge = (float) config('psg.payroll.uniform_charge', 0);

        if ($includeUniform && $uniformCharge > 0) {
            PayrollDeduction::query()->create([
                'payroll_payslip_id' => $payslip->id,
                'type' => PayrollDeductionType::Uniform,
                'label' => 'Uniform charge',
                'amount' => round(min($uniformCharge, $gross), 2),
                'is_statutory' => true,
            ]);
        }

        $this->recalculatePayslipTotals($payslip);
    }

    private function calculatePaye(float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        if (config('psg.payroll.use_progressive_paye', true)) {
            return PayrollPayeCalculator::monthlyTax($gross);
        }

        $payeRate = (float) config('psg.payroll.paye_rate', 0);

        if ($payeRate <= 0) {
            return 0.0;
        }

        return round($gross * ($payeRate / 100), 2);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function createShiftPayslip(PayrollRun $run, Guard $guard, array $row, string $start, string $end): PayrollPayslip
    {
        $effectiveEnd = PayrollRates::effectiveShiftEnd($guard, $run)->toDateString();
        $baseRate = PayrollRates::baseShiftRate($guard, $run);
        $overtimeRate = PayrollRates::overtimeShiftRate($guard, $run);

        if ($effectiveEnd < $end) {
            $row = $this->shiftTotals->guardRowForPeriod($guard->id, $start, $effectiveEnd, $run);
        }

        $gross = round(
            ((int) $row['normal_shifts'] * $baseRate)
            + ((int) $row['overtime_shifts'] * $overtimeRate)
            + ((int) $row['relief_shifts'] * $baseRate)
            + ((int) $row['replacement_shifts'] * $baseRate)
            + ((int) $row['special_duty_shifts'] * $baseRate),
            2,
        );

        $payslip = PayrollPayslip::query()->create([
            'payroll_run_id' => $run->id,
            'guard_id' => $guard->id,
            'employment_id' => $row['employment_id'],
            'full_name' => $row['full_name'],
            'compensation_type' => CompensationType::Shift,
            'normal_shifts' => $row['normal_shifts'],
            'overtime_shifts' => $row['overtime_shifts'],
            'relief_shifts' => $row['relief_shifts'],
            'replacement_shifts' => $row['replacement_shifts'],
            'special_duty_shifts' => $row['special_duty_shifts'],
            'total_shifts' => $row['total_shifts'],
            'base_shift_rate' => $baseRate,
            'overtime_shift_rate' => $overtimeRate,
            'gross_pay' => $gross,
            'bank_name' => $guard->bank_name,
            'bank_account' => $guard->bank_account,
            'nssf_number' => $guard->nssf_number,
            'payroll_email' => $guard->email,
        ]);

        $this->applyStatutoryDeductions($payslip, $gross, includeUniform: true);
        $this->applyAdvanceDeductions($payslip, guardId: $guard->id);
        $this->applyAssetRecoveryDeductions($payslip, guardId: $guard->id);
        $this->linkShifts($payslip, $guard->id, $start, $effectiveEnd, $run);

        return $payslip->refresh();
    }

    private function createSalaryGuardPayslip(PayrollRun $run, Guard $guard): PayrollPayslip
    {
        $monthlyGross = PayrollRates::monthlyGross($guard);
        $salaryGross = PayrollRates::fixedPeriodGross($guard, $run);
        $overtime = $this->salaryOvertimeEarnings($guard, $monthlyGross, $run);
        $gross = round($salaryGross + $overtime['pay'], 2);

        $payslip = PayrollPayslip::query()->create([
            'payroll_run_id' => $run->id,
            'guard_id' => $guard->id,
            'employment_id' => $guard->employment_id,
            'full_name' => $guard->full_name,
            'compensation_type' => CompensationType::Salary,
            'normal_shifts' => 0,
            'overtime_shifts' => $overtime['count'],
            'relief_shifts' => 0,
            'replacement_shifts' => 0,
            'special_duty_shifts' => 0,
            'total_shifts' => $overtime['count'],
            'base_shift_rate' => $monthlyGross,
            'overtime_shift_rate' => $overtime['rate'],
            'gross_pay' => $gross,
            'bank_name' => $guard->bank_name,
            'bank_account' => $guard->bank_account,
            'nssf_number' => $guard->nssf_number,
            'payroll_email' => $guard->email,
        ]);

        $this->applyStatutoryDeductions($payslip, $gross, includeUniform: true);
        $this->applyAdvanceDeductions($payslip, guardId: $guard->id);
        $this->applyAssetRecoveryDeductions($payslip, guardId: $guard->id);

        if ($overtime['count'] > 0) {
            $this->linkShifts(
                $payslip,
                $guard->id,
                $run->period_start->toDateString(),
                PayrollRates::effectiveShiftEnd($guard, $run)->toDateString(),
                $run,
                overtimeOnly: true,
            );
        }

        return $payslip->refresh();
    }

    private function createStaffPayslip(PayrollRun $run, Staff $member): PayrollPayslip
    {
        $member->loadMissing(['supervisorProfile.guardProfile']);

        $monthlyGross = PayrollRates::staffMonthlyGross($member);
        $salaryGross = PayrollRates::staffPeriodGross($member, $run);

        $coverGuard = $member->supervisorProfile?->guardProfile;
        $overtime = $coverGuard !== null
            ? $this->salaryOvertimeEarnings($coverGuard, $monthlyGross, $run)
            : ['count' => 0, 'rate' => 0.0, 'pay' => 0.0];

        $gross = round($salaryGross + $overtime['pay'], 2);

        $payslip = PayrollPayslip::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $member->id,
            'employment_id' => $member->employment_id,
            'full_name' => $member->full_name,
            'compensation_type' => CompensationType::Salary,
            'normal_shifts' => 0,
            'overtime_shifts' => $overtime['count'],
            'relief_shifts' => 0,
            'replacement_shifts' => 0,
            'special_duty_shifts' => 0,
            'total_shifts' => $overtime['count'],
            'base_shift_rate' => $monthlyGross,
            'overtime_shift_rate' => $overtime['rate'],
            'gross_pay' => $gross,
            'bank_name' => $member->bank_name,
            'bank_account' => $member->bank_account,
            'nssf_number' => $member->nssf_number,
            'tin_number' => $member->tin_number,
            'payroll_email' => $member->email,
        ]);

        $this->applyStatutoryDeductions($payslip, $gross, includeUniform: false);
        $this->applyAdvanceDeductions($payslip, staffId: $member->id);

        if ($coverGuard !== null && $overtime['count'] > 0) {
            $this->linkShifts(
                $payslip,
                $coverGuard->id,
                $run->period_start->toDateString(),
                $run->period_end->toDateString(),
                $run,
                overtimeOnly: true,
            );
        }

        return $payslip->refresh();
    }

    /**
     * @return array{count: int, rate: float, pay: float}
     */
    private function salaryOvertimeEarnings(Guard $guard, float $monthlyGross, PayrollRun $run): array
    {
        $end = PayrollRates::effectiveShiftEnd($guard, $run)->toDateString();
        $count = $this->overtimeShiftCountForGuard(
            $guard->id,
            $run->period_start->toDateString(),
            $end,
            $run,
        );

        if ($count <= 0) {
            return ['count' => 0, 'rate' => 0.0, 'pay' => 0.0];
        }

        $rate = PayrollRates::salaryOvertimeShiftRate($monthlyGross, $guard, $run);

        if ($rate <= 0) {
            return ['count' => $count, 'rate' => 0.0, 'pay' => 0.0];
        }

        return [
            'count' => $count,
            'rate' => $rate,
            'pay' => round($count * $rate, 2),
        ];
    }

    private function overtimeShiftCountForGuard(int $guardId, string $start, string $end, PayrollRun $run): int
    {
        return Shift::query()
            ->where('guard_id', $guardId)
            ->whereBetween('shift_date', [$start, $end])
            ->when($run->region_id, fn ($q) => $q->where('region_id', $run->region_id))
            ->when($run->site_id, fn ($q) => $q->where('site_id', $run->site_id))
            ->whereIn('status', ShiftStatus::payableValues())
            ->where('shift_type', ShiftType::Overtime)
            ->count();
    }

    /** @return Collection<int, Staff> */
    private function staffForRun(PayrollRun $run): Collection
    {
        if ($run->site_id) {
            return collect();
        }

        return Staff::query()
            ->employedDuringPeriod($run->period_start, $run->period_end)
            ->when($run->region_id, fn ($q) => $q->where('region_id', $run->region_id))
            ->orderBy('employment_id')
            ->get()
            ->filter(function (Staff $member) use ($run) {
                $salary = PayrollRates::staffPeriodGross($member, $run);
                if ($salary > 0) {
                    return true;
                }

                // Supervisor with no salary yet but overtime cover shifts still need a payslip line.
                $member->loadMissing('supervisorProfile');
                $guardId = $member->supervisorProfile?->guard_id;

                return $guardId !== null
                    && $this->overtimeShiftCountForGuard(
                        (int) $guardId,
                        $run->period_start->toDateString(),
                        $run->period_end->toDateString(),
                        $run,
                    ) > 0;
            });
    }

    /** @return Collection<int, Guard> */
    private function salaryGuardsForRun(PayrollRun $run): Collection
    {
        return Guard::query()
            ->onSalaryPay()
            // Supervisors are paid via their staff payslip (fixed salary + optional overtime).
            ->whereDoesntHave('supervisorProfile')
            ->employedDuringPeriod($run->period_start, $run->period_end)
            ->when($run->region_id, fn ($q) => $q->where('region_id', $run->region_id))
            ->when($run->site_id, fn ($q) => $q->where('current_site_id', $run->site_id))
            ->orderBy('employment_id')
            ->get()
            ->filter(fn (Guard $guard) => PayrollRates::fixedPeriodGross($guard, $run) > 0
                || $this->overtimeShiftCountForGuard(
                    $guard->id,
                    $run->period_start->toDateString(),
                    PayrollRates::effectiveShiftEnd($guard, $run)->toDateString(),
                    $run,
                ) > 0);
    }

    private function applyAdvanceDeductions(PayrollPayslip $payslip, ?int $guardId = null, ?int $staffId = null): void
    {
        $available = max(0, round((float) $payslip->gross_pay - (float) $payslip->deductions()->sum('amount'), 2));

        if ($available <= 0) {
            return;
        }

        $advances = GuardSalaryAdvance::query()
            ->when($guardId, fn ($q) => $q->where('guard_id', $guardId))
            ->when($staffId, fn ($q) => $q->where('staff_id', $staffId))
            ->where('is_active', true)
            ->where('balance_remaining', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($advances as $advance) {
            if ($available <= 0) {
                break;
            }

            $installment = $advance->monthly_installment !== null
                ? (float) $advance->monthly_installment
                : (float) $advance->balance_remaining;

            $amount = min((float) $advance->balance_remaining, $installment, $available);

            if ($amount <= 0) {
                continue;
            }

            PayrollDeduction::query()->create([
                'payroll_payslip_id' => $payslip->id,
                'type' => PayrollDeductionType::Advance,
                'label' => $advance->label.' (recovery)',
                'amount' => round($amount, 2),
                'is_statutory' => false,
                'guard_advance_id' => $advance->id,
            ]);

            $newBalance = max(0, round((float) $advance->balance_remaining - $amount, 2));
            $advance->update([
                'balance_remaining' => $newBalance,
                'is_active' => $newBalance > 0,
            ]);

            $available = round($available - $amount, 2);
        }

        $this->recalculatePayslipTotals($payslip);
    }

    private function applyAssetRecoveryDeductions(PayrollPayslip $payslip, ?int $guardId = null): void
    {
        if ($guardId === null) {
            return;
        }

        $available = max(0, round((float) $payslip->gross_pay - (float) $payslip->deductions()->sum('amount'), 2));

        if ($available <= 0) {
            return;
        }

        $recoveries = GuardAssetRecovery::query()
            ->where('guard_id', $guardId)
            ->where('is_active', true)
            ->where('balance_remaining', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($recoveries as $recovery) {
            if ($available <= 0) {
                break;
            }

            $installment = $recovery->monthly_installment !== null
                ? (float) $recovery->monthly_installment
                : (float) $recovery->balance_remaining;

            $amount = min((float) $recovery->balance_remaining, $installment, $available);

            if ($amount <= 0) {
                continue;
            }

            PayrollDeduction::query()->create([
                'payroll_payslip_id' => $payslip->id,
                'type' => PayrollDeductionType::AssetRecovery,
                'label' => $recovery->label.' (asset recovery)',
                'amount' => round($amount, 2),
                'is_statutory' => false,
                'guard_asset_recovery_id' => $recovery->id,
            ]);

            $newBalance = max(0, round((float) $recovery->balance_remaining - $amount, 2));
            $recovery->update([
                'balance_remaining' => $newBalance,
                'is_active' => $newBalance > 0,
            ]);

            if ($recovery->line) {
                $line = $recovery->line;
                $line->update([
                    'recovered_amount' => round((float) $line->recovered_amount + $amount, 2),
                ]);
            }

            $available = round($available - $amount, 2);
        }

        $this->recalculatePayslipTotals($payslip);
    }

    public function clearPayslips(PayrollRun $run): void
    {
        $this->restoreAdvanceBalancesForRun($run);
        $this->restoreAssetRecoveryBalancesForRun($run);

        $payslipIds = $run->payslips()->pluck('id');

        if ($payslipIds->isEmpty()) {
            return;
        }

        $run->payslips()->with('deductions')->get()->each(function (PayrollPayslip $payslip): void {
            $this->archive->recordSnapshot($payslip, 'payroll.payslips_cleared');
        });

        DB::table('payroll_payslip_shifts')->whereIn('payroll_payslip_id', $payslipIds)->delete();
        PayrollDeduction::query()->whereIn('payroll_payslip_id', $payslipIds)->delete();
        PayrollPayslip::query()->whereIn('id', $payslipIds)->delete();

        $run->update([
            'guard_count' => 0,
            'gross_total' => 0,
            'deductions_total' => 0,
            'net_total' => 0,
            'calculated_at' => null,
        ]);
    }

    private function restoreAdvanceBalancesForRun(PayrollRun $run): void
    {
        PayrollDeduction::query()
            ->whereNotNull('guard_advance_id')
            ->whereHas('payslip', fn ($query) => $query->where('payroll_run_id', $run->id))
            ->with('guardAdvance')
            ->get()
            ->each(function (PayrollDeduction $deduction): void {
                $advance = $deduction->guardAdvance;

                if ($advance === null) {
                    return;
                }

                $advance->update([
                    'balance_remaining' => round((float) $advance->balance_remaining + (float) $deduction->amount, 2),
                    'is_active' => true,
                ]);
            });
    }

    private function restoreAssetRecoveryBalancesForRun(PayrollRun $run): void
    {
        PayrollDeduction::query()
            ->whereNotNull('guard_asset_recovery_id')
            ->whereHas('payslip', fn ($query) => $query->where('payroll_run_id', $run->id))
            ->with(['guardAssetRecovery.line'])
            ->get()
            ->each(function (PayrollDeduction $deduction): void {
                $recovery = $deduction->guardAssetRecovery;

                if ($recovery === null) {
                    return;
                }

                $recovery->update([
                    'balance_remaining' => round((float) $recovery->balance_remaining + (float) $deduction->amount, 2),
                    'is_active' => true,
                ]);

                if ($recovery->line) {
                    $recovery->line->update([
                        'recovered_amount' => max(0, round((float) $recovery->line->recovered_amount - (float) $deduction->amount, 2)),
                    ]);
                }
            });
    }

    private function linkShifts(
        PayrollPayslip $payslip,
        int $guardId,
        string $start,
        string $end,
        PayrollRun $run,
        bool $overtimeOnly = false,
    ): void {
        $types = $overtimeOnly
            ? [ShiftType::Overtime->value]
            : [
                ShiftType::Normal->value,
                ShiftType::Overtime->value,
                ShiftType::Relief->value,
                ShiftType::Replacement->value,
                ShiftType::SpecialDuty->value,
            ];

        $shiftIds = Shift::query()
            ->where('guard_id', $guardId)
            ->whereBetween('shift_date', [$start, $end])
            ->when($run->region_id, fn ($q) => $q->where('region_id', $run->region_id))
            ->when($run->site_id, fn ($q) => $q->where('site_id', $run->site_id))
            ->whereIn('status', ShiftStatus::payableValues())
            ->whereIn('shift_type', $types)
            ->pluck('id');

        if ($shiftIds->isNotEmpty()) {
            $payslip->shifts()->sync($shiftIds);
        }
    }

    private function recalculatePayslipTotals(PayrollPayslip $payslip): void
    {
        $deductions = (float) $payslip->deductions()->sum('amount');
        $net = max(0, round((float) $payslip->gross_pay - $deductions, 2));

        $payslip->update([
            'total_deductions' => round($deductions, 2),
            'net_pay' => $net,
        ]);
    }

    private function recalculateRunTotals(PayrollRun $run): void
    {
        $totals = PayrollPayslip::query()
            ->where('payroll_run_id', $run->id)
            ->selectRaw('COUNT(*) as guard_count, COALESCE(SUM(gross_pay), 0) as gross_total, COALESCE(SUM(total_deductions), 0) as deductions_total, COALESCE(SUM(net_pay), 0) as net_total')
            ->first();

        $run->update([
            'guard_count' => (int) ($totals->guard_count ?? 0),
            'gross_total' => round((float) ($totals->gross_total ?? 0), 2),
            'deductions_total' => round((float) ($totals->deductions_total ?? 0), 2),
            'net_total' => round((float) ($totals->net_total ?? 0), 2),
        ]);
    }
}
