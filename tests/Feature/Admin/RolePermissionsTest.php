<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\User;
use App\Support\Access\RolePermissionService;
use App\Support\Navigation\NavigationAccess;
use App\Support\Navigation\RoleNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_and_update_role_permissions(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($admin)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('Permission matrix')
            ->assertSee('Clone permissions')
            ->assertSee('Save permissions');

        $this->actingAs($admin)
            ->put(route('roles.permissions.update'), [
                'permissions' => [
                    'guards.manage' => [
                        UserRole::ShiftManager->value => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('roles.index'));

        app(RolePermissionService::class)->flushCache();

        $this->assertTrue(app(RolePermissionService::class)->roleCan(UserRole::ShiftManager, 'guards.manage'));
        $this->assertTrue($shiftManager->can('create', Guard::class));
    }

    public function test_super_admin_can_clone_permissions_between_roles(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->post(route('roles.permissions.clone'), [
                'source_role' => UserRole::HrManager->value,
                'target_role' => UserRole::FinanceManager->value,
            ])
            ->assertRedirect(route('roles.index'));

        app(RolePermissionService::class)->flushCache();

        $service = app(RolePermissionService::class);

        $this->assertTrue($service->roleCan(UserRole::FinanceManager, 'guards.manage'));
        $this->assertFalse($service->roleCan(UserRole::FinanceManager, 'operations.shifts_manage'));
    }

    public function test_navigation_hides_items_without_permission(): void
    {
        $service = app(RolePermissionService::class);
        $matrix = $service->matrix();
        $matrix['guards.view'] = array_values(array_filter(
            $matrix['guards.view'],
            fn (string $role) => $role !== UserRole::FinanceManager->value,
        ));
        $service->sync($matrix);
        $service->flushCache();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $navigation = RoleNavigation::for($finance);
        $labels = collect($navigation)->pluck('label')->all();

        $this->assertNotContains('Guards', $labels);
        $this->assertContains('Dashboard', $labels);
        $this->assertContains('Client Billing', $labels);
    }

    public function test_navigation_access_filters_administration_children(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        $this->assertTrue(NavigationAccess::canSeeHref($ops, route('audit.index')));
        $this->assertFalse(NavigationAccess::canSeeHref($ops, route('users.index')));
    }

    public function test_super_admin_can_reset_permissions_to_defaults(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        app(RolePermissionService::class)->sync([
            'guards.manage' => [],
        ]);

        $this->actingAs($admin)
            ->post(route('roles.permissions.reset'))
            ->assertRedirect(route('roles.index'));

        app(RolePermissionService::class)->flushCache();

        $this->assertTrue(app(RolePermissionService::class)->roleCan(UserRole::HrManager, 'guards.manage'));
        $this->assertFalse(app(RolePermissionService::class)->roleCan(UserRole::ShiftManager, 'guards.manage'));
    }

    public function test_revoked_permission_does_not_fall_back_to_catalog_defaults(): void
    {
        $service = app(RolePermissionService::class);
        $matrix = $service->matrix();
        $matrix['guards.manage'] = [];
        $service->sync($matrix);
        $service->flushCache();

        $reloaded = $service->matrix();

        $this->assertSame([], $reloaded['guards.manage']);
        $this->assertFalse($service->roleCan(UserRole::HrManager, 'guards.manage'));
    }

    public function test_non_super_admin_cannot_manage_role_permissions(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($ops)
            ->get(route('roles.index'))
            ->assertForbidden();

        $this->actingAs($ops)
            ->put(route('roles.permissions.update'), [
                'permissions' => [],
            ])
            ->assertForbidden();

        $this->actingAs($ops)
            ->post(route('roles.permissions.clone'), [
                'source_role' => UserRole::HrManager->value,
                'target_role' => UserRole::ShiftManager->value,
            ])
            ->assertForbidden();
    }
}
