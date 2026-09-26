<?php

namespace Tests\Feature\Organization;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ManpowerGapStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\ManpowerGap;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\ManpowerGapService;
use App\Services\ManpowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManpowerGapOvertimeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Night duties before dawn are stored on the previous calendar day.
        Carbon::setTestNow('2026-09-26 14:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_original_shortage_is_preserved_when_overtime_covers_gap(): void
    {
        $ops = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'required_guards' => 3,
            'required_day_guards' => 1,
            'required_night_guards' => 2,
        ]);

        $dayGuard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'overtime_shift_rate' => 45,
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

        $otGuardA = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => null,
            'overtime_shift_rate' => 50,
        ]);

        $otGuardB = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => null,
            'overtime_shift_rate' => 55,
        ]);

        $date = now()->toDateString();
        $gaps = app(ManpowerGapService::class);

        $this->actingAs($ops);

        $nightGap = $gaps->syncGap($site, $date, ShiftPeriod::Night);

        $this->assertSame(2, $nightGap->original_shortage);
        $this->assertSame(2, $nightGap->remaining_shortage);
        $this->assertSame(ManpowerGapStatus::Open, $nightGap->status);

        $first = $gaps->resolveWithOvertime($nightGap, ['guard_id' => $otGuardA->id]);
        $nightGap = $first['gap'];

        $this->assertSame(2, $nightGap->original_shortage);
        $this->assertSame(1, $nightGap->overtime_covered);
        $this->assertSame(1, $nightGap->remaining_shortage);
        $this->assertSame(ManpowerGapStatus::PartiallyResolved, $nightGap->status);

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $otGuardA->id,
            'site_id' => $site->id,
            'is_temporary' => true,
            'duty_type' => ShiftType::Overtime->value,
            'manpower_gap_id' => $nightGap->id,
            'is_current' => true,
        ]);

        $this->assertTrue(
            Shift::query()
                ->where('guard_id', $otGuardA->id)
                ->where('site_id', $site->id)
                ->whereDate('shift_date', $date)
                ->where('period', ShiftPeriod::Night->value)
                ->where('shift_type', ShiftType::Overtime->value)
                ->exists()
        );

        $standing = app(ManpowerService::class)->forSite($site->fresh());
        $this->assertSame(1, $standing['deployed']);
        $this->assertSame(1, $standing['deployed_day']);
        $this->assertSame(0, $standing['deployed_night']);

        $second = $gaps->resolveWithOvertime($nightGap->fresh(), ['guard_id' => $otGuardB->id]);
        $nightGap = $second['gap'];

        $this->assertSame(2, $nightGap->original_shortage);
        $this->assertSame(2, $nightGap->overtime_covered);
        $this->assertSame(0, $nightGap->remaining_shortage);
        $this->assertSame(ManpowerGapStatus::Resolved, $nightGap->status);

        $summary = $gaps->summaryForDate($date, $site->region_id);
        $this->assertSame(2, $summary['original_shortage']);
        $this->assertSame(0, $summary['remaining_shortage']);
        $this->assertGreaterThan(0, $summary['estimated_ot_cost']);
    }

    public function test_shift_manager_can_resolve_gap_from_coverage_page(): void
    {
        $ops = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'required_guards' => 2,
            'required_day_guards' => 0,
            'required_night_guards' => 2,
        ]);

        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => null,
        ]);

        $date = now()->toDateString();

        $this->actingAs($ops);

        $gap = app(ManpowerGapService::class)->syncGap($site, $date, ShiftPeriod::Night);

        $this->assertSame(2, $gap->remaining_shortage);

        $this->post(route('manpower.gaps.overtime', $gap), [
            'guard_id' => $guard->id,
            'notes' => 'Night OT cover',
        ])->assertRedirect(route('manpower.coverage', ['date' => $date]));

        $gap->refresh();
        $this->assertSame(2, $gap->original_shortage);
        $this->assertSame(1, $gap->remaining_shortage);
        $this->assertSame(ManpowerGapStatus::PartiallyResolved, $gap->status);

        $this->get(route('manpower.coverage', ['date' => $date]))
            ->assertOk()
            ->assertSee('Overtime gaps', false)
            ->assertSee($site->name, false);
    }

    public function test_day_posted_guard_can_take_night_overtime_without_changing_permanent_post(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_guards' => 2,
            'required_day_guards' => 1,
            'required_night_guards' => 1,
        ]);

        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
        ]);

        $permanent = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'is_temporary' => false,
        ]);

        $date = now()->toDateString();

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Night->value,
                'duty_type' => ShiftType::Overtime->value,
                'start_date' => $date,
            ])
            ->assertRedirect();

        $permanent->refresh();
        $this->assertTrue($permanent->is_current);
        $this->assertSame(DeploymentShiftType::Day, $permanent->shift_type);

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Night->value,
            'is_temporary' => true,
            'duty_type' => ShiftType::Overtime->value,
        ]);

        $gap = ManpowerGap::query()
            ->where('site_id', $site->id)
            ->whereDate('gap_date', $date)
            ->where('period', ShiftPeriod::Night->value)
            ->first();

        $this->assertNotNull($gap);
        $this->assertSame(1, $gap->original_shortage);
        $this->assertSame(0, $gap->remaining_shortage);
        $this->assertSame(ManpowerGapStatus::Resolved, $gap->status);
    }
}
