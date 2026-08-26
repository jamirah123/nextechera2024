<?php

namespace App\Models;

use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShiftRecurrence extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'guard_id',
        'site_id',
        'region_id',
        'supervisor_id',
        'shift_type',
        'period',
        'start_time',
        'end_time',
        'days_of_week',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'shift_type' => ShiftType::class,
            'period' => ShiftPeriod::class,
            'days_of_week' => 'array',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class, 'recurrence_id');
    }
}
