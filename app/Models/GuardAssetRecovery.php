<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GuardAssetRecovery extends Model
{
    protected $fillable = [
        'guard_id',
        'guard_asset_line_id',
        'label',
        'original_amount',
        'balance_remaining',
        'monthly_installment',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'original_amount' => 'decimal:2',
            'balance_remaining' => 'decimal:2',
            'monthly_installment' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(GuardAssetLine::class, 'guard_asset_line_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payrollDeductions(): HasMany
    {
        return $this->hasMany(PayrollDeduction::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('balance_remaining', '>', 0);
    }
}
