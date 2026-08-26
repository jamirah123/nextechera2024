<?php

namespace Tests\Feature;

use App\Enums\AuditCategory;
use App\Enums\UserRole;
use App\Models\Shift;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_includes_live_notification_bell_for_all_roles(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('notificationBell', false)
                ->assertSee('/notifications', false);
        }
    }

    public function test_feed_returns_role_relevant_events_and_excludes_own_actions(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();
        $financeManager = User::factory()->role(UserRole::FinanceManager)->create();

        $shift = Shift::factory()->create();

        app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Shift scheduled for review.',
            category: AuditCategory::Shift,
            subject: $shift,
            actor: $admin,
        );

        app(AuditService::class)->log(
            action: 'finance.invoice_created',
            summary: 'Draft invoice INV-001 created.',
            category: AuditCategory::Finance,
            actor: $admin,
        );

        $this->actingAs($shiftManager)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonFragment(['summary' => 'Shift scheduled for review.'])
            ->assertJsonMissing(['summary' => 'Draft invoice INV-001 created.'])
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($financeManager)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonFragment(['summary' => 'Draft invoice INV-001 created.'])
            ->assertJsonMissing(['summary' => 'Shift scheduled for review.'])
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($admin)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonMissing(['summary' => 'Shift scheduled for review.'])
            ->assertJsonMissing(['summary' => 'Draft invoice INV-001 created.'])
            ->assertJsonPath('unread_count', 0);
    }

    public function test_mark_read_clears_unread_count(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();

        app(AuditService::class)->log(
            action: 'shift.status_changed',
            summary: 'Shift marked completed.',
            category: AuditCategory::Shift,
            actor: $admin,
        );

        $this->actingAs($shiftManager)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($shiftManager)
            ->postJson(route('notifications.mark-read'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($shiftManager)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('unread_count', 0);
    }

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson(route('notifications.index'))
            ->assertUnauthorized();

        $this->postJson(route('notifications.mark-read'))
            ->assertUnauthorized();
    }
}
