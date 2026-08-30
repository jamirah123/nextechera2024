<?php

namespace Tests\Feature\Hr;

use App\Enums\DeploymentStatus;
use App\Enums\DesertionHrStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesertionReturnTest extends TestCase
{
    use RefreshDatabase;

    public function test_returned_deserted_guard_appears_on_deployment_board(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::Deserted,
            'current_site_id' => $site->id,
            'region_id' => $site->region_id,
            'full_name' => 'Returned Desertion Guard',
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $desertion = Desertion::query()->create([
            'guard_id' => $guard->id,
            'last_known_site_id' => $site->id,
            'date_reported' => now()->subDays(3)->toDateString(),
            'hr_status' => DesertionHrStatus::Confirmed,
            'circumstances' => 'Left post without notice.',
        ]);

        $this->actingAs($hr)
            ->post(route('desertions.status', $desertion), [
                'hr_status' => DesertionHrStatus::Returned->value,
                'notes' => 'Guard reported back to HR.',
            ])
            ->assertRedirect();

        $guard->refresh();
        $deployment->refresh();

        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);
        $this->assertNull($guard->current_site_id);
        $this->assertFalse($deployment->is_current);
        $this->assertSame(DesertionHrStatus::Returned, $desertion->fresh()->hr_status);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Returned Desertion Guard', false);
    }

    public function test_desertions_can_be_filtered_by_reported_date(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create(['region_id' => $site->region_id, 'full_name' => 'Date Filter Guard']);

        Desertion::query()->create([
            'guard_id' => $guard->id,
            'last_known_site_id' => $site->id,
            'date_reported' => '2026-08-01',
            'hr_status' => DesertionHrStatus::Reported,
            'circumstances' => 'Missing.',
        ]);

        $this->actingAs($hr)
            ->get(route('desertions.index', ['date' => '2026-08-01']))
            ->assertOk()
            ->assertSee('Date Filter Guard', false);

        $this->actingAs($hr)
            ->get(route('desertions.index', ['date' => '2026-08-02']))
            ->assertOk()
            ->assertDontSee('Date Filter Guard', false);
    }

    public function test_desertion_report_ends_active_deployment(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'current_site_id' => $site->id,
            'region_id' => $site->region_id,
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $this->actingAs($manager)
            ->post(route('desertions.store'), [
                'guard_id' => $guard->id,
                'date_reported' => now()->toDateString(),
                'last_known_duty_date' => now()->subDay()->toDateString(),
                'last_known_site_id' => $site->id,
                'circumstances' => 'Did not report for duty.',
            ])
            ->assertRedirect();

        $this->assertSame(OperationalStatus::Deserted, $guard->fresh()->operational_status);
        $this->assertFalse($deployment->fresh()->is_current);
    }
}
