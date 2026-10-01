<?php

namespace Tests\Feature\Organization;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\ManpowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DateCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_coverage_counts_required_deployed_and_allocated(): void
    {
        $site = Site::factory()->create([
            'required_guards' => 2,
            'required_day_guards' => 2,
            'required_night_guards' => 0,
        ]);

        Deployment::factory()->count(2)->create([
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $date = now()->toDateString();

        Shift::factory()->create([
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => $date,
            'period' => ShiftPeriod::Day,
            'status' => ShiftStatus::Scheduled,
        ]);

        $snapshot = app(ManpowerService::class)->forSiteOnDate($site, $date);

        $this->assertSame(2, $snapshot['required']);
        $this->assertSame(2, $snapshot['deployed']);
        $this->assertSame(1, $snapshot['allocated']);
        $this->assertSame(1, $snapshot['allocation_shortage']);
        $this->assertSame(1, $snapshot['allocated_day']);
    }

    public function test_coverage_page_shows_updated_manpower_cards_for_the_selected_date(): void
    {
        $user = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_guards' => 3,
            'required_day_guards' => 2,
            'required_night_guards' => 1,
        ]);
        $date = now()->toDateString();

        $this->actingAs($user)
            ->get(route('manpower.coverage', ['date' => $date]))
            ->assertOk()
            ->assertSee('Normal deployed', false)
            ->assertSee('Manpower deficit', false)
            ->assertSee('Remaining shortage', false)
            ->assertSee($site->name, false);
    }
}
