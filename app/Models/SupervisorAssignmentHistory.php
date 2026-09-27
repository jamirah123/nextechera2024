<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupervisorAssignmentHistory extends Model
{
    protected $fillable = [
        'supervisor_id',
        'previous_region_id',
        'new_region_id',
        'change_type',
        'reason',
        'notes',
        'meta',
        'changed_by',
        'effective_at',
        'starts_on',
        'ends_on',
        'status',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'effective_at' => 'datetime',
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    public function previousRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'previous_region_id');
    }

    public function newRegion(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'new_region_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
