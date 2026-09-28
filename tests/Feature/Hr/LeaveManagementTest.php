<?php

namespace Tests\Feature\Hr;

use App\Enums\AttendanceEventType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\OperationalPeriodStatus;
use App\Enums\OperationalStatus;
use App\Enums\PayrollDeductionType;
use App\Enums\ReplacementReason;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\LeaveEntitlement;
use App\Models\LeaveTypeConfig;
use App\Models\OperationalPeriod;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use App\Models\Site;
use App\Models\Staff;
use App\Models\User;
use App\Services\Finance\PayrollRunService;
use App\Support\Hr\LeavePayrollAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_request_leave_and_reserve_the_yearly_balance(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = $this->type('annual');

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
                'reason' => 'Family travel',
                'contact_phone' => '0700111222',
                'expected_return_date' => '2026-10-08',
            ])
            ->assertRedirect();

        $leave = Leave::query()->firstOrFail();
        $this->assertSame(LeaveStatus::Pending, $leave->status);
        $this->assertSame(3.0, (float) $leave->days);
        $this->assertSame($guard->id, $leave->guard_id);
        $this->assertSame('0700111222', $leave->contact_phone);
        $this->assertSame(OperationalStatus::OffDuty, $guard->fresh()->operational_status);

        $balance = LeaveEntitlement::query()->where('guard_id', $guard->id)->where('year', 2026)->firstOrFail();
        $this->assertSame(21.0, (float) $balance->opening_balance);
        $this->assertSame(3.0, (float) $balance->pending);
        $this->assertSame(0.0, (float) $balance->taken);
        $this->assertSame(18.0, $balance->remaining());
        $this->assertTrue(AuditLog::query()->where('action', 'leave.requested')->where('subject_id', $leave->id)->exists());
    }

    public function test_approval_keeps_the_original_shift_and_records_leave_attendance(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = $this->guard($site);
        $cover = $this->guard($site);
        $this->deploy($guard, $site);
        $this->deploy($cover, $site);
        $type = $this->type('annual');

        $shift = Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => '2026-10-06',
            'starts_at' => '2026-10-06 06:00:00',
            'ends_at' => '2026-10-06 18:00:00',
            'status' => ShiftStatus::Scheduled,
        ]);

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
                'reason' => 'Annual leave',
                'approve_now' => true,
            ])
            ->assertRedirect();

        $leave = Leave::query()->firstOrFail();
        $shift->refresh();

        $this->assertSame(LeaveStatus::Approved, $leave->status);
        $this->assertSame(ShiftStatus::Scheduled, $shift->status);
        $this->assertSame($leave->id, $shift->leave_id);
        $this->assertSame($guard->id, $shift->guard_id);
        $this->assertSame(3, Attendance::query()->where('guard_id', $guard->id)->where('event_type', AttendanceEventType::Leave)->count());
        $this->assertTrue(AuditLog::query()->where('action', 'leave.shift_affected')->exists(), 'Shift managers were not notified about the affected shift.');

        $this->actingAs($hr)
            ->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('Arrange replacement')
            ->assertSee('Original guard on leave');

        $this->actingAs($manager)
            ->post(route('replacements.store'), [
                'original_shift_id' => $shift->id,
                'replacement_guard_id' => $cover->id,
                'reason' => ReplacementReason::Leave->value,
                'notes' => 'Cover for approved leave',
                'acknowledge_warnings' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $shift->refresh();
        $this->assertSame($guard->id, $shift->guard_id);
        $this->assertTrue(
            ShiftReplacement::query()->where('original_shift_id', $shift->id)->where('replacement_guard_id', $cover->id)->exists(),
            'The replacement was not recorded separately from the original shift.',
        );
        $this->assertNotSame(OperationalStatus::OnDuty, $guard->fresh()->operational_status);
    }

    public function test_rejection_requires_a_reason_and_releases_pending_days(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $leave = $this->pendingLeave($hr, $guard, '2026-10-05', '2026-10-07');

        $this->actingAs($hr)
            ->post(route('leaves.reject', $leave))
            ->assertSessionHasErrors('leave');

        $this->actingAs($hr)
            ->post(route('leaves.reject', $leave), ['notes' => 'No cover available'])
            ->assertRedirect();

        $this->assertSame(LeaveStatus::Rejected, $leave->fresh()->status);
        $this->assertSame('No cover available', $leave->fresh()->rejection_reason);
        $balance = LeaveEntitlement::query()->where('guard_id', $guard->id)->firstOrFail();
        $this->assertSame(0.0, (float) $balance->pending);
        $this->assertSame(21.0, $balance->remaining());
    }

    public function test_conflicts_and_locked_periods_return_clear_messages(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = $this->type('annual');
        $this->pendingLeave($hr, $guard, '2026-10-05', '2026-10-07');

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-06',
                'end_date' => '2026-10-08',
            ])
            ->assertSessionHasErrors('leave');

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-11-02',
                'end_date' => '2026-12-04',
            ])
            ->assertSessionHasErrors('leave');

        $suspended = $this->guard();
        $suspended->update(['operational_status' => OperationalStatus::Suspended]);

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $suspended->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-13',
            ])
            ->assertSessionHasErrors('leave');

        $deserted = $this->guard();
        $deserted->update(['operational_status' => OperationalStatus::Deserted]);

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $deserted->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-14',
                'end_date' => '2026-10-15',
            ])
            ->assertSessionHasErrors('leave');

        $this->assertDatabaseMissing('leaves', [
            'guard_id' => $deserted->id,
        ]);

        OperationalPeriod::query()->updateOrCreate(
            ['year' => 2026, 'month' => 12],
            [
                'starts_on' => '2026-12-01',
                'ends_on' => '2026-12-31',
                'status' => OperationalPeriodStatus::Closed,
            ],
        );

        $other = $this->guard();
        $this->actingAs($hr)
            ->from(route('leaves.create'))
            ->post(route('leaves.store'), [
                'guard_id' => $other->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-12-07',
                'end_date' => '2026-12-08',
            ])
            ->assertRedirect(route('leaves.create'))
            ->assertSessionHasErrors('leave');

        $this->assertSame(1, Leave::query()->count());
    }

    public function test_sick_leave_requires_a_supporting_document(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = $this->type('sick');

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-13',
            ])
            ->assertSessionHasErrors('leave');

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-13',
                'document' => UploadedFile::fake()->create('clinic.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $this->assertNotNull(Leave::query()->firstOrFail()->document_path);
    }

    public function test_staff_leave_uses_the_existing_staff_record(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $member = Staff::factory()->create();
        $type = $this->type('annual');
        $staffCount = Staff::query()->count();
        $guardCount = Guard::query()->count();

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'staff_id' => $member->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-06',
                'reason' => 'Study day',
            ])
            ->assertRedirect();

        $leave = Leave::query()->firstOrFail();
        $this->assertNull($leave->guard_id);
        $this->assertSame($member->id, $leave->staff_id);
        $this->assertSame($staffCount, Staff::query()->count());
        $this->assertSame($guardCount, Guard::query()->count());
        $this->assertSame(EmploymentStatus::Active, $member->fresh()->employment_status);
    }

    public function test_completing_leave_does_not_mark_the_guard_on_duty(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = $this->type('annual');

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDays(2)->toDateString(),
                'approve_now' => true,
            ])
            ->assertRedirect();

        $leave = Leave::query()->firstOrFail();
        $this->assertSame(OperationalStatus::OnLeave, $guard->fresh()->operational_status);

        $this->actingAs($hr)
            ->post(route('leaves.complete', $leave))
            ->assertRedirect();

        $this->assertSame(LeaveStatus::Completed, $leave->fresh()->status);
        $this->assertSame(OperationalStatus::OffDuty, $guard->fresh()->operational_status);
        $this->assertNotSame(OperationalStatus::OnDuty, $guard->fresh()->operational_status);
    }

    public function test_ended_leave_is_completed_on_sync_and_the_record_is_kept(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $guard->update(['operational_status' => OperationalStatus::OnLeave]);

        $leave = Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => 'annual',
            'start_date' => now()->subDays(4)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'expected_return_date' => now()->toDateString(),
            'status' => LeaveStatus::Approved,
            'days' => 2,
        ]);

        $this->actingAs($hr)
            ->get(route('leaves.index'))
            ->assertOk();

        $this->assertSame(LeaveStatus::Completed, $leave->fresh()->status);
        $this->assertNotNull(Leave::query()->find($leave->id));
        $this->assertSame(OperationalStatus::OffDuty, $guard->fresh()->operational_status);
        $this->assertNotSame(OperationalStatus::OnDuty, $guard->fresh()->operational_status);
    }

    public function test_cancellation_keeps_history_and_a_second_approval_is_refused(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $leave = $this->pendingLeave($hr, $guard, '2026-10-05', '2026-10-07');

        $this->actingAs($hr)
            ->post(route('leaves.approve', $leave))
            ->assertRedirect();

        $this->actingAs($hr)
            ->post(route('leaves.approve', $leave))
            ->assertSessionHasErrors('leave');

        $this->assertSame(LeaveStatus::Approved, $leave->fresh()->status);

        $this->actingAs($hr)
            ->post(route('leaves.cancel', $leave), ['notes' => 'Plans changed'])
            ->assertRedirect();

        $this->assertSame(LeaveStatus::Cancelled, $leave->fresh()->status);
        $this->assertNotNull(Leave::query()->find($leave->id));
        $balance = LeaveEntitlement::query()->where('guard_id', $guard->id)->firstOrFail();
        $this->assertSame(0.0, (float) $balance->taken);
        $this->assertTrue(AuditLog::query()->where('action', 'leave.cancelled')->exists());
    }

    public function test_authorization_blocks_finance_from_creating_and_shift_managers_from_approving(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = $this->type('annual');

        $this->actingAs($finance)
            ->from(route('leaves.create'))
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-06',
            ])
            ->assertRedirect(route('leaves.create'))
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->assertSame(0, Leave::query()->count());

        $leave = $this->pendingLeave($hr, $guard, '2026-10-05', '2026-10-06');

        $this->actingAs($manager)
            ->from(route('leaves.show', $leave))
            ->post(route('leaves.approve', $leave))
            ->assertRedirect(route('leaves.show', $leave))
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->assertSame(LeaveStatus::Pending, $leave->fresh()->status);
    }

    public function test_dashboard_filters_and_csv_use_live_leave_records(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $site = Site::factory()->create();
        $guard = $this->guard($site);
        $type = $this->type('annual');

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
                'expected_return_date' => now()->toDateString(),
                'approve_now' => true,
            ])
            ->assertRedirect();

        $this->actingAs($hr)
            ->get(route('leaves.index', ['site_id' => $site->id, 'employee_type' => 'guard']))
            ->assertOk()
            ->assertSee($guard->full_name)
            ->assertSee('On leave')
            ->assertSee('Guards on leave')
            ->assertViewHas('stats', function (array $stats): bool {
                return $stats['on_leave'] === 1
                    && $stats['guards_on_leave'] === 1
                    && $stats['returning_today'] === 1;
            });

        $export = $this->actingAs($hr)->get(route('leaves.export'));
        $export->assertOk();
        $this->assertStringContainsString($guard->employment_id, $export->streamedContent());
        $this->assertStringContainsString('Annual leave', $export->streamedContent());
    }

    public function test_hr_can_add_a_company_leave_type(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->from(route('leave-types.index'))
            ->post(route('leave-types.store'), [
                'code' => 'family',
                'name' => 'Family leave',
                'pay_percent' => 50,
                'eligibility' => 'all',
            ])
            ->assertRedirect(route('leave-types.index'))
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->actingAs($hr)
            ->post(route('leave-types.store'), [
                'code' => 'family',
                'name' => 'Family leave',
                'pay_percent' => 50,
                'max_days_per_year' => 4,
                'eligibility' => 'all',
                'requires_approval' => 1,
            ])
            ->assertRedirect();

        $this->assertTrue(LeaveTypeConfig::query()->where('code', 'family')->where('pay_percent', 50)->exists());
    }

    public function test_payroll_deducts_unpaid_salary_leave_and_leaves_shift_pay_unchanged(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = PayrollRunService::lastClosedPeriod();
        $start = $period->copy()->startOfMonth();
        $unpaid = $this->type('unpaid');
        $annual = $this->type('annual');

        $member = Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 930000,
            'date_employed' => $start->toDateString(),
        ]);

        $staffLeave = Leave::query()->create([
            'staff_id' => $member->id,
            'leave_type' => 'unpaid',
            'leave_type_id' => $unpaid->id,
            'start_date' => $start->copy()->addDays(2)->toDateString(),
            'end_date' => $start->copy()->addDays(4)->toDateString(),
            'days' => 3,
            'status' => LeaveStatus::Approved,
        ]);

        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 900000,
            'date_employed' => $start->toDateString(),
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $start->toDateString(),
            'status' => ShiftStatus::Recorded,
        ]);

        Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => 'unpaid',
            'leave_type_id' => $unpaid->id,
            'start_date' => $start->copy()->addDays(2)->toDateString(),
            'end_date' => $start->copy()->addDays(4)->toDateString(),
            'days' => 3,
            'status' => LeaveStatus::Approved,
        ]);

        $paidProbe = new Leave([
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(2)->toDateString(),
        ]);
        $paidProbe->setRelation('leaveTypeConfig', $annual);
        $this->assertSame(0.0, LeavePayrollAdjustment::unpaidAmount(
            $paidProbe,
            $start->toDateString(),
            $start->copy()->endOfMonth()->toDateString(),
            930000,
        ));

        $expected = LeavePayrollAdjustment::unpaidAmount(
            $staffLeave->fresh('leaveTypeConfig'),
            $start->toDateString(),
            $start->copy()->endOfMonth()->toDateString(),
            930000,
        );
        $this->assertGreaterThan(0, $expected);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $staffPayslip = PayrollPayslip::query()->where('staff_id', $member->id)->firstOrFail();
        $deduction = $staffPayslip->deductions()->where('type', PayrollDeductionType::UnpaidLeave)->first();
        $this->assertNotNull($deduction);
        $this->assertSame($expected, (float) $deduction->amount);
        $this->assertStringContainsString((string) $staffLeave->id, $deduction->label);

        $guardPayslip = PayrollPayslip::query()->where('guard_id', $guard->id)->firstOrFail();
        $this->assertFalse($guardPayslip->deductions()->where('type', PayrollDeductionType::UnpaidLeave)->exists());
    }

    private function guard(?Site $site = null): Guard
    {
        $site ??= Site::factory()->create();

        return Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
        ]);
    }

    private function deploy(Guard $guard, Site $site): void
    {
        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);
    }

    private function type(string $code): LeaveTypeConfig
    {
        return LeaveTypeConfig::query()->where('code', $code)->firstOrFail();
    }

    private function pendingLeave(User $hr, Guard $guard, string $start, string $end): Leave
    {
        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $this->type('annual')->id,
                'start_date' => $start,
                'end_date' => $end,
                'reason' => 'Rest',
            ])
            ->assertRedirect();

        return Leave::query()->where('guard_id', $guard->id)->latest('id')->firstOrFail();
    }
}
