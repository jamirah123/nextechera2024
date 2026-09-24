<?php

namespace Tests\Feature\Finance;

use App\Enums\CompensationType;
use App\Enums\EmploymentStatus;
use App\Enums\PayrollDeductionType;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\GuardSalaryAdvance;
use App\Models\Payment;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Finance\PayrollRunService;
use App\Services\Finance\ProfitabilityService;
use App\Services\SystemSettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{year: int, month: int, start: Carbon, days: int} */
    private function closedPayrollPeriod(): array
    {
        $period = PayrollRunService::lastClosedPeriod();

        return [
            'year' => $period->year,
            'month' => $period->month,
            'start' => $period->copy()->startOfMonth(),
            'days' => $period->daysInMonth,
        ];
    }

    public function test_finance_manager_can_run_full_payroll_cycle_from_shifts(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $director = User::factory()->managingDirector()->create();
        $site = Site::factory()->create();
        $period = $this->closedPayrollPeriod();

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 900000,
            'overtime_shift_rate' => 45000,
            'bank_name' => 'Stanbic Bank',
            'bank_account' => '1234567890',
        ]);

        $normalShift = Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $period['start']->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $period['start']->copy()->addDay()->toDateString(),
            'shift_type' => ShiftType::Overtime,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
                'notes' => 'Month-end payroll',
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->first();
        $this->assertNotNull($run);
        $this->assertSame(PayrollRunStatus::Draft, $run->status);

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(PayrollRunStatus::Calculated, $run->status);
        $this->assertSame(1, $run->guard_count);

        $payslip = PayrollPayslip::query()->where('payroll_run_id', $run->id)->first();
        $this->assertNotNull($payslip);
        $this->assertSame(2, $payslip->total_shifts);
        $perShift = round(900000 / $period['days'], 2);
        $this->assertSame($perShift + 45000.0, (float) $payslip->gross_pay);
        $this->assertTrue($payslip->shifts()->where('shifts.id', $normalShift->id)->exists());

        $this->actingAs($finance)
            ->post(route('payroll.payslips.deductions.store', [$run, $payslip]), [
                'type' => PayrollDeductionType::Penalty->value,
                'label' => 'Late return penalty',
                'amount' => 5000,
            ])
            ->assertRedirect();

        $payslip->refresh();
        $this->assertGreaterThan(0, (float) $payslip->total_deductions);
        $this->assertLessThan((float) $payslip->gross_pay, (float) $payslip->net_pay);

        $this->actingAs($finance)
            ->post(route('payroll.approve', $run))
            ->assertForbidden();

        $this->actingAs($finance)
            ->post(route('payroll.submit', $run))
            ->assertRedirect();

        $this->assertSame(PayrollRunStatus::Submitted, $run->fresh()->status);

        $this->actingAs($director)
            ->post(route('payroll.approve', $run))
            ->assertRedirect();

        $this->assertSame(PayrollRunStatus::Approved, $run->fresh()->status);

        $this->actingAs($finance)
            ->get(route('payroll.export.bank', $run))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($finance)
            ->post(route('payroll.pay', $run))
            ->assertRedirect();

        $this->assertSame(PayrollRunStatus::Paid, $run->fresh()->status);

        $payment = Payment::query()->where('payroll_run_id', $run->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame((float) $run->fresh()->net_total, (float) $payment->amount);
        $this->assertNull($payment->invoice_id);
    }

    public function test_salary_advance_auto_deducts_on_payroll_calculate(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();
        $period = $this->closedPayrollPeriod();

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 1500000,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $period['start']->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($finance)
            ->post(route('guards.advances.store', $guard), [
                'label' => 'Emergency advance',
                'original_amount' => 20000,
                'monthly_installment' => 15000,
            ])
            ->assertRedirect();

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->where('guard_id', $guard->id)->firstOrFail();
        $this->assertTrue($payslip->deductions()->where('type', PayrollDeductionType::Advance)->exists());

        $advance = $guard->salaryAdvances()->firstOrFail();
        $this->assertSame(5000.0, (float) $advance->fresh()->balance_remaining);
    }

    public function test_payslip_pdf_print_route_renders(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-001',
            'period_year' => now()->year,
            'period_month' => now()->month,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => PayrollRunStatus::Calculated,
            'currency' => 'UGX',
        ]);

        $guard = Guard::factory()->create();
        $payslip = PayrollPayslip::query()->create([
            'payroll_run_id' => $run->id,
            'guard_id' => $guard->id,
            'employment_id' => $guard->employment_id,
            'full_name' => $guard->full_name,
            'gross_pay' => 50000,
            'net_pay' => 47500,
            'total_deductions' => 2500,
            'base_shift_rate' => 50000,
            'overtime_shift_rate' => 75000,
        ]);

        $this->actingAs($finance)
            ->get(route('payroll.payslips.print', [$run, $payslip]))
            ->assertOk()
            ->assertSee('Payslip')
            ->assertSee($guard->full_name);
    }

    public function test_payroll_applies_statutory_deductions_from_system_settings(): void
    {
        SystemSetting::query()->first()?->update([
            'payroll_use_progressive_paye' => false,
            'payroll_paye_rate' => 10,
            'payroll_nssf_employee_rate' => 5,
            'payroll_uniform_charge' => 3000,
        ]);

        app(SystemSettingService::class)->applyRuntimeConfig();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();
        $period = $this->closedPayrollPeriod();

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'compensation_type' => CompensationType::Shift,
            'base_shift_rate' => 300000,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $period['start']->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->where('guard_id', $guard->id)->firstOrFail();

        $perShift = round(300000 / $period['days'], 2);
        $this->assertSame($perShift, (float) $payslip->gross_pay);

        $this->assertTrue($payslip->deductions()->where('type', PayrollDeductionType::Paye)->exists());
        $this->assertTrue($payslip->deductions()->where('type', PayrollDeductionType::Nssf)->exists());
        $this->assertTrue($payslip->deductions()->where('type', PayrollDeductionType::Uniform)->exists());

        $paye = (float) $payslip->deductions()->where('type', PayrollDeductionType::Paye)->value('amount');
        $nssf = (float) $payslip->deductions()->where('type', PayrollDeductionType::Nssf)->value('amount');
        $uniform = (float) $payslip->deductions()->where('type', PayrollDeductionType::Uniform)->value('amount');

        $this->assertSame(round($perShift * 0.10, 2), $paye);
        $this->assertSame(round($perShift * 0.05, 2), $nssf);
        $this->assertSame(3000.0, $uniform);
        $this->assertSame(round($perShift - $paye - $nssf - $uniform, 2), (float) $payslip->net_pay);
    }

    public function test_finance_manager_cannot_delete_approved_payroll_but_managing_director_can_reject(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $director = User::factory()->managingDirector()->create();
        $site = Site::factory()->create();
        $period = $this->closedPayrollPeriod();

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 40000,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $period['start']->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $this->actingAs($finance)
            ->post(route('payroll.submit', $run))
            ->assertRedirect();

        $this->actingAs($director)
            ->post(route('payroll.approve', $run))
            ->assertRedirect();

        $this->assertSame(PayrollRunStatus::Approved, $run->fresh()->status);
        $this->assertSame(1, PayrollPayslip::query()->where('payroll_run_id', $run->id)->count());

        $this->actingAs($finance)
            ->post(route('payroll.cancel', $run))
            ->assertForbidden();

        $this->actingAs($director)
            ->post(route('payroll.cancel', $run))
            ->assertRedirect(route('payroll.index'));

        $run->refresh();
        $this->assertSame(PayrollRunStatus::Cancelled, $run->status);
        $this->assertNull($run->approved_at);
        $this->assertSame(0, PayrollPayslip::query()->where('payroll_run_id', $run->id)->count());

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $this->assertSame(PayrollRunStatus::Draft, PayrollRun::query()
            ->whereNot('status', PayrollRunStatus::Cancelled)
            ->latest('id')
            ->value('status'));
    }

    public function test_payroll_uses_system_default_rate_when_guard_base_rate_is_zero(): void
    {
        SystemSetting::query()->first()?->update([
            'payroll_default_base_shift_rate' => 30000,
        ]);

        app(SystemSettingService::class)->applyRuntimeConfig();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();
        $period = $this->closedPayrollPeriod();

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'compensation_type' => CompensationType::Shift,
            'base_shift_rate' => 0,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $period['start']->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->where('guard_id', $guard->id)->firstOrFail();

        $perShift = round(30000 / $period['days'], 2);
        $this->assertSame($perShift, (float) $payslip->base_shift_rate);
        $this->assertSame($perShift, (float) $payslip->gross_pay);
        $this->assertSame($perShift, (float) $run->fresh()->gross_total);
    }

    public function test_payroll_prorates_by_calendar_days_in_payroll_month(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 310000,
        ]);

        $januaryRun = PayrollRun::query()->create([
            'reference' => 'PAY-2024-01-001',
            'period_year' => 2024,
            'period_month' => 1,
            'period_start' => '2024-01-01',
            'period_end' => '2024-01-31',
            'status' => PayrollRunStatus::Draft,
            'currency' => 'UGX',
        ]);

        $februaryRun = PayrollRun::query()->create([
            'reference' => 'PAY-2024-02-001',
            'period_year' => 2024,
            'period_month' => 2,
            'period_start' => '2024-02-01',
            'period_end' => '2024-02-29',
            'status' => PayrollRunStatus::Draft,
            'currency' => 'UGX',
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2024-01-15',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2024-02-15',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($finance)->post(route('payroll.calculate', $januaryRun))->assertRedirect();
        $this->actingAs($finance)->post(route('payroll.calculate', $februaryRun))->assertRedirect();

        $januaryPayslip = PayrollPayslip::query()->where('payroll_run_id', $januaryRun->id)->firstOrFail();
        $februaryPayslip = PayrollPayslip::query()->where('payroll_run_id', $februaryRun->id)->firstOrFail();

        $this->assertSame(round(310000 / 31, 2), (float) $januaryPayslip->gross_pay);
        $this->assertSame(round(310000 / 29, 2), (float) $februaryPayslip->gross_pay);
    }

    public function test_operations_manager_can_view_but_not_manage_payroll(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-01-001',
            'period_year' => 2026,
            'period_month' => 1,
            'period_start' => '2026-01-01',
            'period_end' => '2026-01-31',
            'status' => PayrollRunStatus::Approved,
            'currency' => 'UGX',
        ]);

        $this->actingAs($ops)
            ->get(route('payroll.index'))
            ->assertOk();

        $this->actingAs($ops)
            ->get(route('payroll.create'))
            ->assertForbidden();

        $this->actingAs($ops)
            ->post(route('payroll.cancel', $run))
            ->assertForbidden();
    }

    public function test_paid_payroll_stays_visible_and_blocks_new_run_for_same_period(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-004',
            'period_year' => 2026,
            'period_month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRunStatus::Paid,
            'currency' => 'UGX',
            'guard_count' => 13,
            'net_total' => 2725.84,
        ]);

        $this->actingAs($finance)
            ->get(route('payroll.index'))
            ->assertOk()
            ->assertSee('PAY-2026-08-004')
            ->assertSee('Paid');

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => 2026,
                'period_month' => 8,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('payroll');

        $this->assertSame(
            1,
            PayrollRun::query()
                ->where('period_year', 2026)
                ->where('period_month', 8)
                ->whereNot('status', PayrollRunStatus::Cancelled->value)
                ->count()
        );
    }

    public function test_rejecting_paid_payroll_removes_linked_payment(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $director = User::factory()->managingDirector()->create();

        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-09-001',
            'period_year' => 2026,
            'period_month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => PayrollRunStatus::Approved,
            'currency' => 'UGX',
            'guard_count' => 2,
            'gross_total' => 500000,
            'net_total' => 450000,
        ]);

        $this->actingAs($finance)->post(route('payroll.pay', $run))->assertRedirect();
        $this->assertDatabaseHas('payments', ['payroll_run_id' => $run->id]);

        $this->actingAs($director)->post(route('payroll.cancel', $run))->assertRedirect();
        $this->assertDatabaseMissing('payments', ['payroll_run_id' => $run->id]);
    }

    public function test_profitability_uses_paid_payroll_gross_total_when_available(): void
    {
        PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-010',
            'period_year' => 2026,
            'period_month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRunStatus::Paid,
            'currency' => 'UGX',
            'guard_count' => 10,
            'gross_total' => 1250000,
            'net_total' => 1100000,
            'paid_at' => now(),
        ]);

        $report = app(ProfitabilityService::class)->analyze('2026-08-01', '2026-08-31');

        $this->assertSame(1250000.0, (float) $report['totals']['payroll_cost']);
        $this->assertSame('actual', $report['totals']['payroll_cost_source']);
    }

    public function test_payments_index_backfills_missing_payroll_disbursements(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-004',
            'period_year' => 2026,
            'period_month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRunStatus::Paid,
            'currency' => 'UGX',
            'guard_count' => 13,
            'gross_total' => 3000,
            'net_total' => 2725.84,
            'paid_at' => now(),
        ]);

        $this->assertDatabaseMissing('payments', ['payroll_run_id' => $run->id]);

        $this->actingAs($finance)
            ->get(route('payments.index'))
            ->assertOk()
            ->assertSee('Payroll disbursement')
            ->assertSee('PAY-2026-08-004');

        $this->assertDatabaseHas('payments', [
            'payroll_run_id' => $run->id,
            'amount' => 2725.84,
        ]);
    }

    public function test_payments_scope_tabs_filter_by_month(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        Payment::query()->create([
            'reference' => 'PAY-'.now()->format('Ym').'-0001',
            'amount' => 1000,
            'payment_date' => now()->toDateString(),
            'method' => 'bank_transfer',
        ]);

        Payment::query()->create([
            'reference' => 'PAY-'.now()->subMonth()->format('Ym').'-0001',
            'amount' => 2000,
            'payment_date' => now()->subMonth()->toDateString(),
            'method' => 'bank_transfer',
        ]);

        $this->actingAs($finance)
            ->get(route('payments.index', ['scope' => 'month']))
            ->assertOk()
            ->assertSee('PAY-'.now()->format('Ym').'-0001')
            ->assertDontSee('PAY-'.now()->subMonth()->format('Ym').'-0001');

        $this->actingAs($finance)
            ->get(route('payments.index', ['scope' => 'all']))
            ->assertOk()
            ->assertSee('PAY-'.now()->format('Ym').'-0001')
            ->assertSee('PAY-'.now()->subMonth()->format('Ym').'-0001');
    }

    public function test_managing_director_receives_notification_when_payroll_is_submitted(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $director = User::factory()->managingDirector()->create();

        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-004',
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
            'employment_id' => 'G-001',
            'full_name' => 'Test Guard',
            'gross_pay' => 3000,
            'net_pay' => 2725.84,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.submit', $run))
            ->assertRedirect();

        $this->actingAs($director)
            ->getJson(route('notifications.index'))
            ->assertOk()
            ->assertJsonFragment(['summary' => 'Payroll PAY-2026-08-004 (2026-08) submitted for Managing Director approval.'])
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.url', route('payroll.show', $run));

        $this->actingAs($finance)
            ->getJson(route('notifications.index'))
            ->assertJsonPath('unread_count', 0);
    }

    public function test_create_payroll_form_lists_closed_and_open_periods(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-004',
            'period_year' => 2026,
            'period_month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRunStatus::Paid,
            'currency' => 'UGX',
        ]);

        PayrollRun::query()->create([
            'reference' => 'PAY-2026-09-001',
            'period_year' => 2026,
            'period_month' => 9,
            'period_start' => '2026-09-01',
            'period_end' => '2026-09-30',
            'status' => PayrollRunStatus::Draft,
            'currency' => 'UGX',
        ]);

        $this->actingAs($finance)
            ->get(route('payroll.create'))
            ->assertOk()
            ->assertSee('Existing payroll periods')
            ->assertSee('2026-08')
            ->assertSee('2026-09')
            ->assertSee('Paid')
            ->assertSee('Draft')
            ->assertSee('cannot be opened again');
    }

    public function test_cannot_open_payroll_before_month_ends(): void
    {
        if (PayrollRunService::isPeriodClosed(now()->year, now()->month)) {
            $this->markTestSkipped('Current month is already closed in this test environment.');
        }

        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => now()->year,
                'period_month' => now()->month,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('payroll');
    }

    public function test_managing_director_sees_delete_action_on_paid_payroll(): void
    {
        $director = User::factory()->managingDirector()->create();

        PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-004',
            'period_year' => 2026,
            'period_month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRunStatus::Paid,
            'currency' => 'UGX',
            'guard_count' => 13,
            'net_total' => 2725.84,
            'paid_at' => now(),
        ]);

        $this->actingAs($director)
            ->get(route('payroll.index'))
            ->assertOk()
            ->assertSee('Delete run')
            ->assertDontSee('Reject run');
    }

    public function test_staff_are_included_in_payroll_without_shifts(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 1200000,
            'date_employed' => $period['start']->toDateString(),
            'full_name' => 'Finance Officer One',
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->firstOrFail();

        $this->assertSame(1, $run->fresh()->guard_count);
        $this->assertSame(1200000.0, (float) $payslip->gross_pay);
        $this->assertTrue($payslip->isFixedSalary());
        $this->assertNotNull($payslip->staff_id);
        $this->assertNull($payslip->guard_id);
        $this->assertSame(0, $payslip->total_shifts);
    }

    public function test_staff_payslips_exclude_uniform_but_apply_paye_and_nssf(): void
    {
        SystemSetting::query()->first()?->update([
            'payroll_use_progressive_paye' => false,
            'payroll_paye_rate' => 10,
            'payroll_nssf_employee_rate' => 5,
            'payroll_uniform_charge' => 3000,
        ]);

        app(SystemSettingService::class)->applyRuntimeConfig();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 1000000,
            'date_employed' => $period['start']->toDateString(),
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $payslip = PayrollPayslip::query()->firstOrFail();

        $this->assertTrue($payslip->deductions()->where('type', PayrollDeductionType::Paye)->exists());
        $this->assertTrue($payslip->deductions()->where('type', PayrollDeductionType::Nssf)->exists());
        $this->assertFalse($payslip->deductions()->where('type', PayrollDeductionType::Uniform)->exists());
    }

    public function test_staff_pro_rated_when_hired_mid_month(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => $period['days'] * 10000,
            'date_employed' => $period['start']->copy()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $expectedDays = $period['days'] - 10;
        $expectedGross = round(($period['days'] * 10000) * ($expectedDays / $period['days']), 2);

        $this->assertSame($expectedGross, (float) PayrollPayslip::query()->value('gross_pay'));
    }

    public function test_staff_pro_rated_when_employment_ends_mid_month(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => $period['days'] * 10000,
            'date_employed' => $period['start']->toDateString(),
            'employment_end_date' => $period['start']->copy()->addDays(19)->toDateString(),
            'employment_status' => EmploymentStatus::Resigned,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $expectedGross = round(($period['days'] * 10000) * (20 / $period['days']), 2);

        $this->assertSame($expectedGross, (float) PayrollPayslip::query()->value('gross_pay'));
    }

    public function test_staff_receives_full_month_when_no_employment_end_date(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 1000000,
            'date_employed' => $period['start']->toDateString(),
            'employment_status' => EmploymentStatus::Active,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $this->assertSame(1000000.0, (float) PayrollPayslip::query()->value('gross_pay'));
    }

    public function test_staff_excluded_from_payroll_after_employment_end_date(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 1000000,
            'date_employed' => $period['start']->copy()->subMonths(6)->toDateString(),
            'employment_end_date' => $period['start']->copy()->subMonth()->endOfMonth()->toDateString(),
            'employment_status' => EmploymentStatus::Terminated,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $this->assertSame(0, PayrollPayslip::query()->count());
    }

    public function test_site_scoped_payroll_excludes_staff(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => $site->region_id,
            'monthly_salary' => 900000,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
                'site_id' => $site->id,
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $this->assertSame(0, $run->fresh()->guard_count);
    }

    public function test_managing_director_can_return_submitted_payroll_to_finance(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $director = User::factory()->managingDirector()->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create(['region_id' => null, 'monthly_salary' => 500000, 'date_employed' => $period['start']->toDateString()]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), ['period_year' => $period['year'], 'period_month' => $period['month']])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();
        $this->actingAs($finance)->post(route('payroll.submit', $run))->assertRedirect();

        $this->actingAs($director)
            ->post(route('payroll.reject', $run), ['reason' => 'Check advance deductions'])
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(PayrollRunStatus::Calculated, $run->status);
        $this->assertNull($run->submitted_at);
        $this->assertSame(1, $run->payslips()->count());
        $this->assertStringContainsString('Returned to finance: Check advance deductions', $run->notes);
    }

    public function test_rejecting_payroll_requires_a_reason(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $director = User::factory()->managingDirector()->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create(['region_id' => null, 'monthly_salary' => 500000, 'date_employed' => $period['start']->toDateString()]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), ['period_year' => $period['year'], 'period_month' => $period['month']])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();
        $this->actingAs($finance)->post(route('payroll.submit', $run))->assertRedirect();

        $this->actingAs($director)
            ->post(route('payroll.reject', $run), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($director)
            ->post(route('payroll.reject', $run), ['reason' => 'ab'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(PayrollRunStatus::Submitted, $run->fresh()->status);
    }

    public function test_salary_guard_is_paid_without_shifts(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Guard::factory()->create([
            'region_id' => null,
            'compensation_type' => CompensationType::Salary,
            'base_shift_rate' => 900000,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), ['period_year' => $period['year'], 'period_month' => $period['month']])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $payslip = PayrollPayslip::query()->firstOrFail();
        $this->assertSame(CompensationType::Salary, $payslip->compensation_type);
        $this->assertSame(900000.0, (float) $payslip->gross_pay);
    }

    public function test_guard_pro_rated_when_employment_ends_mid_month(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Guard::factory()->create([
            'region_id' => null,
            'compensation_type' => CompensationType::Salary,
            'base_shift_rate' => $period['days'] * 10000,
            'date_employed' => $period['start']->toDateString(),
            'employment_end_date' => $period['start']->copy()->addDays(19)->toDateString(),
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), ['period_year' => $period['year'], 'period_month' => $period['month']])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $expected = round(($period['days'] * 10000) * (20 / $period['days']), 2);
        $this->assertSame($expected, (float) PayrollPayslip::query()->value('gross_pay'));
    }

    public function test_staff_advance_auto_deducts_on_payroll(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();
        $staff = Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 1000000,
            'date_employed' => $period['start']->toDateString(),
        ]);

        GuardSalaryAdvance::query()->create([
            'staff_id' => $staff->id,
            'label' => 'Emergency advance',
            'original_amount' => 200000,
            'balance_remaining' => 200000,
            'monthly_installment' => 100000,
            'is_active' => true,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), ['period_year' => $period['year'], 'period_month' => $period['month']])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $payslip = PayrollPayslip::query()->firstOrFail();
        $this->assertTrue($payslip->deductions()->where('type', PayrollDeductionType::Advance)->exists());
    }

    public function test_payslip_pdf_download_returns_pdf(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 800000,
            'date_employed' => $period['start']->toDateString(),
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), ['period_year' => $period['year'], 'period_month' => $period['month']])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $payslip = PayrollPayslip::query()->firstOrFail();

        $response = $this->actingAs($finance)
            ->get(route('payroll.payslips.print', [$run, $payslip, 'format' => 'pdf']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_progressive_paye_applies_uganda_2026_brackets_on_staff(): void
    {
        config(['psg.payroll.use_progressive_paye' => true]);

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 1_000_000,
            'date_employed' => $period['start']->toDateString(),
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $payslip = PayrollPayslip::query()->firstOrFail();
        $paye = (float) $payslip->deductions()->where('type', PayrollDeductionType::Paye)->value('amount');

        $this->assertSame(188_250.0, $paye);
        $this->assertStringContainsString('PAYE', (string) $payslip->deductions()->where('type', PayrollDeductionType::Paye)->value('label'));
    }

    public function test_payroll_net_pay_uses_ura_paye_and_nssf_on_900000_gross(): void
    {
        config([
            'psg.payroll.use_progressive_paye' => true,
            'psg.payroll.nssf_employee_rate' => 5,
            'psg.payroll.uniform_charge' => 0,
            'psg.payroll.paye_brackets' => \App\Support\Finance\PayrollPayeCalculator::defaults(),
        ]);

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 900_000,
            'date_employed' => $period['start']->toDateString(),
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $payslip = PayrollPayslip::query()->firstOrFail();
        $paye = (float) $payslip->deductions()->where('type', PayrollDeductionType::Paye)->value('amount');
        $nssf = (float) $payslip->deductions()->where('type', PayrollDeductionType::Nssf)->value('amount');

        // URA: 33,750 + 30% × (900,000 − 485,000) = 158,250
        $this->assertSame(158_250.0, $paye);
        $this->assertSame(45_000.0, $nssf);
        $this->assertSame(900_000.0, (float) $payslip->gross_pay);
        $this->assertSame(696_750.0, (float) $payslip->net_pay);
    }

    public function test_progressive_paye_is_zero_below_tax_free_threshold(): void
    {
        config(['psg.payroll.use_progressive_paye' => true]);

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $period = $this->closedPayrollPeriod();

        Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 300_000,
            'date_employed' => $period['start']->toDateString(),
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period['year'],
                'period_month' => $period['month'],
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $this->assertFalse(PayrollPayslip::query()->firstOrFail()->deductions()->where('type', PayrollDeductionType::Paye)->exists());
    }
}
