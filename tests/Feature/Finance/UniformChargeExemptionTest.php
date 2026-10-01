<?php

namespace Tests\Feature\Finance;

use App\Enums\CompensationType;
use App\Enums\PayrollDeductionType;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UniformChargeStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\GuardUniformChargeRevision;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniformChargeExemptionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_authorized_roles_record_an_effective_dated_exemption(): void
    {
        Carbon::setTestNow('2026-10-15 09:00:00');

        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG008',
            'first_name' => 'Grace',
            'last_name' => 'Namuli',
            'full_name' => 'Grace Namuli',
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
        ]);

        $hr = User::factory()->role(UserRole::HrManager)->create(['name' => 'HR Manager']);
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($shiftManager)
            ->get(route('guards.show', $guard))
            ->assertOk()
            ->assertSee('Uniform charge')
            ->assertDontSee('Record a uniform charge change');

        $this->actingAs($shiftManager)
            ->post(route('guards.uniform-charge-revisions.store', $guard), [
                'status' => UniformChargeStatus::Exempt->value,
                'effective_from' => '2026-09-01',
                'reason' => 'Approved uniform exemption',
            ])
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->actingAs($shiftManager)
            ->get(route('reports.uniform-exemptions'))
            ->assertForbidden();

        $this->actingAs($hr)
            ->from(route('guards.show', $guard))
            ->post(route('guards.uniform-charge-revisions.store', $guard), [
                'status' => UniformChargeStatus::Exempt->value,
                'effective_from' => '2026-09-01',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($hr)
            ->from(route('guards.show', $guard))
            ->post(route('guards.uniform-charge-revisions.store', $guard), [
                'status' => UniformChargeStatus::Subject->value,
                'effective_from' => '2026-09-01',
                'reason' => 'Already the company rule',
            ])
            ->assertSessionHasErrors('effective_from');

        $this->actingAs($hr)
            ->post(route('guards.uniform-charge-revisions.store', $guard), [
                'status' => UniformChargeStatus::Exempt->value,
                'effective_from' => '2026-09-01',
                'reason' => 'Company-provided uniform / approved exemption',
                'notes' => 'Issued at Kampala stores',
            ])
            ->assertRedirect(route('guards.show', $guard));

        $exempt = GuardUniformChargeRevision::query()->where('guard_id', $guard->id)->firstOrFail();
        $this->assertSame(UniformChargeStatus::Exempt, $exempt->status);
        $this->assertSame('2026-09-01', $exempt->effective_from->toDateString());
        $this->assertNull($exempt->effective_to);
        $this->assertSame($hr->id, $exempt->approved_by);
        $this->assertSame($hr->id, $exempt->created_by);
        $this->assertNotNull($exempt->approved_at);

        $audit = AuditLog::query()->where('action', 'uniform_charge.changed')->where('subject_id', $guard->id)->firstOrFail();
        $this->assertSame($hr->id, $audit->actor_id);
        $this->assertSame('subject', $audit->context['previous_status']);
        $this->assertSame('exempt', $audit->context['new_status']);
        $this->assertSame('2026-09-01', $audit->context['effective_from']);
        $this->assertStringContainsString('PSG008', $audit->summary);

        $this->actingAs($hr)
            ->get(route('guards.show', $guard))
            ->assertOk()
            ->assertSee('Exempt from uniform charge')
            ->assertSee('Company-provided uniform / approved exemption')
            ->assertSee('HR Manager')
            ->assertSee('Record a uniform charge change');

        $this->actingAs($finance)
            ->post(route('guards.uniform-charge-revisions.store', $guard), [
                'status' => UniformChargeStatus::Subject->value,
                'effective_from' => '2026-10-01',
                'reason' => 'Exemption ended',
            ])
            ->assertRedirect(route('guards.show', $guard));

        $exempt->refresh();
        $this->assertSame('2026-09-30', $exempt->effective_to->toDateString());
        $this->assertSame(2, $guard->uniformChargeRevisions()->count());

        $this->actingAs($admin)
            ->from(route('guards.show', $guard))
            ->post(route('guards.uniform-charge-revisions.store', $guard), [
                'status' => UniformChargeStatus::Subject->value,
                'effective_from' => '2026-10-01',
                'reason' => 'Duplicate status',
            ])
            ->assertSessionHasErrors('effective_from');

        $this->actingAs($hr)
            ->get(route('reports.uniform-exemptions', ['scope' => 'active']))
            ->assertOk()
            ->assertDontSee('PSG008');

        $this->actingAs($hr)
            ->get(route('reports.uniform-exemptions', ['scope' => 'expired']))
            ->assertOk()
            ->assertSee('PSG008')
            ->assertSee('Grace Namuli')
            ->assertSee('Company-provided uniform / approved exemption')
            ->assertSee('Exempt from uniform charge');

        $this->actingAs($finance)
            ->get(route('reports.uniform-exemptions', [
                'period_year' => 2026,
                'period_month' => 10,
            ]))
            ->assertOk()
            ->assertSee('Exemption ended')
            ->assertSee('Subject to uniform charge');

        $this->actingAs($hr)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Uniform Charge Exemptions');
    }

    public function test_payroll_uses_the_exemption_in_force_for_that_period_and_leaves_finalized_pay_unchanged(): void
    {
        Carbon::setTestNow('2026-10-15 09:00:00');

        SystemSetting::query()->first()?->update([
            'payroll_use_progressive_paye' => false,
            'payroll_paye_rate' => 0,
            'payroll_nssf_employee_rate' => 5,
            'payroll_uniform_charge' => 2500,
        ]);
        app(SystemSettingService::class)->applyRuntimeConfig();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $director = User::factory()->managingDirector()->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $site = Site::factory()->create();

        $guard = Guard::factory()->create([
            'employment_id' => 'PSG008',
            'full_name' => 'Grace Namuli',
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'compensation_type' => CompensationType::Shift,
            'base_shift_rate' => 170000,
            'date_employed' => '2026-08-01',
        ]);

        $this->actingAs($hr)->post(route('guards.uniform-charge-revisions.store', $guard), [
            'status' => UniformChargeStatus::Exempt->value,
            'effective_from' => '2026-08-01',
            'reason' => 'Approved uniform exemption',
        ])->assertRedirect();

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2026-08-15',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), ['period_year' => 2026, 'period_month' => 8])
            ->assertRedirect();

        $august = PayrollRun::query()->where('period_month', 8)->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $august))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->where('payroll_run_id', $august->id)->where('guard_id', $guard->id)->firstOrFail();
        $uniform = $payslip->deductions()->where('type', PayrollDeductionType::Uniform)->firstOrFail();
        $nssf = (float) $payslip->deductions()->where('type', PayrollDeductionType::Nssf)->value('amount');
        $net = (float) $payslip->net_pay;

        $this->assertSame(0.0, (float) $uniform->amount);
        $this->assertSame('Uniform charge — Exempt', $uniform->label);
        $this->assertGreaterThan(0, $nssf);
        $this->assertSame(2500.0, (float) SystemSetting::query()->value('payroll_uniform_charge'));

        $this->actingAs($finance)
            ->get(route('payroll.payslips.show', [$august, $payslip]))
            ->assertOk()
            ->assertSee('Grace Namuli')
            ->assertSee('Uniform charge — Exempt')
            ->assertSee('UGX 0');

        $this->actingAs($hr)->post(route('guards.uniform-charge-revisions.store', $guard), [
            'status' => UniformChargeStatus::Subject->value,
            'effective_from' => '2026-09-01',
            'reason' => 'Exemption ended',
        ])->assertRedirect();

        $payslip->refresh();
        $uniform->refresh();
        $this->assertSame(0.0, (float) $uniform->amount);
        $this->assertSame('Uniform charge — Exempt', $uniform->label);
        $this->assertSame($nssf, (float) $payslip->deductions()->where('type', PayrollDeductionType::Nssf)->value('amount'));
        $this->assertSame($net, (float) $payslip->net_pay);

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $august))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->where('payroll_run_id', $august->id)->where('guard_id', $guard->id)->firstOrFail();
        $this->assertSame(0.0, (float) $payslip->deductions()->where('type', PayrollDeductionType::Uniform)->value('amount'));
        $this->assertSame('Uniform charge — Exempt', $payslip->deductions()->where('type', PayrollDeductionType::Uniform)->value('label'));

        $this->actingAs($finance)->post(route('payroll.submit', $august))->assertRedirect();
        $this->actingAs($director)->post(route('payroll.approve', $august))->assertRedirect();
        $this->assertSame(PayrollRunStatus::Approved, $august->fresh()->status);

        $this->actingAs($finance)
            ->from(route('payroll.show', $august))
            ->post(route('payroll.calculate', $august))
            ->assertSessionHasErrors('payroll');

        $this->assertSame(0.0, (float) $payslip->fresh()->deductions()->where('type', PayrollDeductionType::Uniform)->value('amount'));

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2026-09-10',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), ['period_year' => 2026, 'period_month' => 9])
            ->assertRedirect();

        $september = PayrollRun::query()->where('period_month', 9)->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $september))
            ->assertRedirect();

        $septemberPayslip = PayrollPayslip::query()->where('payroll_run_id', $september->id)->where('guard_id', $guard->id)->firstOrFail();
        $septemberUniform = $septemberPayslip->deductions()->where('type', PayrollDeductionType::Uniform)->firstOrFail();

        $this->assertSame(2500.0, (float) $septemberUniform->amount);
        $this->assertSame('Uniform charge', $septemberUniform->label);
        $this->assertSame(0.0, (float) $payslip->fresh()->deductions()->where('type', PayrollDeductionType::Uniform)->value('amount'));
    }
}
