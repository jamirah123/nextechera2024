<?php

namespace App\Models;

use App\Enums\GlJournalSource;
use App\Enums\GlJournalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GlJournal extends Model
{
    protected $fillable = [
        'reference',
        'journal_date',
        'period_id',
        'source',
        'source_document_type',
        'source_document_id',
        'description',
        'status',
        'currency',
        'posted_at',
        'posted_by',
        'voided_at',
        'voided_by',
        'reversal_of_id',
    ];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
            'source' => GlJournalSource::class,
            'status' => GlJournalStatus::class,
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(GlPeriod::class, 'period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GlJournalLine::class, 'journal_id')->orderBy('sort_order')->orderBy('id');
    }

    public function sourceDocument(): MorphTo
    {
        return $this->morphTo();
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_id');
    }

    public function debitTotal(): float
    {
        return round((float) $this->lines->sum('debit'), 2);
    }

    public function creditTotal(): float
    {
        return round((float) $this->lines->sum('credit'), 2);
    }

    public function isBalanced(): bool
    {
        return abs($this->debitTotal() - $this->creditTotal()) < 0.009;
    }

    public function scopePosted($query)
    {
        return $query->where('status', GlJournalStatus::Posted->value);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhere('description', 'like', $like);
        });
    }
}
