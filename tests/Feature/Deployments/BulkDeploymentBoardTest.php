<?php

namespace Tests\Feature\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkDeploymentBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_can_open_deployment_board(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'current_site_id' => null,
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee($guard->full_name, false)
            ->assertSee('Deploy selected', false)
            ->assertSee('Showing', false);
    }

    public function test_bulk_deploy_assigns_selected_guards(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'current_site_id' => null,
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

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
        ]);

        $guard->refresh();
        $this->assertSame(OperationalStatus::OnDuty, $guard->operational_status);
    }

    public function test_deployed_guard_does_not_appear_on_board(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
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
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'shift_type' => DeploymentShiftType::Rotating,
            'start_date' => now()->toDateString(),
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertDontSee($guard->full_name, false);
    }

    public function test_past_duty_date_lists_guards_free_that_day_even_if_on_duty_today(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'date_employed' => now()->subMonths(2)->toDateString(),
            'full_name' => 'Historical Free Guard',
        ]);

        Deployment::query()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Rotating->value,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
            'start_date' => now()->toDateString(),
            'end_date' => null,
        ]);

        $pastDate = now()->subDays(5)->toDateString();

        $this->actingAs($ops)
            ->get(route('deployments.board', ['start_date' => $pastDate]))
            ->assertOk()
            ->assertSee('Historical Free Guard', false)
            ->assertSee('Historical duty date', false);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertDontSee('Historical Free Guard', false);
    }

    public function test_guards_index_shows_deployed_guard_even_if_status_is_off_duty(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'full_name' => 'Stale Status Guard',
        ]);

        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'shift_type' => DeploymentShiftType::Rotating,
        ]);

        $this->actingAs($ops)
            ->get(route('guards.index'))
            ->assertOk()
            ->assertSee('Stale Status Guard', false);

        $this->assertSame(OperationalStatus::OffDuty, $guard->fresh()->operational_status);
    }

    public function test_deploy_only_validates_selected_guard_rows(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $target = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);
        $other = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$target->id],
                'rows' => [
                    $target->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                    ],
                    $other->id => [
                        'site_id' => '',
                        'shift_type' => DeploymentShiftType::Night->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $target->id,
            'site_id' => $site->id,
            'is_current' => true,
        ]);
    }

    public function test_bulk_deploy_can_allocate_shifts_in_same_step(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $shiftDate = now()->toDateString();

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $shiftDate,
                'shift_date' => $shiftDate,
                'allocate_shifts' => '1',
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('shifts', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => ShiftStatus::Recorded->value,
            'shift_type' => ShiftType::Normal->value,
        ]);

        $this->assertSame(1, Shift::query()
            ->where('guard_id', $guard->id)
            ->whereDate('shift_date', $shiftDate)
            ->count());
    }
}
