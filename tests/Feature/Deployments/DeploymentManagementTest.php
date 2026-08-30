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

class DeploymentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_can_deploy_guard(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
        ]);

        $guard->refresh();
        $this->assertSame($site->id, $guard->current_site_id);
        $this->assertSame(OperationalStatus::OnDuty, $guard->operational_status);
    }

    public function test_transfer_preserves_previous_deployment_history(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $fromSite = Site::factory()->create();
        $toSite = Site::factory()->create(['region_id' => $fromSite->region_id]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $fromSite->region_id,
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $fromSite->id,
            'region_id' => $fromSite->region_id,
            'supervisor_id' => $fromSite->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $guard->update([
            'current_site_id' => $fromSite->id,
            'current_supervisor_id' => $fromSite->supervisor_id,
        ]);

        $this->actingAs($admin)
            ->post(route('deployments.transfer.store', $deployment), [
                'site_id' => $toSite->id,
                'shift_type' => DeploymentShiftType::Night->value,
                'reason' => 'Coverage rebalance',
            ])
            ->assertRedirect();

        $deployment->refresh();
        $this->assertSame(DeploymentStatus::Transferred, $deployment->status);
        $this->assertFalse($deployment->is_current);

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $toSite->id,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('deployment_transfers', [
            'guard_id' => $guard->id,
            'from_site_id' => $fromSite->id,
            'to_site_id' => $toSite->id,
        ]);
    }

    public function test_hr_manager_cannot_create_deployments(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();

        $this->actingAs($hr)
            ->get(route('deployments.create'))
            ->assertForbidden();
    }

    public function test_manpower_counts_active_deployments(): void
    {
        $site = Site::factory()->create([
            'required_guards' => 2,
            'required_day_guards' => 1,
            'required_night_guards' => 1,
        ]);

        Deployment::factory()->create([
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'shift_type' => DeploymentShiftType::Day,
        ]);

        $snapshot = $site->manpowerSnapshot();

        $this->assertSame(1, $snapshot['deployed']);
        $this->assertSame(1, $snapshot['shortage']);
    }
}
