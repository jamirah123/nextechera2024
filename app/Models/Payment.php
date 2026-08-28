<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'reference',
        'invoice_id',
        'client_id',
        'amount',
        'payment_date',
        'method',
        'external_reference',
        'notes',
        'recorded_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'method' => PaymentMethod::class,
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhere('external_reference', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like))
                ->orWhereHas('invoice', fn ($i) => $i->where('reference', 'like', $like));
        });
    }
}
