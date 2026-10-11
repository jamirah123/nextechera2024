<?php

namespace Tests\Feature\Procurement;

use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceType;
use App\Enums\AssetLineStatus;
use App\Enums\AssetOperationalStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\GuardAssetLine;
use App\Models\ProcurementStockItem;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CompanyAssetRegisterTest extends TestCase
{
    use RefreshDatabase;

    public function test_asset_can_be_registered_without_a_worker_and_then_issued(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $staff = Staff::factory()->create();

        $this->actingAs($officer)->post(route('procurement.stock.store'), $this->laptop('CSL-IT-0042', 'SN-42'))
            ->assertRedirect();

        $item = ProcurementStockItem::query()->where('asset_tag', 'CSL-IT-0042')->first();
        $this->assertNotNull($item);
        $this->assertSame('individual', $item->tracking_type?->value);
        $this->assertSame(AssetOperationalStatus::Available, $item->operational_status);
        $this->assertSame(1, $item->quantity_on_hand);
        $this->assertSame(0, GuardAssetIssuance::query()->count());
        $this->assertTrue(AuditLog::query()->where('action', 'asset.registered')->exists());

        $this->actingAs($officer)->get(route('procurement.stock.index'))
            ->assertOk()
            ->assertSee('CSL-IT-0042');

        $this->actingAs($officer)->post(route('assets.store'), [
            'recipient_type' => 'staff',
            'staff_id' => $staff->id,
            'issuance_type' => AssetIssuanceType::InitialKit->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $item->id,
                'asset_category' => AssetCategory::Laptop->value,
                'quantity' => 1,
            ]],
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(AssetOperationalStatus::Assigned, $item->operational_status);
        $this->assertSame(0, $item->quantity_on_hand);
        $this->assertSame('SN-42', GuardAssetLine::query()->first()->serial_number);

        $this->actingAs($officer)->post(route('assets.store'), [
            'recipient_type' => 'staff',
            'staff_id' => $staff->id,
            'issuance_type' => AssetIssuanceType::Replacement->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $item->id,
                'asset_category' => AssetCategory::Laptop->value,
                'serial_number' => 'SN-42',
                'quantity' => 1,
            ]],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(1, GuardAssetLine::query()->count());
    }

    public function test_repair_lost_retired_and_duplicate_identifiers_are_blocked(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $guard = Guard::factory()->create();

        $this->actingAs($officer)->post(route('procurement.stock.store'), $this->laptop('CSL-IT-0001', 'SN-1', AssetOperationalStatus::Repair->value))
            ->assertRedirect();

        $repair = ProcurementStockItem::query()->where('asset_tag', 'CSL-IT-0001')->first();

        $this->actingAs($officer)->post(route('assets.store'), [
            'recipient_type' => 'guard',
            'guard_id' => $guard->id,
            'issuance_type' => AssetIssuanceType::InitialKit->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $repair->id,
                'asset_category' => AssetCategory::Laptop->value,
                'serial_number' => 'SN-1',
                'quantity' => 1,
            ]],
        ])->assertSessionHasErrors('lines');

        $this->actingAs($officer)->post(route('procurement.stock.store'), $this->laptop('CSL-IT-0001', 'SN-2'))
            ->assertSessionHasErrors('asset_tag');

        $this->actingAs($officer)->post(route('procurement.stock.store'), $this->laptop('CSL-IT-0002', 'SN-1'))
            ->assertSessionHasErrors('asset_tag');

        $this->actingAs($officer)->post(route('procurement.stock.store'), $this->laptop('CSL-IT-0003', 'SN-3'))
            ->assertRedirect();

        $lost = ProcurementStockItem::query()->where('asset_tag', 'CSL-IT-0003')->first();
        $this->actingAs($officer)->post(route('procurement.stock.status', $lost), [
            'operational_status' => AssetOperationalStatus::Lost->value,
            'reason' => 'Cannot be found in the store.',
        ])->assertRedirect();

        $this->actingAs($officer)->post(route('assets.store'), [
            'guard_id' => $guard->id,
            'issuance_type' => AssetIssuanceType::InitialKit->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $lost->id,
                'asset_category' => AssetCategory::Laptop->value,
                'serial_number' => 'SN-3',
                'quantity' => 1,
            ]],
        ])->assertSessionHasErrors('lines');

        $this->assertSame(0, GuardAssetLine::query()->count());
    }

    public function test_transfer_keeps_both_assignments_and_quantity_stock_reconciles(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $guard = Guard::factory()->create();
        $staff = Staff::factory()->create();

        $this->actingAs($officer)->post(route('procurement.stock.store'), $this->laptop('CSL-IT-0099', 'SN-99'))
            ->assertRedirect();

        $laptop = ProcurementStockItem::query()->where('asset_tag', 'CSL-IT-0099')->first();

        $this->actingAs($officer)->post(route('assets.store'), [
            'recipient_type' => 'staff',
            'staff_id' => $staff->id,
            'issuance_type' => AssetIssuanceType::InitialKit->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $laptop->id,
                'asset_category' => AssetCategory::Laptop->value,
                'quantity' => 1,
            ]],
        ])->assertRedirect();

        $this->actingAs($officer)->post(route('procurement.stock.transfer', $laptop), [
            'recipient_type' => 'guard',
            'guard_id' => $guard->id,
            'staff_id' => $staff->id,
            'reason' => 'Staff member returned to the field as a guard posting.',
        ])->assertRedirect();

        $lines = GuardAssetLine::query()->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $this->assertSame(AssetLineStatus::Transferred, $lines[0]->status);
        $this->assertSame(AssetLineStatus::Issued, $lines[1]->status);
        $this->assertSame($lines[0]->id, $lines[1]->transferred_from_line_id);
        $this->assertSame($guard->id, $lines[1]->issuance->guard_id);
        $this->assertSame(AssetOperationalStatus::Assigned, $laptop->fresh()->operational_status);

        $this->actingAs($officer)->post(route('procurement.stock.store'), [
            'name' => 'Shirts',
            'category' => AssetCategory::Uniform->value,
            'tracking_type' => 'quantity',
            'quantity_on_hand' => 10,
            'unit' => 'pcs',
            'condition' => 'good',
            'operational_status' => 'available',
            'acquisition_source' => 'opening_balance',
        ])->assertRedirect();

        $shirts = ProcurementStockItem::query()->where('name', 'Shirts')->first();

        $this->actingAs($officer)->post(route('procurement.stock.receive', $shirts), [
            'quantity' => 5,
            'reason' => 'Opening count correction.',
        ])->assertRedirect();

        $this->assertSame(15, $shirts->fresh()->quantity_on_hand);

        $this->actingAs($officer)->post(route('assets.store'), [
            'guard_id' => $guard->id,
            'issuance_type' => AssetIssuanceType::InitialKit->value,
            'issued_at' => now()->toDateString(),
            'lines' => [[
                'stock_item_id' => $shirts->id,
                'asset_category' => AssetCategory::Uniform->value,
                'quantity' => 4,
            ]],
        ])->assertRedirect();

        $this->assertSame(11, $shirts->fresh()->quantity_on_hand);

        $shirtLine = GuardAssetLine::query()->where('stock_item_id', $shirts->id)->first();
        $this->actingAs($officer)->post(route('assets.return', $shirtLine->issuance), [
            'lines' => [$shirtLine->id => ['return_qty' => 1, 'disposition' => 'returned']],
        ])->assertRedirect();

        $this->assertSame(12, $shirts->fresh()->quantity_on_hand);
        $this->assertSame(3, $shirts->fresh()->quantity_issued);
    }

    public function test_shift_manager_cannot_register_assets_and_procurement_cannot_store_cost(): void
    {
        $scheduler = User::factory()->role(UserRole::ShiftManager)->create();
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();

        $this->actingAs($scheduler)
            ->post(route('procurement.stock.store'), $this->laptop('CSL-IT-0008', 'SN-8'))
            ->assertRedirect()
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->assertSame(0, ProcurementStockItem::query()->count());

        $this->actingAs($officer)->post(route('procurement.stock.store'), $this->laptop('CSL-IT-0008', 'SN-8') + ['unit_cost' => 1500000])
            ->assertRedirect();

        $this->assertNull(ProcurementStockItem::query()->where('asset_tag', 'CSL-IT-0008')->first()->unit_cost);
    }

    public function test_csv_import_rejects_duplicate_tags_without_partial_rows(): void
    {
        $officer = User::factory()->role(UserRole::ProcurementOfficer)->create();
        $csv = "name,category,tracking_type,asset_tag,serial_number,quantity,unit,location,condition,acquisition_source,acquisition_date\n"
            ."Radio,radio,individual,CSL-RD-1,RAD-1,1,pcs,Store,good,opening_balance,2026-10-01\n"
            ."Radio two,radio,individual,CSL-RD-1,RAD-2,1,pcs,Store,good,opening_balance,2026-10-01\n";

        $this->actingAs($officer)->post(route('procurement.stock.import'), [
            'file' => UploadedFile::fake()->createWithContent('assets.csv', $csv),
        ])->assertSessionHasErrors('file');

        $this->assertSame(0, ProcurementStockItem::query()->count());
    }

    /** @return array<string, string> */
    private function laptop(string $tag, string $serial, string $status = 'available'): array
    {
        return [
            'name' => 'Company laptop',
            'category' => AssetCategory::Laptop->value,
            'tracking_type' => 'individual',
            'asset_tag' => $tag,
            'serial_number' => $serial,
            'condition' => 'good',
            'operational_status' => $status,
            'acquisition_source' => 'opening_balance',
            'unit' => 'pcs',
        ];
    }
}
