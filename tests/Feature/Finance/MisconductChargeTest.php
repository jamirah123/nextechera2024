<?php

namespace Tests\Feature\Finance;

use App\Enums\PayrollDeductionType;
use App\Enums\UserRole;
use App\Models\EmployeeMisconductCharge;
use App\Models\Guard;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Staff;
use App\Models\User;
use App\Services\Finance\PayrollRunService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MisconductChargeTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_records_guard_and_staff_charges_below_advances_and_payroll_recovers_them(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create();
        $period = PayrollRunService::lastClosedPeriod();
        $staff = Staff::factory()->create([
            'region_id' => null,
            'monthly_salary' => 1000000,
            'date_employed' => $period->copy()->startOfMonth()->toDateString(),
        ]);

        $this->actingAs($hr)
            ->post(route('guards.charges.store', $guard), [
                'charge_reason' => 'Lost radio',
                'charge_amount' => 50000,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(0, EmployeeMisconductCharge::query()->count());

        $this->actingAs($finance)
            ->post(route('guards.charges.store', $guard), [
                'charge_reason' => 'Lost radio',
                'charge_incident_on' => '2026-10-01',
                'charge_amount' => 50000,
                'charge_installment' => 20000,
                'charge_notes' => 'Site report',
            ])
            ->assertRedirect();

        $guardCharge = $guard->misconductCharges()->firstOrFail();
        $this->assertSame(50000.0, (float) $guardCharge->original_amount);
        $this->assertSame(20000.0, (float) $guardCharge->monthly_installment);
        $this->assertTrue($guardCharge->is_active);

        $this->actingAs($finance)
            ->get(route('guards.show', $guard))
            ->assertOk()
            ->assertSee('Misconduct charges')
            ->assertSee('Lost radio')
            ->assertSee('Salary advances');

        $this->actingAs($finance)
            ->post(route('staff.charges.store', $staff), [
                'charge_reason' => 'Damaged laptop',
                'charge_amount' => 120000,
                'charge_installment' => 40000,
            ])
            ->assertRedirect();

        $this->actingAs($finance)
            ->get(route('charges.index'))
            ->assertOk()
            ->assertSee('Lost radio')
            ->assertSee('Damaged laptop');

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $period->year,
                'period_month' => $period->month,
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();
        $this->actingAs($finance)->post(route('payroll.calculate', $run))->assertRedirect();

        $payslip = PayrollPayslip::query()->where('staff_id', $staff->id)->firstOrFail();
        $deduction = $payslip->deductions()->where('type', PayrollDeductionType::Penalty)->firstOrFail();
        $this->assertSame(40000.0, (float) $deduction->amount);
        $this->assertSame(80000.0, (float) $staff->misconductCharges()->first()->balance_remaining);

        $this->actingAs($finance)
            ->post(route('guards.charges.write-off', [$guard, $guardCharge]))
            ->assertRedirect();

        $this->assertFalse($guardCharge->fresh()->is_active);
        $this->assertSame(0.0, (float) $guardCharge->fresh()->balance_remaining);
    }
}
