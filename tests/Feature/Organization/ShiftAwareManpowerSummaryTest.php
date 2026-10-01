<?php

namespace Tests\Feature\Organization;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Site;
use App\Models\User;
use App\Services\ManpowerGapService;
use App\Services\ManpowerService;
use App\Support\Performance\DashboardCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftAwareManpowerSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_covered_leaves_only_night_shortage_remaining(): void
    {
        $site = Site::factory()->create([
            'required_guards' => 4,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
        ]);

        foreach (range(1, 2) as $i) {
            $guard = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'region_id' => $site->region_id,
            ]);

            Deployment::factory()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => DeploymentShiftType::Day,
                'status' => DeploymentStatus::Active,
                'is_current' => true,
                'is_temporary' => false,
            ]);
        }

        $mp = app(ManpowerService::class)->forSite($site);

        $this->assertSame(2, $mp['deployed_day']);
        $this->assertSame(0, $mp['deployed_night']);
        $this->assertSame(0, $mp['shortage_day']);
        $this->assertSame(2, $mp['shortage_night']);
        $this->assertSame(2, $mp['shortage']);
        $this->assertSame('covered', $mp['shifts']['day']['status']);
        $this->assertSame('understaffed', $mp['shifts']['night']['status']);
        $this->assertSame(2, $mp['shifts']['remaining']);
        $this->assertStringContainsString('Fully covered (2/2)', $mp['shifts']['day']['headline']);
        $this->assertStringContainsString('Shortage — covered 0/2, remaining 2, deficit 2', $mp['shifts']['night']['headline']);
    }

    public function test_overtime_marks_night_covered_while_preserving_original_shortage(): void
    {
        $ops = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'required_guards' => 4,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
        ]);

        foreach (range(1, 2) as $i) {
            $dayGuard = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'region_id' => $site->region_id,
            ]);

            Deployment::factory()->create([
                'guard_id' => $dayGuard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => DeploymentShiftType::Day,
                'status' => DeploymentStatus::Active,
                'is_current' => true,
                'is_temporary' => false,
            ]);
        }

        $permanentNight = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
        ]);

        Deployment::factory()->create([
            'guard_id' => $permanentNight->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Night,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'is_temporary' => false,
        ]);

        $otGuard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => null,
            'overtime_shift_rate' => 50,
        ]);

        $this->actingAs($ops);
        $date = now()->toDateString();
        $gaps = app(ManpowerGapService::class);
        $nightGap = $gaps->syncGap($site, $date, ShiftPeriod::Night);
        $this->assertSame(1, $nightGap->original_shortage);

        $gaps->resolveWithOvertime($nightGap, ['guard_id' => $otGuard->id]);
        $nightGap->refresh();

        $mp = app(ManpowerService::class)->forSite($site);
        $summary = app(ManpowerService::class)->shiftCoverageSummary($mp, [
            'night' => [
                'original_shortage' => (int) $nightGap->original_shortage,
                'overtime_covered' => (int) $nightGap->overtime_covered,
                'remaining_shortage' => (int) $nightGap->remaining_shortage,
            ],
        ]);

        $this->assertSame(1, $mp['shortage_night']);
        $this->assertSame(0, $summary['night']['remaining']);
        $this->assertSame('ot_supported', $summary['night']['status']);
        $this->assertSame(1, $summary['night']['original_shortage']);
        $this->assertSame(1, $summary['night']['overtime']);
        $this->assertSame(1, $summary['deficit']);
        $this->assertStringContainsString('1 normal + 1 temporary cover', (string) $summary['night']['detail']);
    }

    public function test_coverage_table_shows_deficit_when_overtime_fills_gap(): void
    {
        $ops = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'name' => 'Deficit Gate',
            'required_guards' => 2,
            'required_day_guards' => 0,
            'required_night_guards' => 2,
        ]);

        $permanent = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
        ]);

        Deployment::factory()->create([
            'guard_id' => $permanent->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Night,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'is_temporary' => false,
        ]);

        $otGuard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => null,
            'overtime_shift_rate' => 50,
        ]);

        $this->actingAs($ops);
        $date = now()->toDateString();
        $gaps = app(ManpowerGapService::class);
        $nightGap = $gaps->syncGap($site, $date, ShiftPeriod::Night);
        $gaps->resolveWithOvertime($nightGap, ['guard_id' => $otGuard->id]);

        $this->get(route('manpower.coverage'))
            ->assertOk()
            ->assertSee('Deficit')
            ->assertSee('Deficit Gate')
            ->assertSee('Deficit');
    }

    public function test_dashboard_ops_pulse_shows_shift_breakdown(): void
    {
        $user = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_guards' => 4,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
            'name' => 'Alpha Warehouse Gate',
        ]);

        foreach (range(1, 2) as $i) {
            $guard = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'region_id' => $site->region_id,
            ]);

            Deployment::factory()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => DeploymentShiftType::Day,
                'status' => DeploymentStatus::Active,
                'is_current' => true,
                'is_temporary' => false,
            ]);
        }

        DashboardCache::flush();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alpha Warehouse Gate')
            ->assertSee('Day')
            ->assertSee('Night')
            ->assertSee('✓ 2/2')
            ->assertSee('! 0/2')
            ->assertSee('2', false);
    }
}
