<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePromotion extends Model
{
    protected $fillable = [
        'guard_id',
        'staff_id',
        'supervisor_id',
        'previous_position_id',
        'position_id',
        'previous_position',
        'previous_salary',
        'new_salary',
        'effective_from',
        'effective_to',
        'reason',
        'reference',
        'document_path',
        'remarks',
        'region_id',
        'status',
        'applied_at',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_salary' => 'decimal:2',
            'new_salary' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    public function previousPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'previous_position_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCurrent(?\DateTimeInterface $on = null): bool
    {
        $day = \Carbon\Carbon::parse($on ?? now())->startOfDay();

        return $this->status === 'applied'
            && $this->effective_from->copy()->startOfDay()->lessThanOrEqualTo($day)
            && ($this->effective_to === null || $this->effective_to->copy()->startOfDay()->greaterThanOrEqualTo($day));
    }
}
