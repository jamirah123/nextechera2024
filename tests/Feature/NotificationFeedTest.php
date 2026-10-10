<?php

namespace Tests\Feature;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\NotificationState;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\AuditService;
use App\Services\NotificationRecipientService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    public function test_one_alert_can_be_read_dismissed_and_marked_unread(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();

        $first = app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Night shift opened at Kololo.',
            category: AuditCategory::Shift,
            actor: $admin,
        );
        app(AuditService::class)->log(
            action: 'deployment.created',
            summary: 'Cover assigned at Hoima.',
            category: AuditCategory::Deployment,
            actor: $admin,
        );

        $this->actingAs($shiftManager)
            ->postJson(route('notifications.state', $first), ['action' => 'read'])
            ->assertOk()
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($shiftManager)
            ->getJson(route('notifications.index', ['panel' => 'unread']))
            ->assertJsonMissing(['summary' => 'Night shift opened at Kololo.'])
            ->assertJsonFragment(['summary' => 'Cover assigned at Hoima.']);

        $this->actingAs($shiftManager)
            ->postJson(route('notifications.state', $first), ['action' => 'unread'])
            ->assertJsonPath('unread_count', 2);

        $this->actingAs($shiftManager)
            ->postJson(route('notifications.state', $first), ['action' => 'dismiss'])
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($shiftManager)
            ->getJson(route('notifications.index'))
            ->assertJsonMissing(['summary' => 'Night shift opened at Kololo.']);

        $this->actingAs($shiftManager)
            ->get(route('notifications.index', ['status' => 'dismissed']))
            ->assertOk()
            ->assertSee('Night shift opened at Kololo.');
    }

    public function test_history_page_is_role_filtered_and_important_panel_is_separate(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();
        $operations = User::factory()->role(UserRole::OperationsManager)->create();

        app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Day shift scheduled.',
            category: AuditCategory::Shift,
            actor: $admin,
        );
        app(AuditService::class)->log(
            action: 'site.understaffed',
            summary: 'Alpha Warehouse is short by 2 guards.',
            category: AuditCategory::Organization,
            severity: AuditSeverity::Warning,
            actor: $admin,
        );
        app(AuditService::class)->log(
            action: 'finance.invoice_issued',
            summary: 'Invoice INV-200 issued.',
            category: AuditCategory::Finance,
            actor: $admin,
        );

        $this->actingAs($shiftManager)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Day shift scheduled.')
            ->assertSee('Alpha Warehouse is short by 2 guards.')
            ->assertDontSee('Invoice INV-200 issued.')
            ->assertSee('View all notifications', false);

        $this->actingAs($operations)
            ->getJson(route('notifications.index', ['panel' => 'important']))
            ->assertOk()
            ->assertJsonFragment(['summary' => 'Alpha Warehouse is short by 2 guards.'])
            ->assertJsonMissing(['summary' => 'Day shift scheduled.'])
            ->assertJsonMissing(['summary' => 'Invoice INV-200 issued.']);
    }

    public function test_preferences_hide_finance_alerts_and_keep_backup_failures(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        app(AuditService::class)->log(
            action: 'finance.invoice_overdue',
            summary: 'Invoice INV-9 is overdue.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Warning,
            actor: $admin,
        );
        app(AuditService::class)->log(
            action: 'backup.failed',
            summary: 'Nightly backup failed.',
            category: AuditCategory::System,
            severity: AuditSeverity::Critical,
            actor: null,
        );

        $finance->forceFill([
            'notification_preferences' => ['in_app' => true, 'finance' => false, 'email' => true, 'toasts' => true, 'operational' => true, 'hr' => true, 'system' => true],
        ])->save();

        $this->actingAs($finance)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonMissing(['summary' => 'Invoice INV-9 is overdue.'])
            ->assertJsonPath('unread_count', 0);

        $admin->forceFill([
            'notification_preferences' => ['in_app' => true, 'system' => false, 'email' => true, 'toasts' => true, 'operational' => true, 'hr' => true, 'finance' => true],
        ])->save();

        $this->actingAs($admin->fresh())
            ->getJson(route('notifications.index'))
            ->assertJsonFragment(['summary' => 'Nightly backup failed.']);
    }

    public function test_alerts_older_than_the_retention_window_stay_out_of_the_feed(): void
    {
        config(['psg.notifications.retention_days' => 30]);

        $admin = User::factory()->superAdmin()->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();

        $old = app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Archived shift alert.',
            category: AuditCategory::Shift,
            actor: $admin,
        );

        DB::table('audit_logs')->where('id', $old->id)->update([
            'created_at' => now()->subDays(45),
        ]);

        $this->actingAs($shiftManager)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonMissing(['summary' => 'Archived shift alert.'])
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull(AuditLog::query()->find($old->id));
    }

    public function test_each_user_sees_only_their_own_notifications_and_unread_count(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $userA = User::factory()->role(UserRole::ShiftManager)->create();
        $userB = User::factory()->role(UserRole::FinanceManager)->create();

        app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Only the shift manager is notified.',
            category: AuditCategory::Shift,
            actor: $admin,
            context: ['notify_user_ids' => [$userA->id]],
        );
        app(AuditService::class)->log(
            action: 'finance.invoice_created',
            summary: 'Only finance is notified.',
            category: AuditCategory::Finance,
            actor: $admin,
            context: ['notify_user_ids' => [$userB->id]],
        );

        $this->actingAs($userA)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonFragment(['summary' => 'Only the shift manager is notified.'])
            ->assertJsonMissing(['summary' => 'Only finance is notified.'])
            ->assertJsonPath('unread_count', 1);

        $this->actingAs($userB)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonFragment(['summary' => 'Only finance is notified.'])
            ->assertJsonMissing(['summary' => 'Only the shift manager is notified.'])
            ->assertJsonPath('unread_count', 1);
    }

    public function test_a_user_cannot_open_another_users_notification(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $owner = User::factory()->role(UserRole::HrManager)->create();
        $other = User::factory()->role(UserRole::HrManager)->create();

        $log = app(AuditService::class)->log(
            action: 'leave.requested',
            summary: 'Private leave alert for one HR user.',
            category: AuditCategory::Hr,
            actor: $admin,
            context: ['notify_user_ids' => [$owner->id]],
        );

        $this->actingAs($other)
            ->postJson(route('notifications.state', $log), ['action' => 'read'])
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson(route('notifications.state', $log), ['action' => 'read'])
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($other)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('unread_count', 0);
    }

    public function test_reading_a_notification_does_not_change_another_users_status(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $userA = User::factory()->role(UserRole::ShiftManager)->create();
        $userB = User::factory()->role(UserRole::ShiftManager)->create();

        $log = app(AuditService::class)->log(
            action: 'deployment.created',
            summary: 'Shared deployment alert.',
            category: AuditCategory::Deployment,
            actor: $admin,
            context: ['notify_user_ids' => [$userA->id, $userB->id]],
        );

        $this->actingAs($userA)
            ->postJson(route('notifications.state', $log), ['action' => 'read'])
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($userB)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('unread_count', 1)
            ->assertJsonFragment(['summary' => 'Shared deployment alert.', 'is_unread' => true]);
    }

    public function test_region_supervisors_only_receive_their_own_site_alerts(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $westernRegion = Region::factory()->create(['name' => 'Western']);
        $easternRegion = Region::factory()->create(['name' => 'Eastern']);
        $westernSupervisor = Supervisor::factory()->create(['region_id' => $westernRegion->id]);
        $easternSupervisor = Supervisor::factory()->create(['region_id' => $easternRegion->id]);
        $site = Site::factory()->create([
            'name' => 'Alpha Warehouse',
            'region_id' => $westernRegion->id,
            'supervisor_id' => $westernSupervisor->id,
        ]);
        $western = User::factory()->regionSupervisor($westernSupervisor->id)->create();
        $eastern = User::factory()->regionSupervisor($easternSupervisor->id)->create();

        app(AuditService::class)->log(
            action: 'site.understaffed',
            summary: 'Alpha Warehouse is short by 2 guards.',
            category: AuditCategory::Organization,
            severity: AuditSeverity::Warning,
            subject: $site,
            actor: $admin,
        );

        $this->actingAs($western)
            ->getJson(route('notifications.index'))
            ->assertJsonFragment(['summary' => 'Alpha Warehouse is short by 2 guards.']);

        $this->actingAs($eastern)
            ->getJson(route('notifications.index'))
            ->assertJsonMissing(['summary' => 'Alpha Warehouse is short by 2 guards.'])
            ->assertJsonPath('unread_count', 0);
    }

    public function test_leave_that_affects_a_shift_notifies_hr_and_the_shift_manager_only(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $shifts = User::factory()->role(UserRole::ShiftManager)->create();
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $guard = Guard::factory()->create();
        $leave = Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => 'annual',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'status' => 'pending',
            'requested_by' => $admin->id,
        ]);
        Shift::factory()->create(['leave_id' => $leave->id, 'guard_id' => $guard->id]);

        app(AuditService::class)->log(
            action: 'leave.requested',
            summary: 'PSG045 requested leave that covers a shift.',
            category: AuditCategory::Hr,
            subject: $leave,
            actor: $admin,
        );

        $this->actingAs($hr)->getJson(route('notifications.index'))
            ->assertJsonFragment(['summary' => 'PSG045 requested leave that covers a shift.']);
        $this->actingAs($shifts)->getJson(route('notifications.index'))
            ->assertJsonFragment(['summary' => 'PSG045 requested leave that covers a shift.']);
        $this->actingAs($finance)->getJson(route('notifications.index'))
            ->assertJsonMissing(['summary' => 'PSG045 requested leave that covers a shift.'])
            ->assertJsonPath('unread_count', 0);
    }

    public function test_company_wide_announcements_are_explicit_and_delivery_is_not_duplicated(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $shifts = User::factory()->role(UserRole::ShiftManager)->create();

        app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Ordinary shift alert.',
            category: AuditCategory::Shift,
            actor: $admin,
        );

        $this->actingAs($finance)
            ->getJson(route('notifications.index'))
            ->assertJsonMissing(['summary' => 'Ordinary shift alert.']);

        $announcement = app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Office closes early on Friday.',
            category: AuditCategory::Shift,
            actor: $admin,
            context: ['company_wide' => true],
        );

        $this->actingAs($finance)
            ->getJson(route('notifications.index'))
            ->assertJsonFragment(['summary' => 'Office closes early on Friday.']);
        $this->actingAs($shifts)
            ->getJson(route('notifications.index'))
            ->assertJsonFragment(['summary' => 'Office closes early on Friday.']);

        app(NotificationRecipientService::class)->deliver($announcement);

        $this->assertSame(
            1,
            NotificationState::query()->where('user_id', $finance->id)->where('audit_log_id', $announcement->id)->count()
        );
    }

    public function test_mark_all_as_read_leaves_another_users_alerts_unread(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $userA = User::factory()->role(UserRole::ShiftManager)->create();
        $userB = User::factory()->role(UserRole::ShiftManager)->create();

        app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Shared shift alert.',
            category: AuditCategory::Shift,
            actor: $admin,
            context: ['notify_user_ids' => [$userA->id, $userB->id]],
        );

        $this->actingAs($userA)
            ->postJson(route('notifications.mark-read'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($userB)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('unread_count', 1)
            ->assertJsonFragment(['summary' => 'Shared shift alert.', 'is_unread' => true]);
    }

    public function test_history_can_be_searched_filtered_and_paged_for_the_current_user(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $shifts = User::factory()->role(UserRole::ShiftManager)->create();

        app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Night cover at Kololo.',
            category: AuditCategory::Shift,
            actor: $admin,
            context: ['notify_user_ids' => [$shifts->id], 'site_name' => 'Kololo', 'employment_id' => 'PSG900'],
        );
        app(AuditService::class)->log(
            action: 'site.understaffed',
            summary: 'Alpha Warehouse is short by 2 guards.',
            category: AuditCategory::Organization,
            severity: AuditSeverity::Warning,
            actor: $admin,
            context: ['notify_user_ids' => [$shifts->id]],
        );

        $this->actingAs($shifts)
            ->get(route('notifications.index', ['q' => 'Kololo', 'group' => 'deployments']))
            ->assertOk()
            ->assertSee('Night cover at Kololo.')
            ->assertDontSee('Alpha Warehouse is short by 2 guards.');

        $this->actingAs($shifts)
            ->get(route('notifications.index', ['q' => 'PSG900']))
            ->assertOk()
            ->assertSee('Night cover at Kololo.')
            ->assertDontSee('Alpha Warehouse is short by 2 guards.');

        $this->actingAs($shifts)
            ->get(route('notifications.index', ['group' => 'manpower', 'read' => 'unread']))
            ->assertOk()
            ->assertSee('Alpha Warehouse is short by 2 guards.')
            ->assertDontSee('Night cover at Kololo.');

        foreach (range(1, 21) as $number) {
            app(AuditService::class)->log(
                action: 'shift.created',
                summary: 'Paged shift alert '.$number.'.',
                category: AuditCategory::Shift,
                actor: $admin,
                context: ['notify_user_ids' => [$shifts->id]],
            );
        }

        $this->actingAs($shifts)
            ->get(route('notifications.index', ['page' => 2, 'group' => 'deployments']))
            ->assertOk()
            ->assertSee('Paged shift alert 1.');
    }

    public function test_opening_a_notification_marks_it_read_and_stops_when_the_record_is_gone(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $shifts = User::factory()->role(UserRole::ShiftManager)->create();
        $shift = Shift::factory()->create();

        $log = app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Open this shift.',
            category: AuditCategory::Shift,
            subject: $shift,
            actor: $admin,
            context: ['notify_user_ids' => [$shifts->id]],
        );

        $this->actingAs($shifts)
            ->from(route('notifications.index'))
            ->post(route('notifications.state', $log), ['action' => 'open'])
            ->assertRedirect(route('shifts.show', $shift));

        $this->actingAs($shifts)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('unread_count', 0);

        $missing = Shift::factory()->create();
        $gone = app(AuditService::class)->log(
            action: 'shift.created',
            summary: 'Missing shift alert.',
            category: AuditCategory::Shift,
            subject: $missing,
            actor: $admin,
            context: ['notify_user_ids' => [$shifts->id]],
        );
        $missing->delete();

        $this->actingAs($shifts)
            ->from(route('notifications.index'))
            ->post(route('notifications.state', $gone), ['action' => 'open'])
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('error', 'This record is no longer available, or you do not have access to it.');
    }

    public function test_a_shift_manager_does_not_see_finance_rows_they_are_not_authorized_to_receive(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $shifts = User::factory()->role(UserRole::ShiftManager)->create();

        $leaked = app(AuditService::class)->log(
            action: 'ledger.journal_posted',
            summary: 'Journal JE-900 posted (UGX 10,000).',
            category: AuditCategory::Finance,
            actor: $admin,
        );
        NotificationState::query()->create([
            'user_id' => $shifts->id,
            'audit_log_id' => $leaked->id,
            'priority' => 'normal',
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
        ]);

        $explicit = app(AuditService::class)->log(
            action: 'finance.invoice_overdue',
            summary: 'Invoice INV-900 is overdue.',
            category: AuditCategory::Finance,
            actor: $admin,
            context: ['notify_user_ids' => [$shifts->id]],
        );

        $this->actingAs($shifts)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonMissing(['summary' => 'Journal JE-900 posted (UGX 10,000).'])
            ->assertJsonFragment(['summary' => 'Invoice INV-900 is overdue.']);

        $this->actingAs($shifts)
            ->post(route('notifications.state', $leaked), ['action' => 'read'])
            ->assertNotFound();

        $this->assertNotNull($explicit->id);
    }

    public function test_the_bell_caps_large_unread_counts(): void
    {
        $user = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('99+', false)
            ->assertSee('Notifications temporarily unavailable', false)
            ->assertSee('You\'re all caught up', false);
    }

    public function test_notification_endpoints_require_authentication(): void
    {
        $this->getJson(route('notifications.index'))
            ->assertUnauthorized();

        $this->postJson(route('notifications.mark-read'))
            ->assertUnauthorized();
    }
}
