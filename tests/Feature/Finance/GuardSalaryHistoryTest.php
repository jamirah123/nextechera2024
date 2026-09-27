<?php

namespace Tests\Feature\Finance;

use App\Enums\CompensationType;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\PayrollDeductionType;
use App\Enums\SalaryChangeReason;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\GuardSalaryAdvance;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\Finance\PayrollCalculationService;
use App\Services\Finance\PayrollRunService;
use App\Services\GuardSalaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class GuardSalaryHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_guards_with_no_salary_change_are_paid_their_own_rates(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();
        $month = $this->closedMonth();

        $lower = $this->guardOn($site, 150000);
        $higher = $this->guardOn($site, 220000);

        $this->recordShift($lower, $site, $month->copy()->day(3), ShiftType::Normal);
        $this->recordShift($higher, $site, $month->copy()->day(4), ShiftType::Normal);

        $run = $this->calculateMonth($finance, $month);

        $lowerSlip = $this->payslipFor($run, $lower);
        $higherSlip = $this->payslipFor($run, $higher);

        $this->assertSame($this->perShift(150000), (float) $lowerSlip->gross_pay);
        $this->assertSame($this->perShift(220000), (float) $higherSlip->gross_pay);
        $this->assertNull($lowerSlip->salary_breakdown);
        $this->assertNull($higherSlip->salary_breakdown);
    }

    public function test_month_start_increment_uses_the_old_salary_before_and_the_new_salary_after(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $site = Site::factory()->create();
        $before = $this->closedMonth(1);
        $after = $this->closedMonth();

        $guard = $this->guardOn($site, 170000);
        $salaries = app(GuardSalaryService::class);
        $salaries->recordOpening($guard, 170000, Carbon::parse('2025-01-01'), $hr, 'Opening salary');
        $salaries->increment($guard, 200000, $after->copy()->startOfMonth(), SalaryChangeReason::LengthOfService, $hr, 'Length of service');

        $this->recordShift($guard, $site, $before->copy()->day(2), ShiftType::Normal);
        $this->recordShift($guard, $site, $after->copy()->day(2), ShiftType::Normal);

        $beforeRun = $this->calculateMonth($finance, $before);
        $afterRun = $this->calculateMonth($finance, $after);

        $this->assertSame($this->perShift(170000), (float) $this->payslipFor($beforeRun, $guard)->gross_pay);
        $this->assertSame($this->perShift(200000), (float) $this->payslipFor($afterRun, $guard)->gross_pay);
        $this->assertSame(
            $after->copy()->startOfMonth()->subDay()->toDateString(),
            $guard->salaryRevisions()->orderBy('effective_from')->first()->effective_to->toDateString(),
        );
    }

    public function test_multiple_increments_select_the_salary_in_force_for_each_period(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $site = Site::factory()->create();
        $early = $this->closedMonth(2);
        $middle = $this->closedMonth(1);
        $later = $this->closedMonth();

        $guard = $this->guardOn($site, 150000);
        $salaries = app(GuardSalaryService::class);
        $salaries->recordOpening($guard, 150000, Carbon::parse('2024-01-01'), $hr, 'Opening salary');
        $salaries->increment($guard, 180000, $middle->copy()->startOfMonth(), SalaryChangeReason::Performance, $hr, 'Performance');
        $salaries->increment($guard, 220000, $later->copy()->startOfMonth(), SalaryChangeReason::Promotion, $hr, 'Promotion');

        $this->recordShift($guard, $site, $early->copy()->day(4), ShiftType::Normal);
        $this->recordShift($guard, $site, $middle->copy()->day(4), ShiftType::Normal);
        $this->recordShift($guard, $site, $later->copy()->day(4), ShiftType::Normal);

        $this->assertSame($this->perShift(150000), (float) $this->payslipFor($this->calculateMonth($finance, $early), $guard)->gross_pay);
        $this->assertSame($this->perShift(180000), (float) $this->payslipFor($this->calculateMonth($finance, $middle), $guard)->gross_pay);
        $this->assertSame($this->perShift(220000), (float) $this->payslipFor($this->calculateMonth($finance, $later), $guard)->gross_pay);
        $this->assertSame(3, $guard->salaryRevisions()->count());
        $this->assertSame(150000.0, (float) $guard->salaryRevisions()->orderBy('effective_from')->first()->salary);
    }

    public function test_mid_month_increment_splits_shift_pay_and_prorates_fixed_salary(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $site = Site::factory()->create();
        $month = $this->closedMonth();
        $changeOn = $month->copy()->day(15);
        $days = $month->daysInMonth;

        $shiftGuard = $this->guardOn($site, 180000);
        $salaryGuard = $this->guardOn($site, 300000, CompensationType::Salary);

        $salaries = app(GuardSalaryService::class);
        $salaries->recordOpening($shiftGuard, 180000, Carbon::parse('2025-01-01'), $hr, 'Opening salary');
        $salaries->increment($shiftGuard, 240000, $changeOn, SalaryChangeReason::LengthOfService, $hr, 'Mid-month length of service');
        $salaries->recordOpening($salaryGuard, 300000, Carbon::parse('2025-01-01'), $hr, 'Opening salary');
        $salaries->increment($salaryGuard, 450000, $changeOn, SalaryChangeReason::ContractChange, $hr, 'Mid-month contract change');

        $this->recordShift($shiftGuard, $site, $month->copy()->day(1), ShiftType::Normal);
        $this->recordShift($shiftGuard, $site, $month->copy()->day(20), ShiftType::Normal);

        $run = $this->calculateMonth($finance, $month);
        $shiftSlip = $this->payslipFor($run, $shiftGuard);
        $salarySlip = $this->payslipFor($run, $salaryGuard);

        $expectedShift = round($this->perShift(180000) + $this->perShift(240000), 2);
        $expectedSalary = round(300000 * (14 / $days), 2) + round(450000 * (($days - 14) / $days), 2);

        $this->assertSame($expectedShift, (float) $shiftSlip->gross_pay);
        $this->assertTrue($shiftSlip->hasMixedSalary());
        $this->assertSame($expectedSalary, (float) $salarySlip->gross_pay);
        $this->assertTrue($salarySlip->hasMixedSalary());
        $this->assertCount(2, $salarySlip->salary_breakdown);
    }

    public function test_overtime_and_deductions_reconcile_after_an_increment(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $site = Site::factory()->create();
        $month = $this->closedMonth();

        $shiftGuard = $this->guardOn($site, 170000);
        $salaryGuard = $this->guardOn($site, 400000, CompensationType::Salary);
        $salaries = app(GuardSalaryService::class);
        $salaries->recordOpening($shiftGuard, 170000, Carbon::parse('2025-01-01'), $hr, 'Opening salary');
        $salaries->increment($shiftGuard, 210000, $month->copy()->startOfMonth(), SalaryChangeReason::Promotion, $hr, 'Promotion');
        $salaries->recordOpening($salaryGuard, 400000, Carbon::parse('2025-01-01'), $hr, 'Opening salary');
        $salaries->increment($salaryGuard, 500000, $month->copy()->startOfMonth(), SalaryChangeReason::LengthOfService, $hr, 'Length of service');

        $this->recordShift($shiftGuard, $site, $month->copy()->day(2), ShiftType::Normal);
        $this->recordShift($shiftGuard, $site, $month->copy()->day(3), ShiftType::Normal);
        $this->recordShift($shiftGuard, $site, $month->copy()->day(4), ShiftType::Overtime);

        GuardSalaryAdvance::query()->create([
            'guard_id' => $shiftGuard->id,
            'label' => 'Kit advance',
            'original_amount' => 5000,
            'balance_remaining' => 5000,
            'monthly_installment' => 5000,
            'is_active' => true,
        ]);
        GuardSalaryAdvance::query()->create([
            'guard_id' => $salaryGuard->id,
            'label' => 'Salary advance',
            'original_amount' => 10000,
            'balance_remaining' => 10000,
            'monthly_installment' => 10000,
            'is_active' => true,
        ]);

        $run = $this->calculateMonth($finance, $month);
        $shiftSlip = $this->payslipFor($run, $shiftGuard);
        $salarySlip = $this->payslipFor($run, $salaryGuard);

        $expectedShiftGross = round(($this->perShift(210000) * 2) + $this->overtimeRate(210000), 2);
        $this->assertSame($expectedShiftGross, (float) $shiftSlip->gross_pay);
        $this->assertSame(2, $shiftSlip->normal_shifts);
        $this->assertSame(1, $shiftSlip->overtime_shifts);
        $this->assertSame(500000.0, (float) $salarySlip->gross_pay);

        foreach ([$shiftSlip, $salarySlip] as $payslip) {
            $payslip->load('deductions');
            $this->assertTrue($payslip->deductions->contains('type', PayrollDeductionType::Nssf));
            $this->assertTrue($payslip->deductions->contains('type', PayrollDeductionType::Advance));
            $this->assertEqualsWithDelta(
                (float) $payslip->gross_pay - (float) $payslip->total_deductions,
                (float) $payslip->net_pay,
                0.01,
            );
        }

        $this->assertGreaterThan(0, (float) $salarySlip->deductions()->where('type', PayrollDeductionType::Paye)->value('amount'));
        $this->assertSame(10000.0, (float) $salarySlip->deductions()->where('type', PayrollDeductionType::Advance)->value('amount'));
        $this->assertSame(5000.0, (float) $shiftSlip->deductions()->where('type', PayrollDeductionType::Advance)->value('amount'));
    }

    public function test_approved_payroll_is_unchanged_by_a_later_salary_increase(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $site = Site::factory()->create();
        $before = $this->closedMonth(1);
        $after = $this->closedMonth();

        $guard = $this->guardOn($site, 160000);
        app(GuardSalaryService::class)->recordOpening($guard, 160000, Carbon::parse('2025-01-01'), $hr, 'Opening salary');
        $this->recordShift($guard, $site, $before->copy()->day(6), ShiftType::Normal);

        $run = $this->calculateMonth($finance, $before);
        $payslip = $this->payslipFor($run, $guard);
        $gross = (float) $payslip->gross_pay;
        $net = (float) $payslip->net_pay;
        $this->assertSame($this->perShift(160000), $gross);

        $payroll = app(PayrollRunService::class);
        $payroll->submit($run, $finance);
        $payroll->approve($run, $finance);

        app(GuardSalaryService::class)->increment(
            $guard,
            250000,
            $after->copy()->startOfMonth(),
            SalaryChangeReason::Promotion,
            $hr,
            'Later promotion',
        );

        try {
            app(PayrollCalculationService::class)->calculate($run->fresh());
            $this->fail('Approved payroll was recalculated.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('cannot be calculated', $exception->getMessage());
        }

        $payslip->refresh();
        $this->assertSame($gross, (float) $payslip->gross_pay);
        $this->assertSame($net, (float) $payslip->net_pay);
        $this->assertSame(250000.0, (float) $guard->fresh()->base_shift_rate);
    }

    public function test_only_authorized_users_can_record_salary_changes(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();
        $region = \App\Models\Region::factory()->create();

        $this->actingAs($hr)
            ->post(route('guards.store'), [
                'employment_id' => 'PSG9001',
                'first_name' => 'Amina',
                'last_name' => 'Nakato',
                'region_id' => $region->id,
                'date_employed' => now()->subYears(3)->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'base_shift_rate' => 170000,
            ])
            ->assertRedirect();

        $guard = Guard::query()->where('employment_id', 'PSG9001')->firstOrFail();
        $this->assertSame(1, $guard->salaryRevisions()->count());
        $this->assertSame(170000.0, (float) $guard->salaryRevisions()->first()->salary);

        $changeOn = now()->startOfMonth()->subMonth()->startOfDay();

        $this->actingAs($shiftManager)
            ->post(route('guards.salary-revisions.store', $guard), [
                'salary' => 190000,
                'effective_from' => $changeOn->toDateString(),
                'reason' => SalaryChangeReason::LengthOfService->value,
            ])
            ->assertForbidden();

        $this->actingAs($hr)
            ->post(route('guards.salary-revisions.store', $guard), [
                'salary' => 180000,
                'effective_from' => $changeOn->toDateString(),
                'reason' => SalaryChangeReason::LengthOfService->value,
                'notes' => 'Length of service review',
            ])
            ->assertRedirect(route('guards.show', $guard));

        $guard->refresh();
        $current = $guard->salaryRevisions()->whereNull('effective_to')->first();
        $this->assertNotNull($current);
        $this->assertSame(180000.0, (float) $current->salary);
        $this->assertSame(170000.0, (float) $current->previous_salary);
        $this->assertSame($changeOn->copy()->subDay()->toDateString(), $guard->salaryRevisions()->whereNotNull('effective_to')->first()->effective_to->toDateString());
        $this->assertSame($hr->id, $current->approved_by);
        $this->assertSame($hr->id, $current->created_by);
        $this->assertNotNull($current->approved_at);
        $this->assertSame(180000.0, (float) $guard->base_shift_rate);

        $this->assertTrue(AuditLog::query()->where('action', 'guard.salary_changed')->where('subject_id', $guard->id)->exists());

        $this->actingAs($hr)
            ->from(route('guards.show', $guard))
            ->post(route('guards.salary-revisions.store', $guard), [
                'salary' => 190000,
                'effective_from' => $changeOn->copy()->subDay()->toDateString(),
                'reason' => SalaryChangeReason::Other->value,
            ])
            ->assertSessionHasErrors('effective_from');

        $this->assertSame(2, $guard->salaryRevisions()->count());

        $this->actingAs($hr)
            ->put(route('guards.update', $guard), [
                'first_name' => 'Amina',
                'last_name' => 'Nakato',
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'base_shift_rate' => 999999,
            ])
            ->assertRedirect();

        $this->assertSame(180000.0, (float) $guard->fresh()->base_shift_rate);

        $this->actingAs($finance)
            ->post(route('guards.salary-revisions.store', $guard), [
                'salary' => 200000,
                'effective_from' => now()->startOfMonth()->toDateString(),
                'reason' => SalaryChangeReason::ContractChange->value,
                'notes' => 'Contract change',
            ])
            ->assertRedirect();

        $this->actingAs($shiftManager)
            ->get(route('guards.show', $guard))
            ->assertOk()
            ->assertSee('Salary history')
            ->assertSee('Current')
            ->assertSee('Historical')
            ->assertSee('Length of service')
            ->assertDontSee('Record salary change');

        $this->actingAs($hr)
            ->get(route('guards.show', $guard))
            ->assertOk()
            ->assertSee('Record salary change')
            ->assertSee($hr->name);
    }

    private function closedMonth(int $monthsBack = 0): Carbon
    {
        return PayrollRunService::lastClosedPeriod()->copy()->startOfMonth()->subMonths($monthsBack);
    }

    private function guardOn(Site $site, float $salary, CompensationType $type = CompensationType::Shift): Guard
    {
        return Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $type === CompensationType::Shift ? $site->id : null,
            'compensation_type' => $type,
            'base_shift_rate' => $salary,
            'overtime_shift_rate' => 0,
            'date_employed' => '2024-01-01',
            'employment_end_date' => null,
        ]);
    }

    private function recordShift(Guard $guard, Site $site, Carbon $date, ShiftType $type): void
    {
        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $date->toDateString(),
            'shift_type' => $type,
            'status' => ShiftStatus::Recorded,
        ]);
    }

    private function calculateMonth(User $actor, Carbon $month): PayrollRun
    {
        $payroll = app(PayrollRunService::class);
        $run = $payroll->createDraft([
            'period_year' => $month->year,
            'period_month' => $month->month,
        ], $actor);

        return $payroll->calculate($run);
    }

    private function payslipFor(PayrollRun $run, Guard $guard): PayrollPayslip
    {
        return PayrollPayslip::query()
            ->where('payroll_run_id', $run->id)
            ->where('guard_id', $guard->id)
            ->firstOrFail();
    }

    private function perShift(float $monthly): float
    {
        return round($monthly / max(1, (int) config('psg.payroll.standard_shifts_per_month', 30)), 2);
    }

    private function overtimeRate(float $monthly): float
    {
        return round($this->perShift($monthly) * (float) config('psg.payroll.overtime_multiplier', 1.5), 2);
    }
}
