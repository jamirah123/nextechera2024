<?php

namespace App\Models;

use App\Enums\AssetCategory;
use App\Enums\AssetLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GuardAssetLine extends Model
{
    protected $fillable = [
        'guard_asset_issuance_id',
        'asset_category',
        'description',
        'size',
        'serial_number',
        'quantity',
        'quantity_returned',
        'unit_value',
        'recovery_amount',
        'recovered_amount',
        'monthly_recovery',
        'status',
        'returned_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'asset_category' => AssetCategory::class,
            'status' => AssetLineStatus::class,
            'quantity' => 'integer',
            'quantity_returned' => 'integer',
            'unit_value' => 'decimal:2',
            'recovery_amount' => 'decimal:2',
            'recovered_amount' => 'decimal:2',
            'monthly_recovery' => 'decimal:2',
            'returned_at' => 'datetime',
        ];
    }

    public function issuance(): BelongsTo
    {
        return $this->belongsTo(GuardAssetIssuance::class, 'guard_asset_issuance_id');
    }

    public function recovery(): HasOne
    {
        return $this->hasOne(GuardAssetRecovery::class);
    }

    public function quantityOutstanding(): int
    {
        return max(0, (int) $this->quantity - (int) $this->quantity_returned);
    }

    public function recoveryBalance(): float
    {
        return max(0, round((float) $this->recovery_amount - (float) $this->recovered_amount, 2));
    }

    public function displayLabel(): string
    {
        $parts = [$this->asset_category->label()];

        if (filled($this->description)) {
            $parts[] = $this->description;
        }

        if (filled($this->size)) {
            $parts[] = 'Size '.$this->size;
        }

        if (filled($this->serial_number)) {
            $parts[] = 'S/N '.$this->serial_number;
        }

        return implode(' · ', $parts);
    }
}
