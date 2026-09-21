<?php

namespace Tests\Feature\Operations;

use App\Enums\AttendanceEventType;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalPeriodStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\OperationalPeriod;
use App\Models\Site;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\Guards\GuardAsOfService;
use App\Services\Operations\OperationalPeriodService;
use App\Support\Access\RolePermissionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoricalDateHandlingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(RolePermissionService::class)->seedDefaults();
        app(RolePermissionService::class)->flushCache();
    }

    public function test_past_attendance_does_not_flip_on_duty(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));

        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
        ]);

        app(AttendanceService::class)->record([
            'guard_id' => $guard->id,
            'event_type' => AttendanceEventType::CheckIn->value,
            'occurred_at' => '2026-09-10 06:00:00',
            'notes' => 'Late entry of parade check-in.',
        ]);

        $guard->refresh();
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);

        Carbon::setTestNow();
    }

    public function test_finalized_period_blocks_ordinary_writes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));

        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $this->actingAs($manager);

        $period = app(OperationalPeriodService::class)->ensureForDate('2026-08-15');
        app(OperationalPeriodService::class)->close($period, $manager, 'Month-end lock');

        $site = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => '2026-08-15',
                // No correction reason → finalized month must reject even for Shift Manager.
            ])
            ->assertSessionHasErrors();

        $this->assertSame(0, Deployment::query()->where('guard_id', $guard->id)->count());

        Carbon::setTestNow();
    }

    public function test_historical_correct_allows_write_in_finalized_period_with_reason(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $this->actingAs($ops);

        $period = app(OperationalPeriodService::class)->ensureForDate('2026-08-15');
        app(OperationalPeriodService::class)->close($period, $ops, 'Month-end lock');

        $site = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => '2026-08-15',
                'notes' => 'Payroll dispute correction — missed posting.',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $deployment = Deployment::query()->where('guard_id', $guard->id)->first();
        $this->assertNotNull($deployment);
        $this->assertSame('2026-08-15', $deployment->start_date->toDateString());
        $this->assertFalse($deployment->is_current);
        $this->assertSame(DeploymentStatus::Ended, $deployment->status);

        $guard->refresh();
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);

        Carbon::setTestNow();
    }

    public function test_guard_as_of_returns_deployment_effective_on_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 10:00:00'));

        $siteA = Site::factory()->create(['name' => 'Site A']);
        $siteB = Site::factory()->create(['name' => 'Site B', 'region_id' => $siteA->region_id]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'region_id' => $siteA->region_id,
        ]);

        Deployment::query()->create([
            'guard_id' => $guard->id,
            'site_id' => $siteA->id,
            'region_id' => $siteA->region_id,
            'shift_type' => DeploymentShiftType::Day->value,
            'status' => DeploymentStatus::Ended->value,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'is_current' => false,
        ]);

        Deployment::query()->create([
            'guard_id' => $guard->id,
            'site_id' => $siteB->id,
            'region_id' => $siteB->region_id,
            'shift_type' => DeploymentShiftType::Day->value,
            'status' => DeploymentStatus::Active->value,
            'start_date' => '2026-09-11',
            'end_date' => null,
            'is_current' => true,
        ]);

        $asOf = app(GuardAsOfService::class)->snapshot($guard, '2026-09-05');
        $this->assertSame($siteA->id, $asOf['site_id']);
        $this->assertSame('Site A', $asOf['site_name']);

        $asOfToday = app(GuardAsOfService::class)->snapshot($guard, '2026-09-17');
        $this->assertSame($siteB->id, $asOfToday['site_id']);

        Carbon::setTestNow();
    }

    public function test_transfer_stores_operational_effective_date_not_entry_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:30:00'));

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $siteA = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $siteB = Site::factory()->create([
            'region_id' => $siteA->region_id,
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $siteA->region_id,
            'current_site_id' => $siteA->id,
        ]);

        $deployment = Deployment::query()->create([
            'guard_id' => $guard->id,
            'site_id' => $siteA->id,
            'region_id' => $siteA->region_id,
            'shift_type' => DeploymentShiftType::Day->value,
            'status' => DeploymentStatus::Active->value,
            'start_date' => '2026-09-01',
            'end_date' => null,
            'is_current' => true,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.transfer.store', $deployment), [
                'site_id' => $siteB->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'effective_date' => '2026-09-10',
                'reason' => 'client_request',
            ])
            ->assertRedirect();

        $transfer = \App\Models\DeploymentTransfer::query()->latest('id')->first();
        $this->assertNotNull($transfer);
        $this->assertSame('2026-09-10', $transfer->effective_at->toDateString());
        $this->assertNotSame('2026-09-17', $transfer->effective_at->toDateString());

        Carbon::setTestNow();
    }
}
