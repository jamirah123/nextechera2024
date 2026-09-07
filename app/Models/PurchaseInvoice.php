<?php

namespace App\Models;

use App\Enums\PurchaseInvoiceStatus;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoice extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'reference',
        'supplier_name',
        'supplier_tin',
        'supplier_invoice_no',
        'bill_date',
        'due_date',
        'status',
        'currency',
        'subtotal',
        'tax_amount',
        'total',
        'expense_account_id',
        'description',
        'notes',
        'posted_at',
        'posted_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseInvoiceStatus::class,
            'bill_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'posted_at' => 'datetime',
        ];
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'expense_account_id');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function isEditable(): bool
    {
        return $this->status === PurchaseInvoiceStatus::Draft;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhere('supplier_name', 'like', $like)
                ->orWhere('supplier_invoice_no', 'like', $like)
                ->orWhere('supplier_tin', 'like', $like);
        });
    }
}
