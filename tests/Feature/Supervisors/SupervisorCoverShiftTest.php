<?php

namespace Tests\Feature\Supervisors;

use App\Enums\CompensationType;
use App\Enums\DeploymentShiftType;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\ManpowerGap;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DeploymentService;
use App\Services\Finance\PayrollRunService;
use App\Services\ManpowerGapService;
use App\Services\ManpowerService;
use App\Services\SystemSettingService;
use App\Support\Finance\PayrollRates;
use App\Support\Supervisors\SupervisorCoverageClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorCoverShiftTest extends TestCase
{
    use RefreshDatabase;

    private function understaffedSite(Region $region, int $requiredDay = 2, int $requiredNight = 2, int $permanentDay = 1, int $permanentNight = 1): Site
    {
        $site = Site::factory()->create([
            'region_id' => $region->id,
            'required_day_guards' => $requiredDay,
            'required_night_guards' => $requiredNight,
            'required_guards' => $requiredDay + $requiredNight,
        ]);

        for ($i = 0; $i < $permanentDay; $i++) {
            $guard = Guard::factory()->create([
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'current_site_id' => $site->id,
            ]);
            Deployment::factory()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $region->id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => DeploymentShiftType::Day,
                'is_current' => true,
                'is_temporary' => false,
            ]);
        }

        for ($i = 0; $i < $permanentNight; $i++) {
            $guard = Guard::factory()->create([
                'region_id' => $region->id,
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'current_site_id' => $site->id,
            ]);
            Deployment::factory()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $region->id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => DeploymentShiftType::Night,
                'is_current' => true,
                'is_temporary' => false,
            ]);
        }

        return $site;
    }

    public function test_day_cover_is_normal_supervisor_shift_without_ot(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();
        $site = $this->understaffedSite($region);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id, 'name' => 'James Otieno']);

        $this->actingAs($ops)
            ->post(route('supervisors.deploy.store', $supervisor), [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => now()->toDateString(),
                'notes' => 'Covering shortage',
            ])
            ->assertRedirect(route('supervisors.show', $supervisor));

        $supervisor->refresh()->load('guardProfile');

        $this->assertNotNull($supervisor->guard_id);
        $this->assertSame('Supervisor', $supervisor->guardProfile->rank_designation);
        $this->assertSame(CompensationType::Salary, $supervisor->guardProfile->compensation_type);

        $deployment = Deployment::query()->where('guard_id', $supervisor->guard_id)->latest('id')->firstOrFail();
        $this->assertTrue($deployment->is_temporary);
        $this->assertSame(ShiftType::Normal, $deployment->duty_type);
        $this->assertNotNull($deployment->manpower_gap_id);
        $this->assertStringContainsString('Normal Supervisor Shift', (string) $deployment->notes);
        $this->assertStringContainsString('Manpower Shortage', (string) $deployment->notes);

        $shift = Shift::query()->where('guard_id', $supervisor->guard_id)->first();
        $this->assertNotNull($shift);
        $this->assertSame(ShiftType::Normal, $shift->shift_type);
        $this->assertSame(ShiftStatus::Recorded, $shift->status);
    }

    public function test_night_cover_is_always_supervisor_overtime(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();
        $site = $this->understaffedSite($region);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id, 'name' => 'James Otieno']);

        $this->actingAs($ops)
            ->post(route('supervisors.deploy.store', $supervisor), [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Night->value,
                'duty_type' => ShiftType::Normal->value, // ignored for night
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect(route('supervisors.show', $supervisor));

        $supervisor->refresh();

        $deployment = Deployment::query()->where('guard_id', $supervisor->guard_id)->latest('id')->firstOrFail();
        $this->assertTrue($deployment->is_temporary);
        $this->assertSame(ShiftType::Overtime, $deployment->duty_type);
        $this->assertStringContainsString('Supervisor Overtime', (string) $deployment->notes);

        $shift = Shift::query()->where('guard_id', $supervisor->guard_id)->first();
        $this->assertNotNull($shift);
        $this->assertSame(ShiftType::Overtime, $shift->shift_type);
    }

    public function test_classifier_maps_day_to_normal_and_night_to_overtime(): void
    {
        $this->assertSame(ShiftType::Normal, SupervisorCoverageClassifier::classify(DeploymentShiftType::Day));
        $this->assertSame(ShiftType::Overtime, SupervisorCoverageClassifier::classify(DeploymentShiftType::Night));
        $this->assertSame(ShiftType::Overtime, SupervisorCoverageClassifier::classify(DeploymentShiftType::Rotating));
    }

    public function test_supervisor_cover_preserves_deficit_while_filling_operational_coverage(): void
    {
        $region = Region::factory()->create();
        $site = $this->understaffedSite($region, requiredDay: 2, requiredNight: 2, permanentDay: 1, permanentNight: 1);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id]);
        $date = now()->toDateString();

        app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Day->value,
            'start_date' => $date,
        ]);

        $gap = app(ManpowerGapService::class)->syncGap($site->fresh(), $date, \App\Enums\ShiftPeriod::Day);
        $this->assertSame(1, (int) $gap->original_shortage);
        $this->assertSame(1, (int) $gap->overtime_covered);
        $this->assertSame(0, (int) $gap->remaining_shortage);

        $ot = app(ManpowerGapService::class)->otCoverageBySite([$site->id], $date);
        $summary = app(ManpowerService::class)->shiftCoverageSummary([
            'required_day' => 2,
            'deployed_day' => 1,
            'shortage_day' => 1,
            'required_night' => 2,
            'deployed_night' => 1,
            'shortage_night' => 1,
            'required' => 4,
            'deployed' => 2,
        ], $ot[$site->id]);

        $this->assertSame(0, $summary['day']['remaining']);
        $this->assertSame(2, $summary['day']['covered']);
        $this->assertSame(1, $summary['day']['overtime']);
        $this->assertSame(1, $summary['deficit']);
    }

    public function test_completed_supervisor_shifts_appear_on_monthly_report(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $region = Region::factory()->create();
        $site = $this->understaffedSite($region);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id, 'name' => 'Mary Wambui']);

        app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Day->value,
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
            'site_id' => $this->understaffedSite($region)->id,
            'shift_type' => DeploymentShiftType::Day->value,
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
        SystemSetting::query()->first()?->update([
            'payroll_overtime_multiplier' => 1.0,
            'payroll_standard_shifts_per_month' => 30,
        ]);
        app(SystemSettingService::class)->applyRuntimeConfig();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = PayrollRunService::lastClosedPeriod();
        $start = $period->copy()->startOfMonth();
        $region = Region::factory()->create();
        $site = $this->understaffedSite($region);

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
        SystemSetting::query()->first()?->update([
            'payroll_overtime_multiplier' => 1.0,
            'payroll_standard_shifts_per_month' => 30,
        ]);
        app(SystemSettingService::class)->applyRuntimeConfig();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = PayrollRunService::lastClosedPeriod();
        $start = $period->copy()->startOfMonth();
        $region = Region::factory()->create();
        $site = $this->understaffedSite($region);

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

    public function test_cannot_cover_when_site_has_no_shortage(): void
    {
        $region = Region::factory()->create();
        $site = $this->understaffedSite($region, requiredDay: 1, requiredNight: 1, permanentDay: 1, permanentNight: 1);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id]);

        $this->expectException(\InvalidArgumentException::class);

        app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Day->value,
            'start_date' => now()->toDateString(),
        ]);
    }
}
