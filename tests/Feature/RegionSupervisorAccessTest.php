<?php

namespace Tests\Feature;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionSupervisorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_supervisor_can_deploy_guard_in_own_region(): void
    {
        $supervisor = Supervisor::factory()->create();
        $user = User::factory()->regionSupervisor($supervisor->id)->create();
        $site = Site::factory()->create([
            'region_id' => $supervisor->region_id,
            'supervisor_id' => $supervisor->id,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $supervisor->region_id,
        ]);

        $this->actingAs($user)
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
    }

    public function test_region_supervisor_cannot_deploy_outside_own_region(): void
    {
        $supervisor = Supervisor::factory()->create();
        $user = User::factory()->regionSupervisor($supervisor->id)->create();
        $otherSite = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $otherSite->region_id,
        ]);

        $this->actingAs($user)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $otherSite->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors(['site_id', 'guard_id']);
    }

    public function test_region_supervisor_can_record_absence_in_region(): void
    {
        $supervisor = Supervisor::factory()->create();
        $user = User::factory()->regionSupervisor($supervisor->id)->create();
        $site = Site::factory()->create([
            'region_id' => $supervisor->region_id,
            'supervisor_id' => $supervisor->id,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'current_site_id' => $site->id,
            'region_id' => $supervisor->region_id,
        ]);

        $this->actingAs($user)
            ->post(route('absences.store'), [
                'guard_id' => $guard->id,
                'absence_date' => now()->subDay()->toDateString(),
                'reason' => 'no_show',
                'site_id' => $site->id,
            ])
            ->assertRedirect();

        $this->assertSame(OperationalStatus::Absent, $guard->fresh()->operational_status);
    }

    public function test_region_supervisor_can_report_desertion_in_region(): void
    {
        $supervisor = Supervisor::factory()->create();
        $user = User::factory()->regionSupervisor($supervisor->id)->create();
        $site = Site::factory()->create([
            'region_id' => $supervisor->region_id,
            'supervisor_id' => $supervisor->id,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'current_site_id' => $site->id,
            'region_id' => $supervisor->region_id,
        ]);

        $this->actingAs($user)
            ->post(route('desertions.store'), [
                'guard_id' => $guard->id,
                'date_reported' => now()->toDateString(),
                'last_known_duty_date' => now()->subDay()->toDateString(),
                'last_known_site_id' => $site->id,
                'circumstances' => 'Abandoned post without notice.',
            ])
            ->assertRedirect();

        $this->assertSame(OperationalStatus::Deserted, $guard->fresh()->operational_status);
    }

    public function test_region_supervisor_cannot_create_shifts(): void
    {
        $user = User::factory()->regionSupervisor()->create();

        $this->actingAs($user)
            ->get(route('shifts.create'))
            ->assertForbidden();
    }

    public function test_shift_manager_still_manages_deployments(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($manager)
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
        ]);
    }

    public function test_dashboard_and_nav_for_region_supervisor(): void
    {
        $user = User::factory()->regionSupervisor()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Region Supervisor Dashboard', false)
            ->assertSee('Site Postings', false)
            ->assertSee('Absences', false)
            ->assertSee('Desertions', false);
    }
}
