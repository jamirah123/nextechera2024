<?php

namespace Tests\Feature\Notifications;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\UserRole;
use App\Mail\WorkflowActionMail;
use App\Models\EmailDelivery;
use App\Models\User;
use App\Services\AuditService;
use App\Support\Notifications\EmailFailureMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_important_action_queues_one_email_and_records_delivery(): void
    {
        Mail::fake();

        $hr = User::factory()->role(UserRole::HrManager)->create(['email' => 'hr@company.test']);
        $requester = User::factory()->role(UserRole::ShiftManager)->create(['email' => 'shift@company.test']);

        app(AuditService::class)->log(
            action: 'leave.requested',
            summary: 'Leave request submitted for PSG010.',
            category: AuditCategory::Hr,
            severity: AuditSeverity::Notice,
            actor: $requester,
        );

        Mail::assertQueued(WorkflowActionMail::class, function (WorkflowActionMail $mail) use ($hr) {
            return $mail->hasTo($hr->email)
                && $mail->priority === 'important'
                && $mail->deliveryId !== null;
        });

        $this->assertSame(1, EmailDelivery::query()->count());
        $this->assertDatabaseHas('email_deliveries', [
            'recipient_email' => 'hr@company.test',
            'action' => 'leave.requested',
            'status' => 'queued',
            'priority' => 'important',
        ]);
    }

    public function test_repeating_the_same_audit_does_not_queue_a_second_email(): void
    {
        Mail::fake();

        User::factory()->role(UserRole::HrManager)->create(['email' => 'hr@company.test']);
        $requester = User::factory()->role(UserRole::ShiftManager)->create();

        $log = app(AuditService::class)->log(
            action: 'leave.requested',
            summary: 'Leave request submitted for PSG010.',
            category: AuditCategory::Hr,
            severity: AuditSeverity::Notice,
            actor: $requester,
        );

        app(\App\Services\WorkflowMailService::class)->notifyFromAudit($log);

        Mail::assertQueued(WorkflowActionMail::class, 1);
        $this->assertSame(1, EmailDelivery::query()->count());
    }

    public function test_payroll_calculation_stays_in_app_until_email_is_enabled_for_that_event(): void
    {
        Mail::fake();

        User::factory()->role(UserRole::FinanceManager)->create();

        app(AuditService::class)->log(
            action: 'payroll.calculated',
            summary: 'Payroll calculated for August 2026.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Notice,
        );

        Mail::assertNothingQueued();
        $this->assertSame(0, EmailDelivery::query()->count());
    }

    public function test_critical_backup_failure_still_emails_when_workflow_email_is_off(): void
    {
        Mail::fake();
        config(['psg.notifications.workflow_email_enabled' => false]);

        $admin = User::factory()->role(UserRole::SuperAdmin)->create(['email' => 'admin@company.test']);
        $actor = User::factory()->role(UserRole::ManagingDirector)->create();

        app(AuditService::class)->log(
            action: 'backup.failed',
            summary: 'Nightly backup failed.',
            category: AuditCategory::System,
            severity: AuditSeverity::Critical,
            actor: $actor,
        );

        Mail::assertQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($admin->email) && $mail->priority === 'critical');
    }

    public function test_salary_email_omits_the_amount_unless_explicitly_enabled(): void
    {
        Mail::fake();

        $finance = User::factory()->role(UserRole::FinanceManager)->create(['email' => 'finance@company.test']);
        $hr = User::factory()->role(UserRole::HrManager)->create();

        app(AuditService::class)->log(
            action: 'guard.salary_changed',
            summary: 'Salary revised PSG010 1,500,000.00 from 2026-09-01.',
            category: AuditCategory::Hr,
            severity: AuditSeverity::Notice,
            actor: $hr,
        );

        Mail::assertQueued(WorkflowActionMail::class, function (WorkflowActionMail $mail) use ($finance) {
            return $mail->hasTo($finance->email)
                && ! str_contains($mail->summary, '1,500,000');
        });
    }

    public function test_admin_can_send_a_test_email_without_seeing_a_transport_error(): void
    {
        Mail::fake();

        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->post(route('settings.test-email'), ['email' => 'ops@company.test'])
            ->assertRedirect()
            ->assertSessionHas('status', 'Test email sent successfully.');

        Mail::assertSent(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo('ops@company.test'));
        $this->assertDatabaseHas('email_deliveries', [
            'action' => 'system.test_email',
            'recipient_email' => 'ops@company.test',
            'status' => 'sent',
        ]);
    }

    public function test_admin_sees_a_safe_message_when_the_test_email_fails(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SQLSTATE[HY000] password denied at C:\\secret\\.env'));

        $admin = User::factory()->role(UserRole::SuperAdmin)->create();

        $this->actingAs($admin)
            ->post(route('settings.test-email'), ['email' => 'ops@company.test'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Test email could not be sent. Check mail configuration and system logs.');

        $this->assertDatabaseHas('email_deliveries', [
            'action' => 'system.test_email',
            'status' => 'failed',
            'failure_reason' => 'The mail service could not deliver this message.',
        ]);
    }

    public function test_failure_messages_do_not_keep_credentials_or_sql(): void
    {
        $this->assertSame(
            'The mail service could not deliver this message.',
            EmailFailureMessage::sanitize('SQLSTATE[HY000] Access denied for password at vendor\\laravel'),
        );
    }

    public function test_shift_manager_cannot_open_delivery_history(): void
    {
        $shift = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($shift)
            ->get(route('email-deliveries.index'))
            ->assertForbidden();
    }
}
