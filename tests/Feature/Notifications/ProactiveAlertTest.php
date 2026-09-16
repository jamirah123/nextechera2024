<?php

namespace Tests\Feature\Notifications;

use App\Enums\CoverageStatus;
use App\Enums\GuardDocumentType;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Mail\WorkflowActionMail;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Guard;
use App\Models\GuardAttachment;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\ManpowerService;
use App\Services\ProactiveAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ProactiveAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_invoice_command_logs_proactive_alert(): void
    {
        $client = Client::factory()->create();

        $invoice = Invoice::query()->create([
            'reference' => 'INV-OVERDUE-ALERT',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->subMonth()->startOfMonth(),
            'period_end' => now()->subMonth()->endOfMonth(),
            'issue_date' => now()->subMonth(),
            'due_date' => now()->subDays(2),
            'currency' => 'UGX',
            'subtotal' => 500000,
            'tax_amount' => 0,
            'total' => 500000,
            'amount_paid' => 0,
            'balance' => 500000,
        ]);

        $this->artisan('psg:mark-overdue-invoices')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'finance.invoice_overdue',
            'subject_type' => Invoice::class,
            'subject_id' => $invoice->id,
        ]);
    }

    public function test_scan_command_alerts_understaffed_site(): void
    {
        $site = Site::factory()->create([
            'required_guards' => 4,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
        ]);

        $this->mock(ManpowerService::class, function ($mock) use ($site): void {
            $mock->shouldReceive('forSite')
                ->twice()
                ->with(\Mockery::on(fn (Site $s) => $s->id === $site->id))
                ->andReturn([
                    'required' => 4,
                    'required_day' => 2,
                    'required_night' => 2,
                    'scheduled' => 1,
                    'available' => 1,
                    'deployed' => 1,
                    'deployed_day' => 1,
                    'deployed_night' => 0,
                    'shortage' => 3,
                    'surplus' => 0,
                    'shortage_day' => 1,
                    'shortage_night' => 2,
                    'coverage_percent' => 25.0,
                    'contracted' => 4,
                    'sla_shortage' => 0,
                    'status' => CoverageStatus::Understaffed,
                ]);
        });

        $this->artisan('psg:scan-proactive-alerts')
            ->expectsOutputToContain('1 understaffed')
            ->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'site.understaffed',
            'subject_type' => Site::class,
            'subject_id' => $site->id,
        ]);
    }

    public function test_scan_command_reminds_on_stale_pending_leave(): void
    {
        Carbon::setTestNow('2026-08-27 10:00:00');

        $guard = Guard::factory()->create();
        $leave = Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => LeaveType::Annual,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'status' => LeaveStatus::Pending,
            'requested_by' => User::factory()->create()->id,
        ]);
        $leave->forceFill([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ])->save();

        $this->artisan('psg:scan-proactive-alerts')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'leave.pending_reminder',
            'subject_type' => Leave::class,
            'subject_id' => $leave->id,
        ]);

        Carbon::setTestNow();
    }

    public function test_scan_command_alerts_expiring_guard_document(): void
    {
        $guard = Guard::factory()->create();

        $attachment = GuardAttachment::query()->create([
            'guard_id' => $guard->id,
            'label' => 'Medical certificate',
            'document_type' => GuardDocumentType::Medical,
            'expires_at' => now()->addDays(10)->toDateString(),
            'original_name' => 'medical.pdf',
            'path' => 'guards/'.$guard->id.'/medical.pdf',
            'mime_type' => 'application/pdf',
            'size' => 1200,
        ]);

        $this->artisan('psg:scan-proactive-alerts')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'guard.document_expiring',
            'subject_type' => Guard::class,
            'subject_id' => $guard->id,
        ]);

        $this->assertNotNull($attachment->fresh());
    }

    public function test_missed_shift_audit_triggers_workflow_email(): void
    {
        Mail::fake();
        Carbon::setTestNow('2026-08-27 12:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create(['email' => 'ops@company.test']);

        Shift::factory()->create([
            'status' => ShiftStatus::Confirmed,
            'starts_at' => now()->subHours(10),
            'ends_at' => now()->subHour(),
        ]);

        $this->artisan('psg:sync-shift-statuses')->assertSuccessful();

        Mail::assertQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($ops->email));

        Carbon::setTestNow();
    }

    public function test_proactive_alerts_respect_deduplication(): void
    {
        $service = app(ProactiveAlertService::class);
        $guard = Guard::factory()->create();

        $attachment = GuardAttachment::query()->create([
            'guard_id' => $guard->id,
            'label' => 'National ID',
            'document_type' => GuardDocumentType::NationalId,
            'expires_at' => now()->addDays(5)->toDateString(),
            'original_name' => 'id.pdf',
            'path' => 'guards/'.$guard->id.'/id.pdf',
            'mime_type' => 'application/pdf',
            'size' => 900,
        ]);

        $this->assertTrue($service->alertDocument($attachment, expired: false));
        $this->assertFalse($service->alertDocument($attachment->fresh(), expired: false));

        $this->assertSame(1, AuditLog::query()->where('action', 'guard.document_expiring')->count());
    }
}
