<?php

namespace Tests\Feature\Notifications;

use App\Enums\PayrollRunStatus;
use App\Enums\UserRole;
use App\Mail\WorkflowActionMail;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkflowMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_leadership_roles_receive_email_when_payroll_is_submitted(): void
    {
        Mail::fake();

        $leadership = $this->leadershipUsers();
        $finance = $leadership['finance'];

        $run = $this->samplePayrollRun();

        $this->actingAs($finance)
            ->post(route('payroll.submit', $run))
            ->assertRedirect();

        foreach (['admin', 'director', 'ops', 'hr'] as $key) {
            Mail::assertQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($leadership[$key]->email));
        }

        Mail::assertNotQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($finance->email));
    }

    public function test_leadership_roles_receive_email_when_payroll_is_rejected_with_reason(): void
    {
        Mail::fake();

        $leadership = $this->leadershipUsers();
        $finance = $leadership['finance'];
        $director = $leadership['director'];

        $run = $this->samplePayrollRun();
        $run->update([
            'status' => PayrollRunStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $finance->id,
        ]);

        $this->actingAs($director)
            ->post(route('payroll.reject', $run), ['reason' => 'Recheck NSSF deductions'])
            ->assertRedirect();

        foreach (['admin', 'finance', 'ops', 'hr'] as $key) {
            Mail::assertQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($leadership[$key]->email));
        }

        Mail::assertQueued(WorkflowActionMail::class, function (WorkflowActionMail $mail) use ($finance) {
            return $mail->hasTo($finance->email)
                && in_array('Reason: Recheck NSSF deductions', $mail->details, true);
        });

        Mail::assertNotQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($leadership['director']->email));
    }

    public function test_leadership_roles_receive_email_when_payroll_is_approved(): void
    {
        Mail::fake();

        $leadership = $this->leadershipUsers();
        $finance = $leadership['finance'];
        $director = $leadership['director'];

        $run = $this->samplePayrollRun();
        $run->update([
            'status' => PayrollRunStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => $finance->id,
            'deductions_total' => 274.16,
        ]);

        $this->actingAs($director)
            ->post(route('payroll.approve', $run))
            ->assertRedirect();

        foreach (['admin', 'finance', 'ops', 'hr'] as $key) {
            Mail::assertQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($leadership[$key]->email));
        }

        Mail::assertQueued(WorkflowActionMail::class, function (WorkflowActionMail $mail) use ($finance) {
            return $mail->hasTo($finance->email)
                && in_array('Payslips: 1', $mail->details, true)
                && collect($mail->details)->contains(fn (string $line) => str_contains($line, 'Individual payslip emails are not sent'));
        });

        Mail::assertNotQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($director->email));
    }

    public function test_leadership_roles_receive_email_when_payroll_is_marked_paid(): void
    {
        Mail::fake();

        $leadership = $this->leadershipUsers();
        $finance = $leadership['finance'];

        $run = $this->samplePayrollRun();
        $run->update([
            'status' => PayrollRunStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $leadership['director']->id,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.pay', $run))
            ->assertRedirect();

        foreach (['admin', 'director', 'ops', 'hr'] as $key) {
            Mail::assertQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($leadership[$key]->email));
        }

        Mail::assertNotQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($finance->email));
    }

    public function test_hr_approver_receives_email_when_leave_is_requested(): void
    {
        Mail::fake();

        $ops = User::factory()->role(UserRole::OperationsManager)->create(['email' => 'ops@company.test']);
        $hr = User::factory()->role(UserRole::HrManager)->create(['email' => 'hr@company.test']);
        $guard = Guard::factory()->create();

        $this->actingAs($ops)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type' => 'annual',
                'start_date' => now()->addWeek()->toDateString(),
                'end_date' => now()->addWeeks(2)->toDateString(),
                'reason' => 'Family travel',
            ])
            ->assertRedirect();

        Mail::assertQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($hr->email));
        Mail::assertNotQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($ops->email));
    }

    public function test_leave_requester_receives_email_when_leave_is_rejected(): void
    {
        Mail::fake();

        $requester = User::factory()->role(UserRole::ShiftManager)->create(['email' => 'shift@company.test']);
        $hr = User::factory()->role(UserRole::HrManager)->create(['email' => 'hr@company.test']);
        $guard = Guard::factory()->create();

        $leave = Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => 'annual',
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'status' => 'pending',
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($hr)
            ->post(route('leaves.reject', $leave), ['notes' => 'Insufficient cover'])
            ->assertRedirect();

        Mail::assertQueued(WorkflowActionMail::class, fn (WorkflowActionMail $mail) => $mail->hasTo($requester->email));
    }

    public function test_workflow_emails_are_skipped_when_disabled_in_settings(): void
    {
        Mail::fake();
        config(['psg.notifications.workflow_email_enabled' => false]);

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        User::factory()->managingDirector()->create(['email' => 'md@company.test']);

        $run = $this->samplePayrollRun();

        $this->actingAs($finance)
            ->post(route('payroll.submit', $run))
            ->assertRedirect();

        Mail::assertNothingSent();
    }

    private function samplePayrollRun(): PayrollRun
    {
        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-010',
            'period_year' => 2026,
            'period_month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRunStatus::Calculated,
            'currency' => 'UGX',
            'guard_count' => 1,
            'gross_total' => 3000,
            'net_total' => 2725.84,
        ]);

        PayrollPayslip::query()->create([
            'payroll_run_id' => $run->id,
            'guard_id' => Guard::factory()->create()->id,
            'employment_id' => 'G-010',
            'full_name' => 'Test Guard',
            'gross_pay' => 3000,
            'net_pay' => 2725.84,
        ]);

        return $run;
    }

    /** @return array{admin: User, director: User, ops: User, hr: User, finance: User} */
    private function leadershipUsers(): array
    {
        return [
            'admin' => User::factory()->role(UserRole::SuperAdmin)->create(['email' => 'admin@company.test']),
            'director' => User::factory()->managingDirector()->create(['email' => 'md@company.test']),
            'ops' => User::factory()->role(UserRole::OperationsManager)->create(['email' => 'ops@company.test']),
            'hr' => User::factory()->role(UserRole::HrManager)->create(['email' => 'hr@company.test']),
            'finance' => User::factory()->role(UserRole::FinanceManager)->create(['email' => 'finance@company.test']),
        ];
    }
}
