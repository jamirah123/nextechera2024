<?php

namespace App\Models;

use App\Enums\AssetAcquisitionSource;
use App\Enums\AssetCategory;
use App\Enums\AssetCondition;
use App\Enums\AssetLineStatus;
use App\Enums\AssetOperationalStatus;
use App\Enums\AssetTrackingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProcurementStockItem extends Model
{
    protected $fillable = [
        'reference',
        'asset_tag',
        'serial_number',
        'name',
        'brand',
        'model',
        'specifications',
        'category',
        'tracking_type',
        'unit',
        'quantity_on_hand',
        'quantity_issued',
        'quantity_lost',
        'quantity_damaged',
        'reorder_level',
        'unit_cost',
        'acquisition_date',
        'acquisition_source',
        'purchase_reference',
        'warranty_starts_on',
        'warranty_ends_on',
        'condition',
        'operational_status',
        'location',
        'region_id',
        'site_id',
        'supplier_id',
        'is_active',
        'notes',
        'attachment_path',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => AssetCategory::class,
            'tracking_type' => AssetTrackingType::class,
            'acquisition_source' => AssetAcquisitionSource::class,
            'condition' => AssetCondition::class,
            'operational_status' => AssetOperationalStatus::class,
            'quantity_on_hand' => 'integer',
            'quantity_issued' => 'integer',
            'quantity_lost' => 'integer',
            'quantity_damaged' => 'integer',
            'reorder_level' => 'integer',
            'unit_cost' => 'decimal:2',
            'is_active' => 'boolean',
            'acquisition_date' => 'date',
            'warranty_starts_on' => 'date',
            'warranty_ends_on' => 'date',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GuardAssetLine::class, 'stock_item_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(ProcurementStockMovement::class, 'stock_item_id')->latest('id');
    }

    public function isIndividual(): bool
    {
        return $this->tracking_type === AssetTrackingType::Individual;
    }

    public function isIssuable(): bool
    {
        return $this->is_active
            && $this->operational_status === AssetOperationalStatus::Available
            && $this->quantity_on_hand > 0;
    }

    public function isLow(): bool
    {
        return $this->is_active
            && ! $this->isIndividual()
            && $this->quantity_on_hand <= $this->reorder_level;
    }

    /** @return list<string> */
    public function missingPurchaseDetails(): array
    {
        if ($this->acquisition_source !== AssetAcquisitionSource::Purchase) {
            return [];
        }

        $missing = [];

        if ($this->supplier_id === null) {
            $missing[] = 'Supplier';
        }

        if (blank($this->purchase_reference)) {
            $missing[] = 'Purchase reference';
        }

        if ($this->acquisition_date === null) {
            $missing[] = 'Acquisition date';
        }

        return $missing;
    }

    public function openLines(): HasMany
    {
        return $this->lines()
            ->whereNotIn('status', [
                AssetLineStatus::Returned->value,
                AssetLineStatus::Transferred->value,
                AssetLineStatus::WrittenOff->value,
                AssetLineStatus::Lost->value,
            ])
            ->whereColumn('quantity_returned', '<', 'quantity');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($inner) use ($like): void {
            $inner->where('reference', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('asset_tag', 'like', $like)
                ->orWhere('serial_number', 'like', $like)
                ->orWhere('brand', 'like', $like)
                ->orWhere('model', 'like', $like)
                ->orWhere('location', 'like', $like);
        });
    }
}
