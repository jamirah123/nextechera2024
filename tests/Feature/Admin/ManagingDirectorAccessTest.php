<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\Access\Access;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagingDirectorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_managing_director_has_executive_access_except_platform_administration(): void
    {
        $director = User::factory()->managingDirector()->create();

        $this->assertTrue(Access::userCan($director, 'admin.users_manage'));
        $this->assertTrue(Access::userCan($director, 'admin.audit_view'));
        $this->assertTrue(Access::userCan($director, 'organization.manage'));
        $this->assertTrue(Access::userCan($director, 'finance.manage'));
        $this->assertTrue(Access::userCan($director, 'finance.payroll.approve'));
        $this->assertTrue(Access::userCan($director, 'reporting.view_export'));

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $this->assertTrue(Access::userCan($finance, 'finance.manage'));
        $this->assertFalse(Access::userCan($finance, 'finance.payroll.approve'));

        $this->assertFalse(Access::userCan($director, 'admin.settings_manage'));
        $this->assertFalse(Access::userCan($director, 'admin.roles_manage'));
    }

    public function test_managing_director_can_manage_users_and_audit_but_not_platform_admin(): void
    {
        $director = User::factory()->managingDirector()->create();

        $this->actingAs($director)
            ->get(route('users.index'))
            ->assertOk();

        $this->actingAs($director)
            ->get(route('audit.index'))
            ->assertOk();

        $this->actingAs($director)
            ->get(route('settings.index'))
            ->assertForbidden();

        $this->actingAs($director)
            ->get(route('roles.index'))
            ->assertForbidden();
    }

    public function test_managing_director_navigation_excludes_platform_settings_and_roles(): void
    {
        $director = User::factory()->managingDirector()->create();

        $response = $this->actingAs($director)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Users', false);
        $response->assertSee('Finance', false);
        $response->assertSee('Outstanding', false);
        $response->assertSee('Awaiting deploy', false);
        $response->assertDontSee('Platform Settings', false);
        $response->assertDontSee('Roles & Permissions', false);
    }

    public function test_managing_director_cannot_create_super_admin(): void
    {
        $director = User::factory()->managingDirector()->create();

        $this->actingAs($director)
            ->post(route('users.store'), [
                'name' => 'Rogue Admin',
                'email' => 'rogue.admin@example.com',
                'role' => UserRole::SuperAdmin->value,
                'password' => 'Password@12345',
                'password_confirmation' => 'Password@12345',
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', [
            'email' => 'rogue.admin@example.com',
        ]);
    }

    public function test_managing_director_cannot_promote_user_to_super_admin(): void
    {
        $director = User::factory()->managingDirector()->create();
        $manager = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($director)
            ->put(route('users.update', $manager), [
                'name' => $manager->name,
                'email' => $manager->email,
                'role' => UserRole::SuperAdmin->value,
                'is_active' => true,
            ])
            ->assertSessionHasErrors('role');

        $this->assertSame(UserRole::ShiftManager, $manager->fresh()->role);
    }
}
