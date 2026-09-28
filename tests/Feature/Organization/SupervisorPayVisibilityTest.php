<?php

namespace Tests\Feature\Organization;

use App\Enums\CompensationType;
use App\Enums\PayrollRunStatus;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\StaffSalaryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorPayVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_and_operations_managers_cannot_see_supervisor_salary_or_history(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $shift = User::factory()->role(UserRole::ShiftManager)->create();
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $guard = Guard::factory()->create([
            'base_shift_rate' => 1_876_500,
            'full_name' => 'James Kato',
        ]);
        $staff = Staff::factory()->create([
            'monthly_salary' => 1_876_500,
            'guard_id' => $guard->id,
            'full_name' => 'James Kato',
            'job_title' => 'Field Supervisor',
        ]);
        $clerk = Staff::factory()->create([
            'monthly_salary' => 640_000,
            'full_name' => 'Joan Atim',
            'job_title' => 'Admin Assistant',
        ]);
        $supervisor = Supervisor::factory()->create([
            'name' => 'James Kato',
            'guard_id' => $guard->id,
            'staff_id' => $staff->id,
        ]);

        app(StaffSalaryService::class)->recordOpening(
            $staff,
            1_876_500,
            Carbon::parse('2025-01-01'),
            $hr,
            'Field Supervisor',
        );

        $amount = 'UGX 1,876,500';
        $clerkAmount = 'UGX 640,000';

        $this->actingAs($shift)
            ->get(route('supervisors.show', $supervisor))
            ->assertOk()
            ->assertSee('James Kato')
            ->assertDontSee($amount)
            ->assertDontSee('Salary &amp; employment history')
            ->assertDontSee('Salary & employment history');

        $this->actingAs($ops)
            ->get(route('supervisors.show', $supervisor))
            ->assertOk()
            ->assertDontSee($amount)
            ->assertDontSee('Salary & employment history');

        $this->actingAs($hr)
            ->get(route('supervisors.show', $supervisor))
            ->assertOk()
            ->assertSee($amount)
            ->assertSee('Salary &amp; employment history', false);

        $this->actingAs($shift)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertDontSee($amount)
            ->assertDontSee('Salary & employment history');

        $this->actingAs($shift)
            ->get(route('staff.show', $clerk))
            ->assertOk()
            ->assertSee($clerkAmount);

        $this->actingAs($shift)
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee($clerkAmount)
            ->assertDontSee($amount);

        $this->actingAs($ops)
            ->get(route('guards.show', $guard))
            ->assertOk()
            ->assertSee('James Kato')
            ->assertDontSee($amount)
            ->assertDontSee('Salary history');

        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-SUP',
            'period_year' => 2026,
            'period_month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRunStatus::Calculated,
            'currency' => 'UGX',
            'guard_count' => 2,
        ]);

        $supervisorPayslip = PayrollPayslip::query()->create([
            'payroll_run_id' => $run->id,
            'staff_id' => $staff->id,
            'guard_id' => $guard->id,
            'employment_id' => $staff->employment_id,
            'full_name' => 'James Kato',
            'compensation_type' => CompensationType::Salary,
            'gross_pay' => 1_876_500,
            'total_deductions' => 0,
            'net_pay' => 1_876_500,
            'base_shift_rate' => 1_876_500,
        ]);

        $otherGuard = Guard::factory()->create(['full_name' => 'Peter Okello']);
        PayrollPayslip::query()->create([
            'payroll_run_id' => $run->id,
            'guard_id' => $otherGuard->id,
            'employment_id' => $otherGuard->employment_id,
            'full_name' => 'Peter Okello',
            'compensation_type' => CompensationType::Shift,
            'gross_pay' => 222_000,
            'total_deductions' => 0,
            'net_pay' => 222_000,
            'base_shift_rate' => 222_000,
        ]);

        $this->actingAs($ops)
            ->get(route('payroll.show', $run))
            ->assertOk()
            ->assertSee('Peter Okello')
            ->assertSee('UGX 222,000')
            ->assertDontSee('James Kato')
            ->assertDontSee($amount);

        $this->actingAs($ops)
            ->get(route('payroll.payslips.show', [$run, $supervisorPayslip]))
            ->assertForbidden();

        $opsExport = $this->actingAs($ops)->get(route('payroll.export.payslips', $run));
        $opsExport->assertOk();
        $this->assertStringNotContainsString('1876500.00', $opsExport->streamedContent());
        $this->assertStringContainsString('222000.00', $opsExport->streamedContent());

        $this->actingAs($finance)
            ->get(route('payroll.show', $run))
            ->assertOk()
            ->assertSee($amount);

        $financeExport = $this->actingAs($finance)->get(route('payroll.export.payslips', $run));
        $financeExport->assertOk();
        $this->assertStringContainsString('1876500.00', $financeExport->streamedContent());
    }
}
