<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GlJournalLine extends Model
{
    protected $fillable = [
        'journal_id',
        'account_id',
        'debit',
        'credit',
        'memo',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(GlJournal::class, 'journal_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'account_id');
    }
}
