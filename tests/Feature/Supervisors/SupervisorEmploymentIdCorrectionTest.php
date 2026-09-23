<?php

namespace Tests\Feature\Supervisors;

use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Region;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\SupervisorGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorEmploymentIdCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_save_supervisor_keeping_shared_employment_id(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $region = Region::factory()->create();
        $supervisor = Supervisor::factory()->create([
            'region_id' => $region->id,
            'name' => 'Ruth Asiimwe',
        ]);

        $profiles = app(SupervisorGuardService::class)->ensureEmployeeProfiles($supervisor);
        $employmentId = $profiles['guard']->employment_id;

        $this->assertSame($employmentId, $profiles['staff']->employment_id);

        $this->actingAs($admin)
            ->put(route('supervisors.update', $supervisor), [
                'employment_id' => $employmentId,
                'name' => 'Ruth Asiimwe',
                'phone' => $supervisor->phone,
                'email' => $supervisor->email,
                'region_id' => $region->id,
                'status' => $supervisor->status->value,
            ])
            ->assertRedirect(route('supervisors.show', $supervisor))
            ->assertSessionDoesntHaveErrors('employment_id');
    }

    public function test_super_admin_can_change_supervisor_employment_id_across_linked_profiles(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $region = Region::factory()->create();
        $supervisor = Supervisor::factory()->create([
            'region_id' => $region->id,
            'name' => 'Ruth Asiimwe',
        ]);

        app(SupervisorGuardService::class)->ensureEmployeeProfiles($supervisor);

        $this->actingAs($admin)
            ->put(route('supervisors.update', $supervisor), [
                'employment_id' => 'PSG777',
                'reason' => 'Correct duplicate staff/guard ID conflict',
                'name' => 'Ruth Asiimwe',
                'phone' => $supervisor->phone,
                'email' => $supervisor->email,
                'region_id' => $region->id,
                'status' => $supervisor->status->value,
            ])
            ->assertRedirect(route('supervisors.show', $supervisor))
            ->assertSessionDoesntHaveErrors('employment_id');

        $supervisor->refresh()->load(['guardProfile', 'staffProfile']);
        $this->assertSame('PSG777', $supervisor->guardProfile?->employment_id);
        $this->assertSame('PSG777', $supervisor->staffProfile?->employment_id);
        $this->assertSame(EmploymentStatus::Active, $supervisor->staffProfile?->employment_status);
    }
}
