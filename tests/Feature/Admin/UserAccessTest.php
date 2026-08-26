<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_user(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'name' => 'New Shift Manager',
                'email' => 'new.shift@example.com',
                'phone' => '+255700111222',
                'role' => UserRole::ShiftManager->value,
                'password' => 'Password@12345',
                'password_confirmation' => 'Password@12345',
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'new.shift@example.com',
            'role' => UserRole::ShiftManager->value,
            'is_active' => true,
        ]);
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($ops)
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_cannot_deactivate_last_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->post(route('users.toggle-active', $admin))
            ->assertSessionHasErrors('user');

        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_super_admin_can_deactivate_other_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $manager = User::factory()->role(UserRole::ShiftManager)->create(['is_active' => true]);

        $this->actingAs($admin)
            ->post(route('users.toggle-active', $manager))
            ->assertRedirect();

        $this->assertFalse($manager->fresh()->is_active);
    }

    public function test_roles_matrix_is_visible_to_super_admin(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Roles & permissions')
            ->assertSee('Manage users & account access');
    }
}
