<?php

namespace Tests\Feature\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
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
    }

    public function test_deployed_guard_does_not_appear_on_board(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'full_name' => 'Already Posted',
        ]);

        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertDontSee('Already Posted', false);
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
}
