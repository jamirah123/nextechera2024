<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_receives_its_dashboard(): void
    {
        $cases = [
            [UserRole::SuperAdmin, 'Super Admin Dashboard'],
            [UserRole::ManagingDirector, 'Managing Director Dashboard'],
            [UserRole::OperationsManager, 'Operations Dashboard'],
            [UserRole::HrManager, 'HR Dashboard'],
            [UserRole::ShiftManager, 'Shift Manager Dashboard'],
            [UserRole::FinanceManager, 'Finance Dashboard'],
            [UserRole::RegionSupervisor, 'Region Supervisor Dashboard'],
        ];

        foreach ($cases as [$role, $title]) {
            $user = $role === UserRole::RegionSupervisor
                ? User::factory()->regionSupervisor()->create()
                : User::factory()->role($role)->create();

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk();
            $response->assertSee($title, false);
            $response->assertSee($user->name);
            $response->assertDontSee('Your modules', false);
            $response->assertSee('Sign out', false);
        }
    }

    public function test_dashboard_shows_profile_menu_links(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(route('profile.show'), false);
        $response->assertSee(route('profile.edit'), false);
    }
}
