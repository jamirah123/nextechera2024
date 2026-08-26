<?php

namespace App\Models;

use App\Enums\AttendanceEventType;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'guard_id',
        'site_id',
        'shift_id',
        'event_type',
        'source',
        'occurred_at',
        'latitude',
        'longitude',
        'device_ref',
        'notes',
        'meta',
        'recorded_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => AttendanceEventType::class,
            'occurred_at' => 'datetime',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'meta' => 'array',
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

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('notes', 'like', $like)
                ->orWhere('device_ref', 'like', $like)
                ->orWhereHas('assignedGuard', function ($guard) use ($like): void {
                    $guard->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                });
        });
    }
}
