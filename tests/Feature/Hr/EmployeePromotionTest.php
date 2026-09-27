<?php

namespace Tests\Feature\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\PayrollPayslip;
use App\Models\Position;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\Finance\PayrollRunService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeePromotionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_promoting_a_guard_keeps_the_same_employee_and_moves_the_roster(): void
    {
        Storage::fake('local');
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();
        $guard = $this->guard(500000, $region);
        $before = Guard::query()->count();
        $position = Position::query()->where('code', 'supervisor')->firstOrFail();

        $this->actingAs($hr)
            ->post(route('guards.promotions.store', $guard), [
                'position_id' => $position->id,
                'new_salary' => 750000,
                'effective_from' => now()->startOfMonth()->toDateString(),
                'reason' => 'Promoted to supervisor',
                'reference' => 'HR-2026-10',
                'region_id' => $region->id,
                'remarks' => 'Appointment letter filed',
                'document' => UploadedFile::fake()->create('appointment.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $guard->refresh();
        $staff = Staff::query()->where('employment_id', $guard->employment_id)->firstOrFail();
        $supervisor = Supervisor::query()->where('guard_id', $guard->id)->firstOrFail();

        $this->assertSame($before, Guard::query()->count());
        $this->assertSame($guard->id, $staff->guard_id);
        $this->assertSame($guard->employment_id, $staff->employment_id);
        $this->assertSame($guard->employment_id, $supervisor->employmentId());
        $this->assertSame(750000.0, (float) $staff->monthly_salary);
        $this->assertSame(500000.0, (float) $guard->salaryRevisions()->orderBy('effective_from')->first()->salary);
        $this->assertSame(750000.0, (float) $staff->salaryRevisions()->orderBy('effective_from')->first()->salary);
        $this->assertNotNull($guard->promotions()->first()->document_path);
        $this->assertSame($hr->id, $guard->promotions()->first()->approved_by);
        $this->assertTrue(AuditLog::query()->where('action', 'employee.promoted')->where('subject_id', $guard->id)->exists());

        $this->actingAs($hr)->get(route('guards.index'))->assertOk()->assertDontSee($guard->employment_id);
        $this->actingAs($hr)->get(route('staff.index'))->assertOk()->assertSee($guard->full_name);
        $this->actingAs($hr)->get(route('supervisors.index'))->assertOk()->assertSee($guard->full_name);
        $this->actingAs($hr)->get(route('staff.show', $staff))
            ->assertOk()
            ->assertSee('Supervisor')
            ->assertSee('UGX 750,000');
    }

    public function test_a_future_promotion_leaves_the_guard_on_the_roster_until_the_effective_date(): void
    {
        Carbon::setTestNow('2026-09-27 10:00:00');
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();
        $guard = $this->guard(500000, $region);
        $position = Position::query()->where('code', 'supervisor')->firstOrFail();

        $this->actingAs($hr)->post(route('guards.promotions.store', $guard), [
            'position_id' => $position->id,
            'new_salary' => 750000,
            'effective_from' => '2026-10-01',
            'reason' => 'Promoted to supervisor',
            'region_id' => $region->id,
        ])->assertRedirect(route('guards.show', $guard));

        $this->assertSame('scheduled', $guard->promotions()->first()->status);
        $this->assertNull(Staff::query()->where('employment_id', $guard->employment_id)->first());
        $this->actingAs($hr)->get(route('guards.index'))->assertOk()->assertSee($guard->employment_id);

        Carbon::setTestNow('2026-10-01 08:00:00');
        $this->flushSession();
        $this->actingAs($hr)->get(route('guards.index'))->assertOk()->assertDontSee($guard->employment_id);
        $this->actingAs($hr)->get(route('staff.index'))->assertOk()->assertSee($guard->full_name);
        $this->assertSame(750000.0, (float) Staff::query()->where('employment_id', $guard->employment_id)->firstOrFail()->monthly_salary);
        $this->assertSame(500000.0, (float) $guard->fresh()->base_shift_rate);
    }

    public function test_payroll_uses_guard_pay_before_the_promotion_and_fixed_salary_after(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $before = $this->closedMonth(1);
        $after = $this->closedMonth();
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);
        $guard = $this->guard(500000, $region, $site);
        $position = Position::query()->where('code', 'supervisor')->firstOrFail();

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $region->id,
            'shift_date' => $before->copy()->day(4)->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $beforeRun = $this->calculateMonth($finance, $before);
        $beforeSlip = PayrollPayslip::query()->where('payroll_run_id', $beforeRun->id)->where('guard_id', $guard->id)->firstOrFail();
        $gross = (float) $beforeSlip->gross_pay;
        $this->assertSame($this->perShift(500000), $gross);

        $payroll = app(PayrollRunService::class);
        $payroll->submit($beforeRun, $finance);
        $payroll->approve($beforeRun, $finance);

        $this->actingAs($hr)->post(route('guards.promotions.store', $guard), [
            'position_id' => $position->id,
            'new_salary' => 750000,
            'effective_from' => $after->copy()->startOfMonth()->toDateString(),
            'reason' => 'Promoted to supervisor',
            'region_id' => $region->id,
        ])->assertRedirect();

        $afterRun = $this->calculateMonth($finance, $after);
        $staffSlip = PayrollPayslip::query()->where('payroll_run_id', $afterRun->id)->whereNotNull('staff_id')->firstOrFail();
        $this->assertSame(750000.0, (float) $staffSlip->gross_pay);
        $this->assertSame(0, $staffSlip->overtime_shifts);
        $this->assertNull(PayrollPayslip::query()->where('payroll_run_id', $afterRun->id)->where('guard_id', $guard->id)->first());

        $beforeSlip->refresh();
        $this->assertSame($gross, (float) $beforeSlip->gross_pay);
        $this->assertSame(PayrollRunStatus::Approved, $beforeRun->fresh()->status);
    }

    public function test_mid_month_promotion_splits_guard_shifts_and_fixed_salary(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $month = $this->closedMonth();
        $days = $month->daysInMonth;
        $region = Region::factory()->create();
        $site = Site::factory()->create(['region_id' => $region->id]);
        $guard = $this->guard(500000, $region, $site);
        $position = Position::query()->where('code', 'finance_officer')->firstOrFail();

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $region->id,
            'shift_date' => $month->copy()->day(3)->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);
        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $region->id,
            'shift_date' => $month->copy()->day(20)->toDateString(),
            'shift_type' => ShiftType::Overtime,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($hr)->post(route('guards.promotions.store', $guard), [
            'position_id' => $position->id,
            'new_salary' => 900000,
            'effective_from' => $month->copy()->day(15)->toDateString(),
            'reason' => 'Moved to finance',
        ])->assertRedirect();

        $run = $this->calculateMonth($finance, $month);
        $guardSlip = PayrollPayslip::query()->where('payroll_run_id', $run->id)->where('guard_id', $guard->id)->firstOrFail();
        $staffSlip = PayrollPayslip::query()->where('payroll_run_id', $run->id)->whereNotNull('staff_id')->firstOrFail();

        $this->assertSame(1, $guardSlip->normal_shifts);
        $this->assertSame(0, $guardSlip->overtime_shifts);
        $this->assertSame($this->perShift(500000), (float) $guardSlip->gross_pay);
        $this->assertSame(round(900000 * (($days - 14) / $days), 2), (float) $staffSlip->gross_pay);
        $this->assertSame(0, $staffSlip->overtime_shifts);
        $this->assertNull(Supervisor::query()->where('guard_id', $guard->id)->first());
    }

    public function test_a_custom_position_controls_the_roster_without_a_code_change(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create();
        $guard = $this->guard(400000, $region);

        $this->actingAs($hr)->post(route('positions.store'), [
            'name' => 'Control Room Lead',
            'code' => 'control_room_lead',
            'salary_type' => 'fixed',
            'is_staff_position' => '1',
            'is_management_position' => '1',
        ])->assertRedirect(route('positions.index'));

        $position = Position::query()->where('code', 'control_room_lead')->firstOrFail();
        $this->assertTrue($position->is_staff_position);
        $this->assertTrue($position->is_management_position);
        $this->assertFalse($position->is_guard_position);

        $this->actingAs($hr)->post(route('guards.promotions.store', $guard), [
            'position_id' => $position->id,
            'new_salary' => 1100000,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'reason' => 'Appointed to the control room',
        ])->assertRedirect();

        $this->actingAs($hr)->get(route('guards.index'))->assertOk()->assertDontSee($guard->employment_id);
        $this->actingAs($hr)->get(route('staff.index'))->assertOk()->assertSee($guard->full_name);
    }

    public function test_supervisor_region_transfer_closes_the_previous_assignment(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $central = Region::factory()->create(['name' => 'Central Region']);
        $eastern = Region::factory()->create(['name' => 'Eastern Region']);
        $guard = $this->guard(500000, $central);
        $position = Position::query()->where('code', 'supervisor')->firstOrFail();

        $this->actingAs($hr)->post(route('guards.promotions.store', $guard), [
            'position_id' => $position->id,
            'new_salary' => 750000,
            'effective_from' => '2026-01-01',
            'reason' => 'Promoted to supervisor',
            'region_id' => $central->id,
        ])->assertRedirect();

        $supervisor = Supervisor::query()->where('guard_id', $guard->id)->firstOrFail();

        $this->actingAs($hr)->post(route('supervisors.region-transfers.store', $supervisor), [
            'region_id' => $eastern->id,
            'starts_on' => '2026-10-01',
            'remarks' => 'Transferred east',
        ])->assertRedirect(route('supervisors.show', $supervisor));

        $history = $supervisor->assignmentHistories()->reorder()->orderBy('starts_on')->get();
        $this->assertCount(2, $history);
        $this->assertSame('2026-09-30', $history[0]->ends_on->toDateString());
        $this->assertSame($central->id, $history[0]->new_region_id);
        $this->assertSame('2026-10-01', $history[1]->starts_on->toDateString());
        $this->assertNull($history[1]->ends_on);
        $this->assertSame($eastern->id, $supervisor->fresh()->region_id);
    }

    public function test_unauthorized_users_cannot_promote(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $guard = $this->guard(500000, Region::factory()->create());
        $position = Position::query()->where('code', 'supervisor')->firstOrFail();

        $this->actingAs($manager)->post(route('guards.promotions.store', $guard), [
            'position_id' => $position->id,
            'new_salary' => 750000,
            'effective_from' => now()->toDateString(),
            'reason' => 'Not allowed',
            'region_id' => $guard->region_id,
        ])->assertForbidden();

        $this->assertSame(0, $guard->promotions()->count());
        $this->assertSame(1, Guard::query()->count());
    }

    private function guard(float $salary, Region $region, ?Site $site = null): Guard
    {
        return Guard::factory()->create([
            'region_id' => $region->id,
            'current_site_id' => $site?->id,
            'base_shift_rate' => $salary,
            'overtime_shift_rate' => 0,
            'date_employed' => '2024-01-01',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'rank_designation' => 'Guard',
        ]);
    }

    private function closedMonth(int $monthsBack = 0): Carbon
    {
        return PayrollRunService::lastClosedPeriod()->copy()->startOfMonth()->subMonths($monthsBack);
    }

    private function calculateMonth(User $actor, Carbon $month): \App\Models\PayrollRun
    {
        $payroll = app(PayrollRunService::class);
        $run = $payroll->createDraft([
            'period_year' => $month->year,
            'period_month' => $month->month,
        ], $actor);

        return $payroll->calculate($run);
    }

    private function perShift(float $monthly): float
    {
        return round($monthly / max(1, (int) config('psg.payroll.standard_shifts_per_month', 30)), 2);
    }
}
