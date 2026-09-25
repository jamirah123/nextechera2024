<?php

namespace Database\Seeders;

use App\Enums\CompensationType;
use App\Enums\PayrollRunStatus;
use App\Models\Guard;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\User;
use App\Services\Finance\PayrollRunService;
use App\Services\GuardService;
use App\Services\StaffService;
use App\Services\SystemSettingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Ensures guard monthly gross salaries and platform payroll defaults, then
 * rebuilds company-wide payroll runs for closed months via PayrollRunService.
 */
class RepairGuardPayrollSeeder extends Seeder
{
    public function run(?string $from = null, ?string $to = null): void
    {
        $from = Carbon::parse($from ?: '2026-01-01')->startOfDay();
        $to = Carbon::parse($to ?: now()->toDateString())->startOfDay();

        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->orderBy('id')->first();

        if ($admin === null) {
            throw new \RuntimeException('No user available for payroll repair seed.');
        }

        Auth::login($admin);

        $this->ensurePayrollDefaults();
        $this->ensureGuardCompensation();
        $this->rebuildPayrollRuns($from, $to);

        Auth::logout();
    }

    private function ensurePayrollDefaults(): void
    {
        $settings = app(SystemSettingService::class);
        $current = $settings->current();
        $patch = [];

        // Company standard guard monthly gross — adjustable anytime in Admin → Payroll defaults.
        $targetMonthly = 170000.0;
        if ((float) $current->payroll_default_base_shift_rate !== $targetMonthly) {
            $patch['payroll_default_base_shift_rate'] = $targetMonthly;
        }

        if ((int) ($current->payroll_standard_shifts_per_month ?? 0) <= 0) {
            $patch['payroll_standard_shifts_per_month'] = 30;
        }

        if ((float) $current->payroll_overtime_multiplier < 1.25) {
            $patch['payroll_overtime_multiplier'] = 1.5;
        }

        if ($patch === []) {
            $settings->applyRuntimeConfig($current);
            $this->command?->info('Payroll defaults already configured.');

            return;
        }

        $updated = $settings->update($patch);
        $settings->applyRuntimeConfig($updated);
        $this->command?->info('Payroll defaults: '.collect($patch)->map(
            fn ($value, $key) => "{$key}={$value}"
        )->implode(', '));
    }

    private function ensureGuardCompensation(): void
    {
        $guards = app(GuardService::class);
        $staff = app(StaffService::class);
        $monthlyGross = (float) config('psg.payroll.default_monthly_gross', 170000);
        if ($monthlyGross <= 0) {
            $monthlyGross = 170000;
        }
        $updated = 0;

        Guard::query()
            ->whereDoesntHave('supervisorProfile')
            ->orderBy('employment_id')
            ->each(function (Guard $guard) use ($guards, $monthlyGross, &$updated): void {
                if ((float) $guard->base_shift_rate === $monthlyGross) {
                    return;
                }

                $guards->updateGuard($guard, [
                    'compensation_type' => CompensationType::Shift->value,
                    'base_shift_rate' => $monthlyGross,
                    'bank_name' => $guard->bank_name ?: 'Centenary Bank',
                    'bank_account' => $guard->bank_account ?: '30'.str_pad((string) $guard->id, 8, '0', STR_PAD_LEFT),
                    'nssf_number' => $guard->nssf_number ?: 'NSSF'.str_pad((string) $guard->id, 6, '0', STR_PAD_LEFT),
                ], 'repair_guard_payroll_salary');
                $updated++;
            });

        Staff::query()
            ->whereHas('supervisorProfile')
            ->orderBy('id')
            ->each(function (Staff $member) use ($staff): void {
                if ((float) $member->monthly_salary >= 1000000) {
                    return;
                }

                $staff->updateStaff($member, [
                    'monthly_salary' => 1200000,
                ], 'repair_supervisor_payroll_salary');
            });

        $this->command?->info("Shift-guard salaries set/updated: {$updated} → UGX ".number_format($monthlyGross, 0).'/month');
    }

    private function rebuildPayrollRuns(Carbon $from, Carbon $to): void
    {
        $payroll = app(PayrollRunService::class);
        $finance = User::query()->where('email', 'finance@platinumsecurity.local')->first() ?? Auth::user();

        $month = $from->copy()->startOfMonth();
        $lastPayrollMonth = $to->copy()->startOfMonth()->subMonth();

        $this->command?->info(sprintf(
            'Rebuilding payroll %s → %s via PayrollRunService…',
            $month->format('Y-m'),
            $lastPayrollMonth->format('Y-m'),
        ));

        while ($month->lte($lastPayrollMonth)) {
            $year = (int) $month->year;
            $monthNo = (int) $month->month;

            if (! PayrollRunService::isPeriodClosed($year, $monthNo)) {
                $month->addMonth();

                continue;
            }

            PayrollRun::query()
                ->where('period_year', $year)
                ->where('period_month', $monthNo)
                ->whereNull('region_id')
                ->whereNull('site_id')
                ->where('status', '!=', PayrollRunStatus::Cancelled->value)
                ->orderBy('id')
                ->each(function (PayrollRun $existing) use ($payroll): void {
                    try {
                        $payroll->cancel($existing);
                    } catch (Throwable $e) {
                        $this->command?->warn("Cancel {$existing->reference} failed: ".$e->getMessage());
                    }
                });

            try {
                $run = $payroll->createDraft([
                    'period_year' => $year,
                    'period_month' => $monthNo,
                    'notes' => 'Company payroll for '.$month->format('F Y').'.',
                ], $finance);

                $run = $payroll->calculate($run);
                $run = $payroll->submit($run, $finance);
                $run = $payroll->approve($run, $finance);

                if (! $month->isSameMonth($lastPayrollMonth)) {
                    $run = $payroll->markPaid($run, $finance);
                }

                $sample = $run->payslips()
                    ->where('compensation_type', CompensationType::Shift->value)
                    ->orderByDesc('gross_pay')
                    ->first();

                $this->command?->info(sprintf(
                    '%s [%s] gross=%s net=%s payslips=%d | sample shift gross=%s (shifts=%s)',
                    $month->format('Y-m'),
                    $run->status->value,
                    number_format((float) $run->gross_total, 0),
                    number_format((float) $run->net_total, 0),
                    $run->payslips()->count(),
                    $sample ? number_format((float) $sample->gross_pay, 0) : 'n/a',
                    $sample?->total_shifts ?? 'n/a',
                ));
            } catch (Throwable $e) {
                $this->command?->warn('Payroll '.$month->format('Y-m').' failed: '.$e->getMessage());
            }

            $month->addMonth();
        }
    }
}
