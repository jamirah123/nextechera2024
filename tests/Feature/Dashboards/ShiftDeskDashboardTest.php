<?php

namespace Tests\Feature\Dashboards;

use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftDeskDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_manager_dashboard_shows_work_queue(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Today’s work queue', false)
            ->assertSee('Awaiting deploy', false)
            ->assertSee('Needs allocation', false)
            ->assertDontSee('Daily workflow', false)
            ->assertSee(route('deployments.board'), false)
            ->assertSee(route('deployments.board'), false);
    }
}
