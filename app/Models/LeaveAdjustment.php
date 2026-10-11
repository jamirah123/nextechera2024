<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveAdjustment extends Model
{
    protected $fillable = [
        'guard_id',
        'staff_id',
        'leave_type_id',
        'year',
        'days',
        'reason',
        'adjusted_by',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'days' => 'decimal:1',
        ];
    }

    public function leaveTypeConfig(): BelongsTo
    {
        return $this->belongsTo(LeaveTypeConfig::class, 'leave_type_id');
    }

    public function adjuster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}
