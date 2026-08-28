<?php

namespace Tests\Feature\Shifts;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkShiftAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_manager_can_open_allocation_board(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
        ]);
        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $this->actingAs($manager)
            ->get(route('shifts.allocate'))
            ->assertOk()
            ->assertSee($guard->full_name, false)
            ->assertSee('Allocate selected', false);
    }

    public function test_shift_manager_can_bulk_allocate_shifts(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
        ]);
        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $date = now()->toDateString();

        $this->actingAs($manager)
            ->post(route('shifts.allocate.store'), [
                'shift_date' => $date,
                'selected' => [$deployment->id],
                'rows' => [
                    $deployment->id => [
                        'period' => ShiftPeriod::Day->value,
                        'shift_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'period' => ShiftPeriod::Day->value,
            'shift_type' => ShiftType::Normal->value,
        ]);
    }

    public function test_undeployed_guard_does_not_appear_on_allocate_board(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($manager)
            ->get(route('shifts.allocate'))
            ->assertOk()
            ->assertDontSee($guard->full_name, false);
    }

    public function test_night_posting_allocated_for_day_period_is_overtime(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
        ]);
        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Night,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('shifts.allocate.store'), [
                'shift_date' => now()->toDateString(),
                'selected' => [$deployment->id],
                'rows' => [
                    $deployment->id => [
                        'period' => ShiftPeriod::Day->value,
                        'shift_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'guard_id' => $guard->id,
            'period' => ShiftPeriod::Day->value,
            'shift_type' => ShiftType::Overtime->value,
        ]);
    }
}
