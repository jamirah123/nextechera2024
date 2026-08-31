<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes, TracksUserChanges;
    use \App\Models\Concerns\CapturesDeletionSnapshot;

    protected $fillable = [
        'reference',
        'client_id',
        'site_id',
        'status',
        'period_start',
        'period_end',
        'issue_date',
        'due_date',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'amount_paid',
        'balance',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'issue_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isEditable(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like));
        });
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            InvoiceStatus::Issued->value,
            InvoiceStatus::PartiallyPaid->value,
            InvoiceStatus::Overdue->value,
        ]);
    }
}
