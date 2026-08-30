<?php

namespace Tests\Feature\Supervisors;

use App\Enums\DeploymentShiftType;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\DeploymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupervisorCoverShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_deploy_creates_guard_profile_and_scheduled_shift(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id, 'name' => 'James Otieno']);

        $this->actingAs($ops)
            ->post(route('supervisors.deploy.store', $supervisor), [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'work_shift_type' => DeploymentShiftType::Night->value,
                'start_date' => now()->toDateString(),
                'notes' => 'Covering shortage',
            ])
            ->assertRedirect(route('supervisors.show', $supervisor));

        $supervisor->refresh()->load('guardProfile');

        $this->assertNotNull($supervisor->guard_id);
        $this->assertSame('Supervisor', $supervisor->guardProfile->rank_designation);

        $shift = Shift::query()->where('guard_id', $supervisor->guard_id)->first();
        $this->assertNotNull($shift);
        $this->assertSame(ShiftType::Overtime, $shift->shift_type);
        $this->assertSame(ShiftStatus::Scheduled, $shift->status);
    }

    public function test_completed_supervisor_shifts_appear_on_monthly_report_with_overtime(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id, 'name' => 'Mary Wambui']);

        $deployment = app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => $site->id,
            'shift_type' => DeploymentShiftType::Day->value,
            'work_shift_type' => DeploymentShiftType::Night->value,
            'start_date' => now()->toDateString(),
        ]);

        $supervisor->refresh();

        Shift::query()
            ->where('guard_id', $supervisor->guard_id)
            ->update(['status' => ShiftStatus::Completed->value]);

        $this->actingAs($finance)
            ->get(route('reports.monthly-shifts', [
                'year' => now()->year,
                'month' => now()->month,
            ]))
            ->assertOk()
            ->assertSee('Mary Wambui')
            ->assertSee('Supervisor', false)
            ->assertSee($supervisor->guardProfile->employment_id);
    }

    public function test_supervisor_payroll_profiles_are_hidden_from_deployment_board(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $region = Region::factory()->create();
        $supervisor = Supervisor::factory()->create(['region_id' => $region->id]);

        app(DeploymentService::class)->deploySupervisor($supervisor, [
            'site_id' => Site::factory()->create(['region_id' => $region->id])->id,
            'shift_type' => DeploymentShiftType::Day->value,
            'start_date' => now()->toDateString(),
        ]);

        $supervisor->refresh();

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertDontSee($supervisor->guardProfile->employment_id, false);
    }
}
