<?php

namespace Tests\Feature\Finance;

use App\Enums\CompensationType;
use App\Enums\EmploymentStatus;
use App\Enums\PayrollDeductionType;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\StaffSalaryChangeType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\GuardSalaryAdvance;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Services\Finance\PayrollCalculationService;
use App\Services\Finance\PayrollRunService;
use App\Services\StaffSalaryService;
use App\Support\Finance\PayrollRates;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class StaffSalaryHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_employee_salary_is_recorded_without_replacing_later_history(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();

        $this->actingAs($hr)
            ->post(route('staff.store'), [
                'employee_type' => 'staff',
                'employment_id' => 'PSG015',
                'first_name' => 'Amina',
                'last_name' => 'Nakato',
                'job_title' => 'Security Officer',
                'job_grade' => 'G2',
                'date_employed' => '2025-01-01',
                'employment_status' => EmploymentStatus::Active->value,
                'monthly_salary' => 800000,
            ])
            ->assertRedirect();

        $staff = Staff::query()->where('employment_id', 'PSG015')->firstOrFail();
        $opening = $staff->salaryRevisions()->first();

        $this->assertSame(1, $staff->salaryRevisions()->count());
        $this->assertSame(StaffSalaryChangeType::Initial, $opening->change_type);
        $this->assertSame(800000.0, (float) $opening->salary);
        $this->assertSame('Security Officer', $opening->job_title);
        $this->assertSame('G2', $opening->grade);
        $this->assertSame('2025-01-01', $opening->effective_from->toDateString());
        $this->assertNull($opening->effective_to);
        $this->assertSame(800000.0, (float) $staff->monthly_salary);
    }

    public function test_salary_increment_keeps_the_previous_salary(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $staff = $this->staffMember(900000, 'Admin Assistant');

        app(StaffSalaryService::class)->recordOpening($staff, 900000, Carbon::parse('2025-01-01'), $hr, 'Admin Assistant', 'G1');
        app(StaffSalaryService::class)->change(
            $staff,
            980000,
            $this->closedMonth()->copy()->startOfMonth(),
            StaffSalaryChangeType::Increment,
            'Annual salary review',
            $hr,
        );

        $staff->refresh();
        $rows = $staff->salaryRevisions()->orderBy('effective_from')->get();

        $this->assertSame(900000.0, (float) $rows[0]->salary);
        $this->assertSame(980000.0, (float) $rows[1]->salary);
        $this->assertSame(900000.0, (float) $rows[1]->previous_salary);
        $this->assertSame(StaffSalaryChangeType::Increment, $rows[1]->change_type);
        $this->assertSame('Admin Assistant', $rows[1]->job_title);
        $this->assertSame($hr->id, $rows[1]->approved_by);
        $this->assertSame($hr->id, $rows[1]->created_by);
        $this->assertNotNull($rows[1]->approved_at);
        $this->assertSame(980000.0, (float) $staff->monthly_salary);
    }

    public function test_salary_reduction_keeps_the_previous_salary(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $staff = $this->staffMember(1200000, 'Operations Supervisor', 'G4');

        $effective = $this->closedMonth()->copy()->startOfMonth();

        app(StaffSalaryService::class)->recordOpening($staff, 1200000, Carbon::parse('2025-01-01'), $hr, 'Operations Supervisor', 'G4');
        app(StaffSalaryService::class)->change(
            $staff,
            1000000,
            $effective,
            StaffSalaryChangeType::Demotion,
            'Approved management decision',
            $hr,
            'Operations Supervisor',
            'G3',
        );

        $staff->refresh();
        $previous = $staff->salaryRevisions()->orderBy('effective_from')->first();
        $current = $staff->salaryRevisions()->reorder()->orderByDesc('effective_from')->first();

        $this->assertSame(1200000.0, (float) $previous->salary);
        $this->assertSame($effective->copy()->subDay()->toDateString(), $previous->effective_to->toDateString());
        $this->assertSame(1000000.0, (float) $current->salary);
        $this->assertSame(1200000.0, (float) $current->previous_salary);
        $this->assertSame(StaffSalaryChangeType::Demotion, $current->change_type);
        $this->assertSame('Approved management decision', $current->reason);
        $this->assertSame('G3', $current->grade);
        $this->assertSame('G4', $current->previous_grade);
        $this->assertSame(1000000.0, (float) $staff->monthly_salary);
    }

    public function test_promotion_with_salary_change_keeps_the_earlier_position_and_pay(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $staff = $this->staffMember(800000, 'Security Officer', 'G2');
        $staff->update(['employment_id' => 'PSG015']);

        $effective = $this->closedMonth()->copy()->startOfMonth();
        $salaries = app(StaffSalaryService::class);
        $salaries->recordOpening($staff, 800000, Carbon::parse('2025-01-01'), $hr, 'Security Officer', 'G2');
        $salaries->change(
            $staff,
            1200000,
            $effective,
            StaffSalaryChangeType::Promotion,
            'Promotion approved by HR Manager',
            $hr,
            'Operations Supervisor',
            'G4',
        );

        $staff->refresh();

        $this->assertSame('Operations Supervisor', $staff->job_title);
        $this->assertSame('G4', $staff->job_grade);
        $this->assertSame(1200000.0, (float) $staff->monthly_salary);
        $this->assertSame(800000.0, (float) $staff->salaryRevisions()->orderBy('effective_from')->first()->salary);
        $this->assertSame('Security Officer', $staff->salaryRevisions()->orderBy('effective_from')->first()->job_title);
        $this->assertSame($effective->copy()->subDay()->toDateString(), $staff->salaryRevisions()->orderBy('effective_from')->first()->effective_to->toDateString());

        $log = AuditLog::query()->where('action', 'staff.salary_changed')->where('subject_id', $staff->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame(Staff::class, $log->subject_type);
        $this->assertSame(800000.0, (float) $log->context['previous_salary']);
        $this->assertSame(1200000.0, (float) $log->context['salary']);
        $this->assertSame('Security Officer', $log->context['previous_job_title']);
        $this->assertSame('Operations Supervisor', $log->context['job_title']);
        $this->assertSame('promotion', $log->context['change_type']);
        $this->assertSame($hr->id, $log->context['approved_by']);
    }

    public function test_promotion_without_salary_change_updates_position_only(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $staff = $this->staffMember(800000, 'Security Officer', 'G2');
        $salaries = app(StaffSalaryService::class);
        $salaries->recordOpening($staff, 800000, Carbon::parse('2025-01-01'), $hr, 'Security Officer', 'G2');

        $salaries->change(
            $staff,
            800000,
            $this->closedMonth(1)->copy()->startOfMonth(),
            StaffSalaryChangeType::Promotion,
            'Promotion with no salary change',
            $hr,
            'Operations Supervisor',
            'G2',
        );

        $staff->refresh();

        $this->assertSame(800000.0, (float) $staff->monthly_salary);
        $this->assertSame('Operations Supervisor', $staff->job_title);
        $this->assertSame(2, $staff->salaryRevisions()->count());
        $this->assertSame(800000.0, (float) $staff->salaryRevisions()->orderBy('effective_from')->first()->salary);
        $this->assertSame('Security Officer', $staff->salaryRevisions()->orderBy('effective_from')->first()->job_title);

        try {
            $salaries->change(
                $staff->fresh(),
                800000,
                $this->closedMonth()->copy()->startOfMonth(),
                StaffSalaryChangeType::Other,
                'No actual change',
                $hr,
                'Operations Supervisor',
                'G2',
            );
            $this->fail('A salary change with no difference was recorded.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('Change the salary', $exception->getMessage());
        }

        $this->assertSame(2, $staff->salaryRevisions()->count());
    }

    public function test_multiple_salary_changes_remain_available(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $staff = $this->staffMember(700000, 'Operations Clerk');
        $salaries = app(StaffSalaryService::class);
        $salaries->recordOpening($staff, 700000, Carbon::parse('2025-01-01'), $hr, 'Operations Clerk');
        $salaries->change($staff, 740000, $this->closedMonth(1)->copy()->startOfMonth(), StaffSalaryChangeType::Increment, 'Salary increment', $hr);
        $salaries->change($staff, 800000, $this->closedMonth()->copy()->startOfMonth(), StaffSalaryChangeType::Other, 'Management-approved salary adjustment', $hr);

        $amounts = $staff->salaryRevisions()->orderBy('effective_from')->pluck('salary')->map(fn ($amount) => (float) $amount)->all();

        $this->assertSame([700000.0, 740000.0, 800000.0], $amounts);
        $this->assertSame(800000.0, (float) $staff->fresh()->monthly_salary);
        $this->assertSame(2, $staff->salaryRevisions()->whereNotNull('effective_to')->count());
    }

    public function test_payroll_uses_the_salary_in_force_for_each_period(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $before = $this->closedMonth(1);
        $after = $this->closedMonth();
        $staff = $this->staffMember(800000, 'Security Officer');

        $salaries = app(StaffSalaryService::class);
        $salaries->recordOpening($staff, 800000, Carbon::parse('2024-01-01'), $hr, 'Security Officer');
        $salaries->change(
            $staff,
            1200000,
            $after->copy()->startOfMonth(),
            StaffSalaryChangeType::Promotion,
            'Promotion approved by HR Manager',
            $hr,
            'Operations Supervisor',
        );

        $beforeRun = $this->calculateMonth($finance, $before);
        $afterRun = $this->calculateMonth($finance, $after);
        $beforeSlip = $this->payslipFor($beforeRun, $staff);
        $afterSlip = $this->payslipFor($afterRun, $staff);

        $this->assertSame(800000.0, (float) $beforeSlip->gross_pay);
        $this->assertSame(800000.0, (float) $beforeSlip->base_shift_rate);
        $this->assertNull($beforeSlip->salary_breakdown);
        $this->assertSame(1200000.0, (float) $afterSlip->gross_pay);
        $this->assertSame(1200000.0, (float) $afterSlip->base_shift_rate);
        $this->assertSame(1200000.0, (float) $staff->fresh()->monthly_salary);
        $this->assertSame(800000.0, (float) $staff->salaryRevisions()->orderBy('effective_from')->first()->salary);
    }

    public function test_mid_month_salary_change_is_prorated_and_shown_on_the_payslip(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $month = $this->closedMonth();
        $days = $month->daysInMonth;
        $staff = $this->staffMember(900000, 'Finance Officer');

        GuardSalaryAdvance::query()->create([
            'staff_id' => $staff->id,
            'label' => 'Emergency advance',
            'original_amount' => 50000,
            'balance_remaining' => 50000,
            'monthly_installment' => 10000,
            'is_active' => true,
        ]);

        $salaries = app(StaffSalaryService::class);
        $salaries->recordOpening($staff, 900000, Carbon::parse('2024-01-01'), $hr, 'Finance Officer');
        $salaries->change(
            $staff,
            1200000,
            $month->copy()->day(15),
            StaffSalaryChangeType::Increment,
            'Salary increment',
            $hr,
        );

        $run = $this->calculateMonth($finance, $month);
        $payslip = $this->payslipFor($run, $staff);
        $expected = round(900000 * (14 / $days), 2) + round(1200000 * (($days - 14) / $days), 2);

        $this->assertSame($expected, (float) $payslip->gross_pay);
        $this->assertTrue($payslip->hasMixedSalary());
        $this->assertSame('900000.00', number_format((float) $payslip->salary_breakdown[0]['monthly_gross'], 2, '.', ''));
        $this->assertSame(14, $payslip->salary_breakdown[0]['days']);
        $this->assertSame(1200000.0, (float) $payslip->salary_breakdown[1]['monthly_gross']);
        $payslip->load('deductions');
        $this->assertTrue($payslip->deductions->contains('type', PayrollDeductionType::Nssf));
        $this->assertTrue($payslip->deductions->contains('type', PayrollDeductionType::Paye));
        $this->assertSame(10000.0, (float) $payslip->deductions->firstWhere('type', PayrollDeductionType::Advance)->amount);
        $this->assertEqualsWithDelta(
            (float) $payslip->gross_pay - (float) $payslip->total_deductions,
            (float) $payslip->net_pay,
            0.01,
        );

        $this->actingAs($finance)
            ->get(route('payroll.payslips.show', [$run, $payslip]))
            ->assertOk()
            ->assertSee('UGX 900,000')
            ->assertSee('UGX 1,200,000');
    }

    public function test_supervisor_overtime_uses_the_salary_in_force_on_the_cover_date(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $month = $this->closedMonth();
        $days = $month->daysInMonth;
        $site = Site::factory()->create();
        $staff = $this->staffMember(900000, 'Supervisor');
        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'compensation_type' => CompensationType::Salary,
            'base_shift_rate' => 900000,
            'overtime_shift_rate' => 0,
            'date_employed' => '2024-01-01',
        ]);

        Supervisor::factory()->create([
            'region_id' => $site->region_id,
            'staff_id' => $staff->id,
            'guard_id' => $guard->id,
            'name' => $staff->full_name,
        ]);

        $salaries = app(StaffSalaryService::class);
        $salaries->recordOpening($staff, 900000, Carbon::parse('2024-01-01'), $hr, 'Supervisor');
        $salaries->change($staff, 1200000, $month->copy()->day(15), StaffSalaryChangeType::Increment, 'Salary increment', $hr);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $month->copy()->day(10)->toDateString(),
            'shift_type' => ShiftType::Overtime,
            'status' => ShiftStatus::Recorded,
        ]);
        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $month->copy()->day(20)->toDateString(),
            'shift_type' => ShiftType::Overtime,
            'status' => ShiftStatus::Recorded,
        ]);

        $run = $this->calculateMonth($finance, $month);
        $payslip = $this->payslipFor($run, $staff);
        $salary = round(900000 * (14 / $days), 2) + round(1200000 * (($days - 14) / $days), 2);
        $oldRate = PayrollRates::salaryOvertimeShiftRate(900000, $guard, $run);
        $newRate = PayrollRates::salaryOvertimeShiftRate(1200000, $guard, $run);

        $this->assertSame(2, $payslip->overtime_shifts);
        $this->assertSame(round($salary + $oldRate + $newRate, 2), (float) $payslip->gross_pay);
        $this->assertSame(1, $payslip->salary_breakdown[0]['overtime_shifts']);
        $this->assertSame(1, $payslip->salary_breakdown[1]['overtime_shifts']);
    }

    public function test_finalized_payroll_is_unchanged_by_a_later_salary_change(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $before = $this->closedMonth(1);
        $after = $this->closedMonth();
        $staff = $this->staffMember(800000, 'Security Officer');

        app(StaffSalaryService::class)->recordOpening($staff, 800000, Carbon::parse('2024-01-01'), $hr, 'Security Officer');

        $run = $this->calculateMonth($finance, $before);
        $payslip = $this->payslipFor($run, $staff);
        $gross = (float) $payslip->gross_pay;
        $net = (float) $payslip->net_pay;
        $this->assertSame(800000.0, $gross);

        $payroll = app(PayrollRunService::class);
        $payroll->submit($run, $finance);
        $payroll->approve($run, $finance);

        app(StaffSalaryService::class)->change(
            $staff,
            1200000,
            $after->copy()->startOfMonth(),
            StaffSalaryChangeType::Promotion,
            'Later promotion',
            $hr,
            'Operations Supervisor',
        );

        try {
            app(PayrollCalculationService::class)->calculate($run->fresh());
            $this->fail('Approved payroll was recalculated.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('cannot be calculated', $exception->getMessage());
        }

        $payslip->refresh();
        $this->assertSame($gross, (float) $payslip->gross_pay);
        $this->assertSame($net, (float) $payslip->net_pay);
        $this->assertSame(800000.0, (float) $payslip->base_shift_rate);
        $this->assertSame(1200000.0, (float) $staff->fresh()->monthly_salary);
    }

    public function test_unauthorized_users_cannot_change_salary_and_history_stays_visible(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();
        $staff = $this->staffMember(800000, 'Security Officer', 'G2');
        $staff->update(['employment_id' => 'PSG015']);

        app(StaffSalaryService::class)->recordOpening($staff, 800000, Carbon::parse('2025-01-01'), $hr, 'Security Officer', 'G2');

        $payload = [
            'salary' => 1200000,
            'effective_from' => $this->closedMonth()->copy()->startOfMonth()->toDateString(),
            'change_type' => StaffSalaryChangeType::Promotion->value,
            'reason' => 'Promotion approved by HR Manager',
            'job_title' => 'Operations Supervisor',
            'grade' => 'G4',
        ];

        $this->actingAs($shiftManager)
            ->post(route('staff.salary-revisions.store', $staff), $payload)
            ->assertRedirect()
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->actingAs($finance)
            ->post(route('staff.salary-revisions.store', $staff), $payload)
            ->assertRedirect()
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->actingAs($finance)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertSee('Current employment')
            ->assertSee('UGX 800,000')
            ->assertSee('Security Officer')
            ->assertSee('Initial salary')
            ->assertDontSee('Record salary change');

        $this->actingAs($hr)
            ->post(route('staff.salary-revisions.store', $staff), $payload)
            ->assertRedirect(route('staff.show', $staff));

        $staff->refresh();
        $this->assertSame(1200000.0, (float) $staff->monthly_salary);
        $this->assertSame('Operations Supervisor', $staff->job_title);
        $this->assertSame(800000.0, (float) $staff->salaryRevisions()->orderBy('effective_from')->first()->salary);

        $this->actingAs($hr)
            ->put(route('staff.update', $staff), [
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'employment_status' => EmploymentStatus::Active->value,
                'job_title' => 'Replaced title',
                'job_grade' => 'G9',
                'monthly_salary' => 999999,
                'department' => 'Operations',
            ])
            ->assertRedirect();

        $staff->refresh();
        $this->assertSame(1200000.0, (float) $staff->monthly_salary);
        $this->assertSame('Operations Supervisor', $staff->job_title);
        $this->assertSame('G4', $staff->job_grade);

        $this->actingAs($hr)
            ->get(route('staff.show', $staff))
            ->assertOk()
            ->assertSee('Record salary change')
            ->assertSee('UGX 1,200,000')
            ->assertSee('UGX 800,000')
            ->assertSee('Promotion')
            ->assertSee('Historical')
            ->assertSee($hr->name);
    }

    private function staffMember(float $salary, string $title, ?string $grade = null): Staff
    {
        return Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => $salary,
            'job_title' => $title,
            'job_grade' => $grade,
            'date_employed' => '2024-01-01',
            'employment_end_date' => null,
        ]);
    }

    private function closedMonth(int $monthsBack = 0): Carbon
    {
        return PayrollRunService::lastClosedPeriod()->copy()->startOfMonth()->subMonths($monthsBack);
    }

    private function calculateMonth(User $actor, Carbon $month): PayrollRun
    {
        $payroll = app(PayrollRunService::class);
        $run = $payroll->createDraft([
            'period_year' => $month->year,
            'period_month' => $month->month,
        ], $actor);

        return $payroll->calculate($run);
    }

    private function payslipFor(PayrollRun $run, Staff $staff): PayrollPayslip
    {
        return PayrollPayslip::query()
            ->where('payroll_run_id', $run->id)
            ->where('staff_id', $staff->id)
            ->firstOrFail();
    }
}
