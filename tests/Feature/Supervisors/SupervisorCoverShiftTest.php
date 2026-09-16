<?php

namespace Tests\Feature\Supervisors;

use App\Enums\CompensationType;
use App\Enums\DeploymentShiftType;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\DeploymentService;
use App\Services\Finance\PayrollRunService;
use App\Support\Finance\PayrollRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorCoverShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_deploy_creates_guard_profile_and_normal_shift_by_default(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id, 'name' => 'James Otieno']);

        $this->actingAs($ops)
            ->post(route('supervisors.deploy.store', $supervisor), [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'duty_type' => ShiftType::Normal->value,
                'start_date' => now()->toDateString(),
                'notes' => 'Covering shortage',
            ])
            ->assertRedirect(route('supervisors.show', $supervisor));

        $supervisor->refresh()->load('guardProfile');

        $this->assertNotNull($supervisor->guard_id);
        $this->assertSame('Supervisor', $supervisor->guardProfile->rank_designation);
        $this->assertSame(CompensationType::Salary, $supervisor->guardProfile->compensation_type);

        $shift = Shift::query()->where('guard_id', $supervisor->guard_id)->first();
        $this->assertNotNull($shift);
        $this->assertSame(ShiftType::Normal, $shift->shift_type);
        $this->assertSame(ShiftStatus::Recorded, $shift->status);
    }

    public function test_supervisor_deploy_records_overtime_only_when_duty_type_is_overtime(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id, 'name' => 'James Otieno']);

        $this->actingAs($ops)
            ->post(route('supervisors.deploy.store', $supervisor), [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Night->value,
                'duty_type' => ShiftType::Overtime->value,
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('supervisors.show', $supervisor));

        $supervisor->refresh();

        $shift = Shift::query()->where('guard_id', $supervisor->guard_id)->first();
        $this->assertNotNull($shift);
        $this->assertSame(ShiftType::Overtime, $shift->shift_type);
    }

    public function test_night_cover_is_not_assumed_to_be_overtime(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id]);

        $this->actingAs($ops)
            ->post(route('supervisors.deploy.store', $supervisor), [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Night->value,
                'duty_type' => ShiftType::Normal->value,
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $supervisor->refresh();

        $shift = Shift::query()->where('guard_id', $supervisor->guard_id)->firstOrFail();
        $this->assertSame(ShiftType::Normal, $shift->shift_type);
    }

    public function test_completed_supervisor_shifts_appear_on_monthly_report(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id, 'name' => 'Mary Wambui']);

        app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Day->value,
            'duty_type' => ShiftType::Overtime->value,
            'start_date' => now()->toDateString(),
        ]);

        $supervisor->refresh();

        Shift::query()
            ->where('guard_id', $supervisor->guard_id)
            ->update(['status' => ShiftStatus::Recorded->value]);

        $this->actingAs($finance)
            ->get(route('reports.monthly-shifts', [
                'year' => now()->year,
                'month' => now()->month,
            ]))
            ->assertOk()
            ->assertSee('Mary Wambui')
            ->assertSee('Supervisor', false)
            ->assertSee($supervisor->guardProfile->employment_id);
    }

    public function test_supervisor_payroll_profiles_are_hidden_from_deployment_board(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id]);

        app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => Site::factory()->create(['region_id' => $region->id])->id,
            'shift_type' => DeploymentShiftType::Day->value,
            'duty_type' => ShiftType::Normal->value,
            'start_date' => now()->toDateString(),
        ]);

        $supervisor->refresh();

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertDontSee($supervisor->guardProfile->employment_id, false);
    }

    public function test_normal_cover_does_not_add_overtime_to_supervisor_payroll(): void
    {
        config(['psg.payroll.overtime_multiplier' => 1.0]);
        config(['psg.payroll.standard_shifts_per_month' => 30]);

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = PayrollRunService::lastClosedPeriod();
        $start = $period->copy()->startOfMonth();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);

        $staff = Staff::factory()->create([
            'region_id' => $region->id,
            'monthly_salary' => 1_500_000,
            'date_employed' => $start->toDateString(),
            'first_name' => 'Cover',
            'last_name' => 'Normal',
        ]);

        $supervisor = Supervisor::factory()->create([
            'region_id' => $region->id,
            'staff_id' => $staff->id,
            'name' => $staff->full_name,
        ]);

        app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Day->value,
            'duty_type' => ShiftType::Normal->value,
            'start_date' => $start->toDateString(),
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()
            ->where('payroll_run_id', $run->id)
            ->where('staff_id', $staff->id)
            ->firstOrFail();

        $this->assertSame(0, $payslip->overtime_shifts);
        $this->assertEquals(1_500_000.0, (float) $payslip->gross_pay);
        $this->assertSame(CompensationType::Salary, $payslip->compensation_type);
    }

    public function test_overtime_cover_adds_daily_rate_to_supervisor_payroll_without_changing_salary(): void
    {
        config(['psg.payroll.overtime_multiplier' => 1.0]);
        config(['psg.payroll.standard_shifts_per_month' => 30]);

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = PayrollRunService::lastClosedPeriod();
        $start = $period->copy()->startOfMonth();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);

        $staff = Staff::factory()->create([
            'region_id' => $region->id,
            'monthly_salary' => 1_500_000,
            'date_employed' => $start->toDateString(),
            'first_name' => 'Cover',
            'last_name' => 'Overtime',
        ]);

        $supervisor = Supervisor::factory()->create([
            'region_id' => $region->id,
            'staff_id' => $staff->id,
            'name' => $staff->full_name,
        ]);

        app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Night->value,
            'duty_type' => ShiftType::Overtime->value,
            'start_date' => $start->toDateString(),
        ]);

        $supervisor->refresh();
        $this->assertSame(ShiftType::Overtime, Shift::query()->where('guard_id', $supervisor->guard_id)->value('shift_type'));

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(PayrollRunStatus::Calculated, $run->status);

        $payslip = PayrollPayslip::query()
            ->where('payroll_run_id', $run->id)
            ->where('staff_id', $staff->id)
            ->firstOrFail();

        $dailyRate = PayrollRates::dailyRateFromMonthly(1_500_000, $run);
        $this->assertSame(50_000.0, $dailyRate);
        $this->assertSame(1, $payslip->overtime_shifts);
        $this->assertEquals(50_000.0, (float) $payslip->overtime_shift_rate);
        $this->assertEquals(1_550_000.0, (float) $payslip->gross_pay);
        $this->assertEquals(1_500_000.0, (float) $staff->fresh()->monthly_salary);
    }
}
