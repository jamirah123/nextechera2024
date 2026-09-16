<?php

namespace Tests\Feature\Audit;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_and_ops_can_view_audit_logs(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        app(AuditService::class)->log(
            action: 'test.event',
            summary: 'Test audit event',
            category: AuditCategory::System,
            severity: AuditSeverity::Info,
            actor: $admin,
        );

        $this->actingAs($admin)
            ->get(route('audit.index'))
            ->assertOk()
            ->assertSee('Test audit event');

        $this->actingAs($ops)
            ->get(route('audit.index'))
            ->assertOk();
    }

    public function test_shift_manager_cannot_view_audit_logs(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($manager)
            ->get(route('audit.index'))
            ->assertForbidden();
    }

    public function test_audit_logs_are_immutable(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $log = app(AuditService::class)->log(
            action: 'security.test',
            summary: 'Immutable check',
            actor: $admin,
        );

        $this->expectException(\LogicException::class);
        $log->update(['summary' => 'Changed']);
    }

    public function test_login_creates_audit_event(): void
    {
        $user = User::factory()->role(UserRole::OperationsManager)->create([
            'email' => 'ops-audit@example.com',
            'password' => 'Password@123',
        ]);

        $this->post(route('login.store'), [
            'email' => 'ops-audit@example.com',
            'password' => 'Password@123',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login',
            'actor_id' => $user->id,
        ]);
    }

    public function test_audit_log_captures_application_timezone_wall_clock(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $before = now()->timezone(config('app.timezone'))->subSecond();
        $log = app(AuditService::class)->log(
            action: 'time.check',
            summary: 'Timezone stamp check',
            actor: $admin,
        );
        $after = now()->timezone(config('app.timezone'))->addSecond();

        $this->assertNotNull($log->created_at);
        $this->assertTrue($log->created_at->betweenIncluded($before, $after));
        $this->assertStringContainsString('EAT', $log->occurredAtLabel());
    }

    public function test_shift_manager_cannot_override_without_permission(): void
    {
        $this->assertFalse(
            User::factory()->role(UserRole::ShiftManager)->create()->can('override', Shift::class)
        );

        $this->assertTrue(
            User::factory()->role(UserRole::OperationsManager)->create()->can('override', Shift::class)
        );
    }
}
