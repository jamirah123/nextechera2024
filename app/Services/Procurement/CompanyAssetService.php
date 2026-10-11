<?php

namespace App\Services\Procurement;

use App\Enums\AssetAcquisitionSource;
use App\Enums\AssetCategory;
use App\Enums\AssetCondition;
use App\Enums\AssetOperationalStatus;
use App\Enums\AssetTrackingType;
use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Models\GuardAssetLine;
use App\Models\ProcurementStockItem;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CompanyAssetService
{
    public function __construct(
        private AuditService $audit,
        private StockLedger $ledger,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $actor, ?UploadedFile $file = null): ProcurementStockItem
    {
        $tracking = AssetTrackingType::from((string) $data['tracking_type']);
        $status = AssetOperationalStatus::from((string) ($data['operational_status'] ?? AssetOperationalStatus::Available->value));
        $tag = filled($data['asset_tag'] ?? null) ? trim((string) $data['asset_tag']) : null;
        $serial = filled($data['serial_number'] ?? null) ? trim((string) $data['serial_number']) : null;

        $this->assertIdentifiersAvailable($tag, $serial);

        if ($tracking === AssetTrackingType::Individual && $status === AssetOperationalStatus::Assigned) {
            throw new InvalidArgumentException('Register the asset first. Assignment happens when it is issued.');
        }

        $quantity = $tracking === AssetTrackingType::Individual
            ? ($status === AssetOperationalStatus::Available ? 1 : 0)
            : max(0, (int) ($data['quantity_on_hand'] ?? 0));

        return DB::transaction(function () use ($data, $actor, $file, $tracking, $status, $tag, $serial, $quantity): ProcurementStockItem {
            $item = ProcurementStockItem::query()->create([
                'reference' => $this->nextReference(),
                'asset_tag' => $tag,
                'serial_number' => $serial,
                'name' => trim((string) $data['name']),
                'brand' => $this->nullableString($data['brand'] ?? null),
                'model' => $this->nullableString($data['model'] ?? null),
                'specifications' => $this->nullableString($data['specifications'] ?? null),
                'category' => $data['category'],
                'tracking_type' => $tracking,
                'unit' => $this->nullableString($data['unit'] ?? null) ?? 'pcs',
                'quantity_on_hand' => $quantity,
                'quantity_issued' => 0,
                'quantity_lost' => 0,
                'quantity_damaged' => 0,
                'reorder_level' => $tracking === AssetTrackingType::Individual ? 0 : max(0, (int) ($data['reorder_level'] ?? 0)),
                'unit_cost' => $data['unit_cost'] ?? null,
                'acquisition_date' => $data['acquisition_date'] ?? null,
                'acquisition_source' => $data['acquisition_source'] ?? AssetAcquisitionSource::OpeningBalance->value,
                'purchase_reference' => $this->nullableString($data['purchase_reference'] ?? null),
                'warranty_starts_on' => $data['warranty_starts_on'] ?? null,
                'warranty_ends_on' => $data['warranty_ends_on'] ?? null,
                'condition' => $data['condition'] ?? AssetCondition::Good->value,
                'operational_status' => $status,
                'location' => $this->nullableString($data['location'] ?? null),
                'region_id' => $data['region_id'] ?? null,
                'site_id' => $data['site_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'is_active' => true,
                'notes' => $this->nullableString($data['notes'] ?? null),
                'attachment_path' => $file?->store('procurement/assets', 'local'),
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            if ($tracking === AssetTrackingType::Individual && $tag === null) {
                $item->update(['asset_tag' => 'CSL-'.str_pad((string) $item->id, 4, '0', STR_PAD_LEFT)]);
            }

            $this->ledger->record(
                $item->fresh(),
                'opening',
                $quantity,
                'in',
                'Registered as '.$item->acquisition_source->label(),
                null,
                $actor,
            );

            $this->audit->log(
                action: 'asset.registered',
                summary: 'Registered '.$item->name.' ('.$item->reference.')',
                category: AuditCategory::Hr,
                severity: AuditSeverity::Info,
                subject: $item,
                context: [
                    'tracking_type' => $tracking->value,
                    'operational_status' => $status->value,
                    'quantity' => $quantity,
                ],
                actor: $actor,
            );

            return $item->fresh();
        });
    }

    public function changeStatus(ProcurementStockItem $item, AssetOperationalStatus $status, string $reason, User $actor): ProcurementStockItem
    {
        if ($item->operational_status === AssetOperationalStatus::Assigned && $status !== AssetOperationalStatus::Assigned) {
            if ($item->openLines()->exists()) {
                throw new InvalidArgumentException('This asset is still assigned. Record a return or transfer before changing its status.');
            }
        }

        if ($status === AssetOperationalStatus::Assigned) {
            throw new InvalidArgumentException('Assignment is recorded by issuing the asset.');
        }

        if ($status === AssetOperationalStatus::Available && $item->openLines()->exists()) {
            throw new InvalidArgumentException('Close the current assignment before marking this asset available.');
        }

        return DB::transaction(function () use ($item, $status, $reason, $actor): ProcurementStockItem {
            $locked = ProcurementStockItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $before = $locked->operational_status?->value;
            $quantity = $locked->isIndividual()
                ? ($status === AssetOperationalStatus::Available ? 1 : 0)
                : (int) $locked->quantity_on_hand;

            $locked->update([
                'operational_status' => $status,
                'quantity_on_hand' => $quantity,
                'is_active' => $status !== AssetOperationalStatus::Retired,
                'updated_by' => $actor->id,
            ]);

            $this->ledger->record($locked->fresh(), 'status', $quantity, 'in', $reason, null, $actor);

            $this->audit->log(
                action: 'asset.status_changed',
                summary: $locked->name.' marked '.$status->label(),
                category: AuditCategory::Hr,
                severity: AuditSeverity::Info,
                subject: $locked,
                context: ['from' => $before, 'to' => $status->value, 'reason' => $reason],
                actor: $actor,
            );

            return $locked->fresh();
        });
    }

    public function receiveQuantity(ProcurementStockItem $item, int $quantity, string $reason, User $actor): ProcurementStockItem
    {
        if ($item->isIndividual()) {
            throw new InvalidArgumentException('Add a new asset record for another individually tracked item.');
        }

        if ($quantity < 1) {
            throw new InvalidArgumentException('Enter a quantity to add.');
        }

        return DB::transaction(function () use ($item, $quantity, $reason, $actor): ProcurementStockItem {
            $locked = ProcurementStockItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->increment('quantity_on_hand', $quantity);
            $locked->update(['updated_by' => $actor->id]);
            $fresh = $locked->fresh();
            $this->ledger->record($fresh, 'receipt', $quantity, 'in', $reason, null, $actor);

            $this->audit->log(
                action: 'asset.quantity_added',
                summary: 'Added '.$quantity.' to '.$fresh->name,
                category: AuditCategory::Hr,
                severity: AuditSeverity::Info,
                subject: $fresh,
                context: ['quantity' => $quantity, 'balance' => $fresh->quantity_on_hand, 'reason' => $reason],
                actor: $actor,
            );

            return $fresh;
        });
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{created: int, errors: list<string>}
     */
    public function import(array $rows, User $actor): array
    {
        $errors = [];
        $prepared = [];
        $seenTags = [];
        $seenSerials = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $name = trim((string) ($row['name'] ?? ''));
            $category = trim((string) ($row['category'] ?? ''));

            if ($name === '' || AssetCategory::tryFrom($category) === null) {
                $errors[] = 'Row '.$line.' needs a name and a valid category.';

                continue;
            }

            $tracking = AssetTrackingType::tryFrom(trim((string) ($row['tracking_type'] ?? 'quantity'))) ?? AssetTrackingType::Quantity;
            $tag = trim((string) ($row['asset_tag'] ?? ''));
            $serial = trim((string) ($row['serial_number'] ?? ''));

            if ($tag !== '') {
                if (isset($seenTags[$tag]) || ProcurementStockItem::query()->where('asset_tag', $tag)->exists()) {
                    $errors[] = 'Row '.$line.' repeats asset tag '.$tag.'.';
                }
                $seenTags[$tag] = true;
            }

            if ($serial !== '') {
                if (isset($seenSerials[$serial]) || ProcurementStockItem::query()->where('serial_number', $serial)->exists()) {
                    $errors[] = 'Row '.$line.' repeats serial '.$serial.'.';
                }
                $seenSerials[$serial] = true;
            }

            $prepared[] = [
                'name' => $name,
                'category' => $category,
                'tracking_type' => $tracking->value,
                'asset_tag' => $tag !== '' ? $tag : null,
                'serial_number' => $serial !== '' ? $serial : null,
                'quantity_on_hand' => max(0, (int) ($row['quantity'] ?? ($tracking === AssetTrackingType::Individual ? 1 : 0))),
                'unit' => trim((string) ($row['unit'] ?? 'pcs')) ?: 'pcs',
                'location' => trim((string) ($row['location'] ?? '')) ?: null,
                'condition' => AssetCondition::tryFrom(trim((string) ($row['condition'] ?? 'good')))?->value ?? AssetCondition::Good->value,
                'acquisition_source' => AssetAcquisitionSource::tryFrom(trim((string) ($row['acquisition_source'] ?? 'opening_balance')))?->value ?? AssetAcquisitionSource::OpeningBalance->value,
                'acquisition_date' => trim((string) ($row['acquisition_date'] ?? '')) ?: null,
                'operational_status' => AssetOperationalStatus::Available->value,
                'reorder_level' => 0,
            ];
        }

        if ($errors !== []) {
            return ['created' => 0, 'errors' => $errors];
        }

        $created = 0;

        DB::transaction(function () use ($prepared, $actor, &$created): void {
            foreach ($prepared as $row) {
                $this->register($row, $actor);
                $created++;
            }
        });

        return ['created' => $created, 'errors' => []];
    }

    private function assertIdentifiersAvailable(?string $tag, ?string $serial): void
    {
        if ($tag !== null && ProcurementStockItem::query()->where('asset_tag', $tag)->exists()) {
            throw new InvalidArgumentException('Asset tag '.$tag.' is already registered.');
        }

        if ($serial === null || $serial === '') {
            return;
        }

        if (ProcurementStockItem::query()->where('serial_number', $serial)->exists()) {
            throw new InvalidArgumentException('Serial '.$serial.' is already registered.');
        }

        $open = GuardAssetLine::query()
            ->where('serial_number', $serial)
            ->whereNotIn('status', ['returned', 'transferred'])
            ->exists();

        if ($open) {
            throw new InvalidArgumentException('Serial '.$serial.' is already on an open assignment.');
        }
    }

    private function nextReference(): string
    {
        $latest = ProcurementStockItem::query()->lockForUpdate()->orderByDesc('id')->value('reference');
        $sequence = 1;

        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return 'STK-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
