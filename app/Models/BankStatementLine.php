<?php

namespace App\Models;

use App\Enums\BankStatementLineStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankStatementLine extends Model
{
    protected $fillable = [
        'bank_account_id',
        'transaction_date',
        'description',
        'external_reference',
        'amount',
        'status',
        'matched_payment_id',
        'matched_journal_id',
        'matched_at',
        'matched_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'status' => BankStatementLineStatus::class,
            'matched_at' => 'datetime',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    public function matchedJournal(): BelongsTo
    {
        return $this->belongsTo(GlJournal::class, 'matched_journal_id');
    }

    public function matcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function scopeUnmatched($query)
    {
        return $query->where('status', BankStatementLineStatus::Unmatched->value);
    }
}
