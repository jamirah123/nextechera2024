<?php

namespace App\Models;

use App\Enums\PayrollRunStatus;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollRun extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'reference',
        'period_year',
        'period_month',
        'period_start',
        'period_end',
        'region_id',
        'site_id',
        'status',
        'currency',
        'guard_count',
        'gross_total',
        'deductions_total',
        'net_total',
        'notes',
        'calculated_at',
        'submitted_at',
        'approved_at',
        'paid_at',
        'created_by',
        'updated_by',
        'submitted_by',
        'approved_by',
        'paid_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayrollRunStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'gross_total' => 'decimal:2',
            'deductions_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'calculated_at' => 'datetime',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(PayrollPayslip::class)->orderBy('employment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function periodLabel(): string
    {
        return sprintf('%04d-%02d', $this->period_year, $this->period_month);
    }

    public function payslipCount(): int
    {
        return (int) $this->guard_count;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhere('notes', 'like', $like);
        });
    }
}
