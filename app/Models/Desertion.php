<?php

namespace App\Models;

use App\Enums\DesertionHrStatus;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Desertion extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'guard_id',
        'last_known_duty_date',
        'last_known_site_id',
        'date_reported',
        'circumstances',
        'action_taken',
        'hr_status',
        'notes',
        'reported_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hr_status' => DesertionHrStatus::class,
            'last_known_duty_date' => 'date',
            'date_reported' => 'date',
        ];
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function lastKnownSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'last_known_site_id');
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
            $q->where('circumstances', 'like', $like)
                ->orWhere('action_taken', 'like', $like)
                ->orWhereHas('assignedGuard', function ($guard) use ($like): void {
                    $guard->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                });
        });
    }
}
