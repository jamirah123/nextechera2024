<?php

namespace Tests\Feature\Hr;

use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Site;
use App\Models\User;
use App\Services\AbsenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbsenceDeploymentBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_absence_must_be_recorded_for_a_past_day(): void
    {
        Carbon::setTestNow('2026-08-02 10:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'current_site_id' => $site->id,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('absences.store'), [
                'guard_id' => $guard->id,
                'absence_date' => '2026-08-02',
                'reason' => 'no_show',
                'site_id' => $site->id,
            ])
            ->assertSessionHasErrors('absence_date');
    }

    public function test_absence_ends_deployment_and_returns_guard_to_board_from_following_day(): void
    {
        Carbon::setTestNow('2026-08-02 10:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'current_site_id' => $site->id,
            'region_id' => $site->region_id,
            'full_name' => 'Absent Guard',
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $this->actingAs($ops)
            ->post(route('absences.store'), [
                'guard_id' => $guard->id,
                'absence_date' => '2026-08-01',
                'reason' => 'no_show',
                'site_id' => $site->id,
            ])
            ->assertRedirect();

        $guard->refresh();
        $deployment->refresh();

        $this->assertSame(OperationalStatus::Absent, $guard->operational_status);
        $this->assertFalse($deployment->is_current);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Absent Guard', false);

        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->fresh()->operational_status);
    }

    public function test_absent_guard_appears_on_deployment_board_from_the_following_day(): void
    {
        Carbon::setTestNow('2026-08-03 08:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::Absent,
            'region_id' => $site->region_id,
            'full_name' => 'Released Guard',
        ]);

        Absence::query()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'absence_date' => '2026-08-01',
            'reason' => 'no_show',
            'reported_at' => now()->subDay(),
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Released Guard', false);

        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->fresh()->operational_status);
    }

    public function test_guard_stays_off_board_on_the_missed_day(): void
    {
        Carbon::setTestNow('2026-08-01 18:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::Absent,
            'region_id' => $site->region_id,
            'full_name' => 'Still Absent Guard',
        ]);

        Absence::query()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'absence_date' => '2026-08-01',
            'reason' => 'no_show',
            'reported_at' => now(),
        ]);

        app(AbsenceService::class)->releaseEligibleAbsentGuards(now());

        $this->assertSame(OperationalStatus::Absent, $guard->fresh()->operational_status);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertDontSee('Still Absent Guard', false);
    }
}
