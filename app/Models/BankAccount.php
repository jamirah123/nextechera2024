<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = [
        'gl_account_id',
        'name',
        'bank_name',
        'account_number',
        'currency',
        'is_active',
        'opening_balance',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'opening_balance' => 'decimal:2',
        ];
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id');
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->latest('transaction_date')->latest('id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        $bits = array_filter([$this->name, $this->bank_name, $this->account_number]);

        return implode(' · ', $bits);
    }
}
