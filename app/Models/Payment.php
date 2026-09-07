<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'reference',
        'payroll_run_id',
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

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function matchedStatementLines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class, 'matched_payment_id');
    }

    public function isDisbursement(): bool
    {
        return $this->payroll_run_id !== null;
    }

    public function isCollection(): bool
    {
        return $this->invoice_id !== null;
    }

    public function scopeCollections($query)
    {
        return $query->whereNotNull('invoice_id');
    }

    public function scopeDisbursements($query)
    {
        return $query->whereNotNull('payroll_run_id');
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
                ->orWhereHas('invoice', fn ($i) => $i->where('reference', 'like', $like))
                ->orWhereHas('payrollRun', fn ($r) => $r->where('reference', 'like', $like));
        });
    }
}
