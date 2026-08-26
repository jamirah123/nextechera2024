<?php

namespace Tests\Feature\Dashboards;

use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_landing_shows_live_operations_pulse(): void
    {
        $user = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Live operations')
            ->assertSee('Open company dashboard');
    }

    public function test_company_region_site_and_guard_dashboards_render(): void
    {
        $user = User::factory()->role(UserRole::ShiftManager)->create();
        $region = Region::factory()->create(['name' => 'Central Ops Region']);
        $site = Site::factory()->create([
            'region_id' => $region->id,
            'name' => 'HQ Gate Site',
            'required_guards' => 4,
        ]);
        $guard = Guard::factory()->create([
            'region_id' => $region->id,
            'current_site_id' => $site->id,
            'full_name' => 'Dashboard Test Guard',
        ]);

        $this->actingAs($user)
            ->get(route('ops-dashboards.company'))
            ->assertOk()
            ->assertSee('Company operations')
            ->assertSee('Central Ops Region');

        $this->actingAs($user)
            ->get(route('ops-dashboards.region', $region))
            ->assertOk()
            ->assertSee('HQ Gate Site');

        $this->actingAs($user)
            ->get(route('ops-dashboards.site', $site))
            ->assertOk()
            ->assertSee('Deployed guards');

        $this->actingAs($user)
            ->get(route('ops-dashboards.guard', $guard))
            ->assertOk()
            ->assertSee('Dashboard Test Guard');
    }
}
