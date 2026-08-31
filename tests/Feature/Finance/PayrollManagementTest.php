<?php

namespace Tests\Feature\Finance;

use App\Enums\PayrollDeductionType;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Payment;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Finance\ProfitabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_manager_can_run_full_payroll_cycle_from_shifts(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $director = User::factory()->managingDirector()->create();
        $site = Site::factory()->create();

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
            'shift_date' => now()->startOfMonth()->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => now()->startOfMonth()->addDay()->toDateString(),
            'shift_type' => ShiftType::Overtime,
            'status' => ShiftStatus::Completed,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => now()->year,
                'period_month' => now()->month,
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
        $perShift = round(900000 / now()->daysInMonth, 2);
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

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 1500000,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => now()->startOfMonth()->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
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
                'period_year' => now()->year,
                'period_month' => now()->month,
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
            'payroll_paye_rate' => 10,
            'payroll_nssf_employee_rate' => 5,
            'payroll_uniform_charge' => 3000,
        ]);

        app(\App\Services\SystemSettingService::class)->applyRuntimeConfig();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 300000,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => now()->startOfMonth()->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => now()->year,
                'period_month' => now()->month,
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->where('guard_id', $guard->id)->firstOrFail();

        $perShift = round(300000 / now()->daysInMonth, 2);
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

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 40000,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => now()->startOfMonth()->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => now()->year,
                'period_month' => now()->month,
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
                'period_year' => now()->year,
                'period_month' => now()->month,
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

        app(\App\Services\SystemSettingService::class)->applyRuntimeConfig();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();

        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 0,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => now()->startOfMonth()->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => now()->year,
                'period_month' => now()->month,
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->where('guard_id', $guard->id)->firstOrFail();

        $perShift = round(30000 / now()->daysInMonth, 2);
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
            'status' => ShiftStatus::Completed,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2024-02-15',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
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
}
