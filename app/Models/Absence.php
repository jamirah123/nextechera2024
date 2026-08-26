<?php

namespace App\Models;

use App\Enums\AbsenceReason;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absence extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'guard_id',
        'site_id',
        'shift_id',
        'absence_date',
        'reason',
        'action_taken',
        'replacement_required',
        'replacement_guard_id',
        'notes',
        'reported_by',
        'reported_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'reason' => AbsenceReason::class,
            'absence_date' => 'date',
            'replacement_required' => 'boolean',
            'reported_at' => 'datetime',
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

    public function replacementGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'replacement_guard_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('action_taken', 'like', $like)
                ->orWhere('notes', 'like', $like)
                ->orWhereHas('assignedGuard', function ($guard) use ($like): void {
                    $guard->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                });
        });
    }
}
