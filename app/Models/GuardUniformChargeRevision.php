<?php

namespace App\Models;

use App\Enums\UniformChargeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardUniformChargeRevision extends Model
{
    protected $fillable = [
        'guard_id',
        'status',
        'effective_from',
        'effective_to',
        'reason',
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => UniformChargeStatus::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function covers(\DateTimeInterface $day): bool
    {
        $date = \Carbon\Carbon::parse($day)->startOfDay();

        return $this->effective_from->copy()->startOfDay()->lessThanOrEqualTo($date)
            && ($this->effective_to === null || $this->effective_to->copy()->startOfDay()->greaterThanOrEqualTo($date));
    }
}
