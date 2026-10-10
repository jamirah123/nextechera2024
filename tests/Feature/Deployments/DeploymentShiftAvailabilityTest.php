<?php

namespace Tests\Feature\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\DeploymentService;
use App\Support\Deployments\DeploymentShiftSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeploymentShiftAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_deployed_guards_are_hidden_from_deployment_board(): void
    {
        Carbon::setTestNow('2026-08-28 22:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $deployed = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'full_name' => 'Deployed Guard',
        ]);
        $awaiting = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'full_name' => 'Awaiting Guard',
        ]);

        Deployment::factory()->create([
            'guard_id' => $deployed->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Night,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Awaiting Guard', false)
            ->assertSee('Deployed Guard', false)
            ->assertSee('Day: Available', false)
            ->assertSee('Night: Available', false);
    }

    public function test_guard_disappears_from_board_after_deployment(): void
    {
        Carbon::setTestNow('2026-08-28 10:00:00');

        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'full_name' => 'Fresh Guard',
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Fresh Guard', false)
            ->assertSee('Day: '.$site->name, false)
            ->assertSee('Night: Available', false);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$guard->id],
                'overtime_reviewed' => '1',
                'confirm_overtime' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($manager)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertDontSee('Fresh Guard', false);

        $this->actingAs($manager)
            ->get(route('deployments.index'))
            ->assertOk()
            ->assertSee('Fresh Guard', false);
    }

    public function test_shift_schedule_respects_configured_windows(): void
    {
        Carbon::setTestNow('2026-08-28 17:59:00');
        $this->assertTrue(DeploymentShiftSchedule::isOnShift(DeploymentShiftType::Day));

        Carbon::setTestNow('2026-08-28 18:00:00');
        $this->assertFalse(DeploymentShiftSchedule::isOnShift(DeploymentShiftType::Day));
        $this->assertTrue(DeploymentShiftSchedule::isOnShift(DeploymentShiftType::Night));

        Carbon::setTestNow('2026-08-28 06:00:00');
        $this->assertFalse(DeploymentShiftSchedule::isOnShift(DeploymentShiftType::Night));
    }

    public function test_day_guard_returns_to_board_when_day_shift_window_ends(): void
    {
        Carbon::setTestNow('2026-08-28 17:59:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'full_name' => 'Day Shift Guard',
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Day Shift Guard', false)
            ->assertSee('Night: Available', false);

        $this->actingAs($ops)
            ->get(route('deployments.board', ['start_date' => '2026-08-29']))
            ->assertOk()
            ->assertSee('Day Shift Guard', false)
            ->assertSee('Day: Available', false)
            ->assertSee('Night: Available', false);

        $guard->refresh();
        $this->assertSame(OperationalStatus::OnDuty, $guard->operational_status);
        $this->assertTrue(Deployment::query()
            ->where('guard_id', $guard->id)
            ->whereDate('start_date', '2026-08-28')
            ->exists());
    }

    public function test_night_guard_returns_to_board_when_night_shift_window_ends(): void
    {
        Carbon::setTestNow('2026-08-29 05:59:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'full_name' => 'Night Shift Guard',
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($ops)
            ->get(route('deployments.board', ['start_date' => '2026-08-28']))
            ->assertOk()
            ->assertSee('Night Shift Guard', false)
            ->assertSee('Day: Available', false);

        $this->actingAs($ops)
            ->get(route('deployments.board', ['start_date' => '2026-08-29']))
            ->assertOk()
            ->assertSee('Night Shift Guard', false)
            ->assertSee('Day: Available', false)
            ->assertSee('Night: Available', false);

        $guard->refresh();
        $this->assertTrue(Deployment::query()->where('guard_id', $guard->id)->exists());
    }

    public function test_night_posting_created_during_daytime_is_not_released_before_night_window(): void
    {
        Carbon::setTestNow('2026-09-07 10:00:00');

        $site = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'current_site_id' => null,
        ]);

        $deployment = app(DeploymentService::class)->deploy([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Night->value,
            'start_date' => '2026-09-07',
        ]);

        $this->assertTrue($deployment->is_current);
        $this->assertSame(DeploymentStatus::Active, $deployment->status);

        $released = app(DeploymentService::class)->releaseGuardsAfterShiftWindow();
        $this->assertSame(0, $released);

        $deployment->refresh();
        $guard->refresh();
        $this->assertTrue($deployment->is_current);
        $this->assertSame(OperationalStatus::OnDuty, $guard->operational_status);

        Carbon::setTestNow('2026-09-08 06:00:00');

        $released = app(DeploymentService::class)->releaseGuardsAfterShiftWindow();
        $this->assertSame(1, $released);

        $deployment->refresh();
        $guard->refresh();
        $this->assertFalse($deployment->is_current);
        $this->assertSame(DeploymentStatus::Ended, $deployment->status);
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);
    }

    public function test_scheduled_command_releases_guards_when_shift_window_ends(): void
    {
        Carbon::setTestNow('2026-08-28 17:59:00');

        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
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
        ]);

        Carbon::setTestNow('2026-08-28 18:00:00');

        $this->artisan('psg:release-shift-window-guards')
            ->expectsOutputToContain('1 deployment(s) ended')
            ->assertSuccessful();

        $guard->refresh();
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);
        $this->assertFalse($guard->currentDeployment()->exists());
    }

    public function test_shift_window_release_keeps_day_posting_when_upcoming_shift_exists(): void
    {
        Carbon::setTestNow('2026-08-28 17:59:00');

        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
        ]);

        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->addDay()->toDateString(),
            'status' => ShiftStatus::Scheduled,
            'starts_at' => now()->addDay()->setTime(6, 0),
            'ends_at' => now()->addDay()->setTime(18, 0),
        ]);

        Carbon::setTestNow('2026-08-28 18:00:00');

        $this->artisan('psg:release-shift-window-guards')
            ->expectsOutputToContain('0 deployment(s) ended')
            ->assertSuccessful();

        $this->assertTrue($guard->fresh()->currentDeployment()->exists());
        $this->assertSame($site->id, $guard->fresh()->current_site_id);
    }
}
