<?php

namespace Tests\Feature\Hr;

use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceType;
use App\Enums\AssetLineStatus;
use App\Enums\PayrollDeductionType;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\GuardAssetRecovery;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuardAssetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_manager_can_issue_and_return_assets(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create();

        $this->actingAs($hr)
            ->post(route('assets.store'), [
                'guard_id' => $guard->id,
                'issuance_type' => AssetIssuanceType::InitialKit->value,
                'issued_at' => now()->toDateString(),
                'lines' => [
                    [
                        'asset_category' => AssetCategory::Uniform->value,
                        'description' => 'Shirt',
                        'size' => 'L',
                        'quantity' => 2,
                        'unit_value' => 25000,
                        'recover_cost' => '1',
                        'recovery_amount' => 50000,
                        'monthly_recovery' => 10000,
                    ],
                    [
                        'asset_category' => AssetCategory::Radio->value,
                        'description' => 'Handheld radio',
                        'serial_number' => 'RAD-001',
                        'quantity' => 1,
                        'unit_value' => 150000,
                    ],
                ],
            ])
            ->assertRedirect();

        $issuance = GuardAssetIssuance::query()->first();
        $this->assertNotNull($issuance);
        $this->assertSame(2, $issuance->lines()->count());

        $recovery = GuardAssetRecovery::query()->where('guard_id', $guard->id)->first();
        $this->assertNotNull($recovery);
        $this->assertSame(50000.0, (float) $recovery->balance_remaining);

        $line = $issuance->lines()->where('asset_category', AssetCategory::Uniform)->first();

        $this->actingAs($hr)
            ->post(route('assets.return', $issuance), [
                'termination_return' => '1',
                'lines' => [
                    $line->id => [
                        'return_qty' => 2,
                        'disposition' => 'returned',
                    ],
                ],
            ])
            ->assertRedirect(route('assets.show', $issuance));

        $this->assertSame(AssetLineStatus::Returned, $line->fresh()->status);
        $this->assertSame(2, $line->fresh()->quantity_returned);
    }

    public function test_hr_manager_can_edit_issuance_before_returns_or_payroll(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $wrongGuard = Guard::factory()->create();
        $correctGuard = Guard::factory()->create();

        $this->actingAs($hr)
            ->post(route('assets.store'), [
                'guard_id' => $wrongGuard->id,
                'issuance_type' => AssetIssuanceType::InitialKit->value,
                'issued_at' => now()->toDateString(),
                'lines' => [
                    [
                        'asset_category' => AssetCategory::Uniform->value,
                        'description' => 'Shirt',
                        'size' => 'L',
                        'quantity' => 1,
                        'unit_value' => 25000,
                    ],
                ],
            ])
            ->assertRedirect();

        $issuance = GuardAssetIssuance::query()->firstOrFail();
        $line = $issuance->lines()->firstOrFail();

        $this->actingAs($hr)
            ->put(route('assets.update', $issuance), [
                'guard_id' => $correctGuard->id,
                'issuance_type' => AssetIssuanceType::Replacement->value,
                'issued_at' => now()->subDay()->toDateString(),
                'notes' => 'Corrected guard assignment',
                'lines' => [
                    [
                        'id' => $line->id,
                        'asset_category' => AssetCategory::Uniform->value,
                        'description' => 'Shirt',
                        'size' => 'XL',
                        'quantity' => 1,
                        'unit_value' => 25000,
                    ],
                ],
            ])
            ->assertRedirect(route('assets.show', $issuance));

        $issuance->refresh();

        $this->assertSame($correctGuard->id, $issuance->guard_id);
        $this->assertSame(AssetIssuanceType::Replacement, $issuance->issuance_type);
        $this->assertSame('Corrected guard assignment', $issuance->notes);
        $this->assertSame('XL', $line->fresh()->size);
    }

    public function test_hr_manager_can_delete_unprocessed_issuance(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create();

        $this->actingAs($hr)
            ->post(route('assets.store'), [
                'guard_id' => $guard->id,
                'issuance_type' => AssetIssuanceType::TopUp->value,
                'issued_at' => now()->toDateString(),
                'lines' => [
                    [
                        'asset_category' => AssetCategory::Other->value,
                        'description' => 'Belt',
                        'quantity' => 1,
                        'unit_value' => 10000,
                    ],
                ],
            ])
            ->assertRedirect();

        $issuance = GuardAssetIssuance::query()->firstOrFail();

        $this->actingAs($hr)
            ->delete(route('assets.destroy', $issuance))
            ->assertRedirect(route('assets.index'));

        $this->assertDatabaseMissing('guard_asset_issuances', ['id' => $issuance->id]);
        $this->assertDatabaseMissing('guard_asset_lines', ['guard_asset_issuance_id' => $issuance->id]);
    }

    public function test_issuance_with_returns_cannot_be_edited_or_deleted(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create();

        $this->actingAs($hr)
            ->post(route('assets.store'), [
                'guard_id' => $guard->id,
                'issuance_type' => AssetIssuanceType::InitialKit->value,
                'issued_at' => now()->toDateString(),
                'lines' => [
                    [
                        'asset_category' => AssetCategory::Uniform->value,
                        'description' => 'Shirt',
                        'quantity' => 2,
                        'unit_value' => 25000,
                    ],
                ],
            ]);

        $issuance = GuardAssetIssuance::query()->firstOrFail();
        $line = $issuance->lines()->firstOrFail();

        $this->actingAs($hr)
            ->post(route('assets.return', $issuance), [
                'lines' => [
                    $line->id => [
                        'return_qty' => 1,
                        'disposition' => 'returned',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($hr)
            ->get(route('assets.edit', $issuance))
            ->assertRedirect(route('assets.show', $issuance));

        $this->actingAs($hr)
            ->delete(route('assets.destroy', $issuance))
            ->assertRedirect(route('assets.show', $issuance));

        $this->assertDatabaseHas('guard_asset_issuances', ['id' => $issuance->id]);
    }

    public function test_asset_recovery_deducts_on_payroll(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();
        $periodStart = now()->subMonthNoOverflow()->startOfMonth();
        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'base_shift_rate' => 500000,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => $periodStart->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
        ]);

        $this->actingAs(User::factory()->role(UserRole::HrManager)->create())
            ->post(route('assets.store'), [
                'guard_id' => $guard->id,
                'issuance_type' => AssetIssuanceType::Replacement->value,
                'issued_at' => now()->toDateString(),
                'lines' => [
                    [
                        'asset_category' => AssetCategory::Boots->value,
                        'description' => 'Safety boots',
                        'size' => '42',
                        'quantity' => 1,
                        'unit_value' => 80000,
                        'recover_cost' => '1',
                        'recovery_amount' => 80000,
                        'monthly_recovery' => 20000,
                    ],
                ],
            ]);

        $this->actingAs($finance)
            ->post(route('payroll.store'), [
                'period_year' => $periodStart->year,
                'period_month' => $periodStart->month,
            ])
            ->assertRedirect();

        $run = PayrollRun::query()->firstOrFail();

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect();

        $payslip = PayrollPayslip::query()->where('guard_id', $guard->id)->first();
        $this->assertNotNull($payslip);

        $deduction = $payslip->deductions()->where('type', PayrollDeductionType::AssetRecovery)->first();
        $this->assertNotNull($deduction);
        $this->assertGreaterThan(0, (float) $deduction->amount);
        $this->assertLessThanOrEqual(20000.0, (float) $deduction->amount);

        $recovery = GuardAssetRecovery::query()->where('guard_id', $guard->id)->first();
        $this->assertSame(
            round(80000 - (float) $deduction->amount, 2),
            (float) $recovery->fresh()->balance_remaining,
        );

        $issuance = GuardAssetIssuance::query()->firstOrFail();

        $this->actingAs($finance)
            ->get(route('assets.edit', $issuance))
            ->assertRedirect(route('assets.show', $issuance));

        $this->actingAs($finance)
            ->delete(route('assets.destroy', $issuance))
            ->assertRedirect(route('assets.show', $issuance));
    }

    public function test_operations_and_finance_managers_can_issue_assets(): void
    {
        $guard = Guard::factory()->create();

        foreach ([UserRole::OperationsManager, UserRole::FinanceManager] as $role) {
            $user = User::factory()->role($role)->create();

            app(\App\Support\Access\RolePermissionService::class)->seedDefaults();
            app(\App\Support\Access\RolePermissionService::class)->flushCache();

            $this->actingAs($user)
                ->get(route('assets.create'))
                ->assertOk();

            $this->actingAs($user)
                ->post(route('assets.store'), [
                    'guard_id' => $guard->id,
                    'issuance_type' => AssetIssuanceType::TopUp->value,
                    'issued_at' => now()->toDateString(),
                    'lines' => [
                        [
                            'asset_category' => AssetCategory::Other->value,
                            'description' => 'Belt',
                            'quantity' => 1,
                            'unit_value' => 10000,
                        ],
                    ],
                ])
                ->assertRedirect();
        }
    }
}
