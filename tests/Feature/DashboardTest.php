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
            [UserRole::SuperAdmin, 'Super Admin Dashboard', 'System Control Center'],
            [UserRole::OperationsManager, 'Operations Dashboard', 'Operations Command'],
            [UserRole::HrManager, 'HR Dashboard', 'Human Resources'],
            [UserRole::ShiftManager, 'Shift Manager Dashboard', 'Shift Operations'],
            [UserRole::FinanceManager, 'Finance Dashboard', 'Financial Oversight'],
        ];

        foreach ($cases as [$role, $title, $eyebrow]) {
            $user = User::factory()->role($role)->create();

            $response = $this->actingAs($user)->get(route('dashboard'));

            $response->assertOk();
            $response->assertSee($title, false);
            $response->assertSee($eyebrow, false);
            $response->assertSee($user->name, false);
            $response->assertSee('Your modules', false);
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
