<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\PayrollDeductionType;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Guard;
use App\Models\GuardSalaryAdvance;
use App\Models\PayrollDeduction;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Services\AuditService;
use App\Services\Reports\MonthlyShiftCalculationService;
use App\Support\Finance\PayrollRates;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PayrollCalculationService
{
    public function __construct(
        private MonthlyShiftCalculationService $shiftTotals,
        private AuditService $audit,
    ) {
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

                if ($guard === null) {
                    continue;
                }

                $baseRate = PayrollRates::baseShiftRate($guard, $run);
                $overtimeRate = PayrollRates::overtimeShiftRate($guard, $run);

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
                ]);

                $this->applyStatutoryDeductions($payslip, $gross);
                $this->applyAdvanceDeductions($payslip, $guard);
                $this->linkShifts($payslip, $guard->id, $start, $end, $run);

                $payslip->refresh();
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
                summary: 'Payroll run '.$run->reference.' calculated for '.$count.' guards.',
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

    private function applyStatutoryDeductions(PayrollPayslip $payslip, float $gross): void
    {
        $payeRate = (float) config('psg.payroll.paye_rate', 0);
        $nssfRate = (float) config('psg.payroll.nssf_employee_rate', 5);

        if ($payeRate > 0 && $gross > 0) {
            PayrollDeduction::query()->create([
                'payroll_payslip_id' => $payslip->id,
                'type' => PayrollDeductionType::Paye,
                'label' => 'PAYE ('.rtrim(rtrim(number_format($payeRate, 2), '0'), '.').'%)',
                'amount' => round($gross * ($payeRate / 100), 2),
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

        if ($uniformCharge > 0) {
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

    private function applyAdvanceDeductions(PayrollPayslip $payslip, Guard $guard): void
    {
        $available = max(0, round((float) $payslip->gross_pay - (float) $payslip->deductions()->sum('amount'), 2));

        if ($available <= 0) {
            return;
        }

        $advances = GuardSalaryAdvance::query()
            ->where('guard_id', $guard->id)
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

    public function clearPayslips(PayrollRun $run): void
    {
        $this->restoreAdvanceBalancesForRun($run);

        $payslipIds = $run->payslips()->pluck('id');

        if ($payslipIds->isEmpty()) {
            return;
        }

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

    private function linkShifts(PayrollPayslip $payslip, int $guardId, string $start, string $end, PayrollRun $run): void
    {
        $shiftIds = Shift::query()
            ->where('guard_id', $guardId)
            ->whereBetween('shift_date', [$start, $end])
            ->when($run->region_id, fn ($q) => $q->where('region_id', $run->region_id))
            ->when($run->site_id, fn ($q) => $q->where('site_id', $run->site_id))
            ->where('status', ShiftStatus::Completed->value)
            ->whereIn('shift_type', [
                ShiftType::Normal->value,
                ShiftType::Overtime->value,
                ShiftType::Relief->value,
                ShiftType::Replacement->value,
                ShiftType::SpecialDuty->value,
            ])
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
