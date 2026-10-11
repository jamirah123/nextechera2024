<?php

namespace Tests\Feature\Procurement;

use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceType;
use App\Enums\AssetLineStatus;
use App\Enums\AssetRecipientType;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\GuardAssetLine;
use App\Models\GuardAssetRecovery;
use App\Models\ProcurementStockItem;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAssetIssuanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_form_lists_guards_and_staff_and_issues_to_the_selected_type(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $guard = Guard::factory()->create(['full_name' => 'Agnes Adong', 'employment_id' => 'PSG001']);
        $staff = Staff::factory()->create([
            'full_name' => 'Jane Nansubuga',
            'employment_id' => 'STF001',
            'job_title' => 'Operations Manager',
        ]);
        Staff::factory()->create([
            'full_name' => 'Left Employee',
            'employment_status' => EmploymentStatus::Resigned,
        ]);

        $this->actingAs($officer)
            ->get(route('assets.create'))
            ->assertOk()
            ->assertSee('PSG001')
            ->assertSee('STF001')
            ->assertSee('Operations Manager')
            ->assertDontSee('Left Employee');

        $stock = ProcurementStockItem::query()->create([
            'reference' => 'STK-LAP-1',
            'name' => 'Company laptop',
            'category' => AssetCategory::Laptop->value,
            'unit' => 'pcs',
            'quantity_on_hand' => 2,
            'reorder_level' => 0,
            'is_active' => true,
        ]);

        $this->actingAs($officer)
            ->post(route('assets.store'), [
                'recipient_type' => AssetRecipientType::Staff->value,
                'guard_id' => $guard->id,
                'staff_id' => $staff->id,
                'issuance_type' => AssetIssuanceType::InitialKit->value,
                'issued_at' => now()->toDateString(),
                'lines' => [[
                    'stock_item_id' => $stock->id,
                    'asset_category' => AssetCategory::Laptop->value,
                    'description' => 'Company laptop',
                    'serial_number' => 'CSL-IT-0042',
                    'quantity' => 1,
                    'recover_cost' => '1',
                    'recovery_amount' => 1500000,
                ]],
            ])
            ->assertRedirect();

        $issuance = GuardAssetIssuance::query()->first();
        $this->assertNotNull($issuance);
        $this->assertSame(AssetRecipientType::Staff, $issuance->recipient_type);
        $this->assertSame($staff->id, $issuance->staff_id);
        $this->assertNull($issuance->guard_id);
        $this->assertSame(1, $stock->fresh()->quantity_on_hand);
        $this->assertSame(0, GuardAssetRecovery::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'asset.issued')->exists());

        $this->actingAs($officer)
            ->post(route('assets.store'), [
                'recipient_type' => AssetRecipientType::Guard->value,
                'staff_id' => $staff->id,
                'guard_id' => $guard->id,
                'issuance_type' => AssetIssuanceType::Replacement->value,
                'issued_at' => now()->toDateString(),
                'lines' => [[
                    'asset_category' => AssetCategory::Uniform->value,
                    'description' => 'Shirt',
                    'quantity' => 1,
                ]],
            ])
            ->assertRedirect();

        $guardIssue = GuardAssetIssuance::query()->where('guard_id', $guard->id)->first();
        $this->assertNotNull($guardIssue);
        $this->assertNull($guardIssue->staff_id);
        $this->assertSame(AssetRecipientType::Guard, $guardIssue->recipient_type);
    }

    public function test_serialized_lost_and_inactive_staff_cannot_be_issued(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $staff = Staff::factory()->create();
        $other = Staff::factory()->create();
        $inactive = Staff::factory()->create(['employment_status' => EmploymentStatus::Terminated]);

        $this->actingAs($officer)
            ->post(route('assets.store'), [
                'recipient_type' => 'staff',
                'staff_id' => $staff->id,
                'issuance_type' => AssetIssuanceType::InitialKit->value,
                'issued_at' => now()->toDateString(),
                'lines' => [[
                    'asset_category' => AssetCategory::Laptop->value,
                    'description' => 'Laptop',
                    'quantity' => 1,
                ]],
            ])
            ->assertSessionHasErrors('lines');

        $this->actingAs($officer)
            ->post(route('assets.store'), [
                'recipient_type' => 'staff',
                'staff_id' => $inactive->id,
                'issuance_type' => AssetIssuanceType::InitialKit->value,
                'issued_at' => now()->toDateString(),
                'lines' => [[
                    'asset_category' => AssetCategory::Uniform->value,
                    'description' => 'Shirt',
                    'quantity' => 1,
                ]],
            ])
            ->assertSessionHasErrors('lines');

        $this->actingAs($officer)
            ->post(route('assets.store'), [
                'recipient_type' => 'staff',
                'staff_id' => $staff->id,
                'issuance_type' => AssetIssuanceType::InitialKit->value,
                'issued_at' => now()->toDateString(),
                'lines' => [[
                    'asset_category' => AssetCategory::Laptop->value,
                    'description' => 'Laptop',
                    'serial_number' => 'CSL-IT-0042',
                    'quantity' => 1,
                ]],
            ])
            ->assertRedirect();

        $line = GuardAssetLine::query()->first();
        $line->update(['status' => AssetLineStatus::Lost]);

        $this->actingAs($officer)
            ->post(route('assets.store'), [
                'recipient_type' => 'staff',
                'staff_id' => $other->id,
                'issuance_type' => AssetIssuanceType::Replacement->value,
                'issued_at' => now()->toDateString(),
                'lines' => [[
                    'asset_category' => AssetCategory::Laptop->value,
                    'description' => 'Replacement laptop',
                    'serial_number' => 'CSL-IT-0042',
                    'quantity' => 1,
                ]],
            ])
            ->assertSessionHasErrors('lines');

        $this->assertSame(1, GuardAssetLine::query()->count());
    }

    public function test_procurement_cannot_edit_staff_records(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $staff = Staff::factory()->create();

        $this->actingAs($officer)
            ->get(route('staff.edit', $staff))
            ->assertForbidden();

        $this->actingAs($officer)
            ->put(route('staff.update', $staff), ['job_title' => 'Changed'])
            ->assertRedirect()
            ->assertSessionHas('error', 'You do not have permission to perform this action.');
    }

    public function test_partial_return_restores_only_the_returned_quantity(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $staff = Staff::factory()->create();
        $stock = ProcurementStockItem::query()->create([
            'reference' => 'STK-UNI-1',
            'name' => 'Shirt',
            'category' => AssetCategory::Uniform->value,
            'quantity_on_hand' => 5,
            'is_active' => true,
        ]);

        $this->actingAs($officer)->post(route('assets.store'), [
            'recipient_type' => 'staff',
            'staff_id' => $staff->id,
            'issuance_type' => AssetIssuanceType::InitialKit->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $stock->id,
                'asset_category' => AssetCategory::Uniform->value,
                'description' => 'Shirt',
                'quantity' => 3,
            ]],
        ])->assertRedirect();

        $issuance = GuardAssetIssuance::query()->first();
        $line = $issuance->lines()->first();

        $this->actingAs($officer)->post(route('assets.return', $issuance), [
            'lines' => [
                $line->id => ['return_qty' => 1, 'disposition' => 'returned'],
            ],
        ])->assertRedirect();

        $this->assertSame(3, $stock->fresh()->quantity_on_hand);
        $this->assertSame(1, (int) $line->fresh()->quantity_returned);
        $this->assertSame(AssetLineStatus::PartiallyReturned, $line->fresh()->status);
    }
}
