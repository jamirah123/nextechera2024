<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveEntitlement extends Model
{
    protected $fillable = [
        'guard_id',
        'staff_id',
        'leave_type_id',
        'year',
        'opening_balance',
        'accrued',
        'taken',
        'pending',
        'carried_forward',
        'adjustments',
        'expired',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'opening_balance' => 'decimal:1',
            'accrued' => 'decimal:1',
            'taken' => 'decimal:1',
            'pending' => 'decimal:1',
            'carried_forward' => 'decimal:1',
            'adjustments' => 'decimal:1',
            'expired' => 'decimal:1',
        ];
    }

    public function leaveTypeConfig(): BelongsTo
    {
        return $this->belongsTo(LeaveTypeConfig::class, 'leave_type_id');
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function remaining(): float
    {
        return round(
            (float) $this->opening_balance
            + (float) $this->accrued
            + (float) $this->carried_forward
            + (float) $this->adjustments
            - (float) $this->taken
            - (float) $this->pending
            - (float) $this->expired,
            1,
        );
    }
}
