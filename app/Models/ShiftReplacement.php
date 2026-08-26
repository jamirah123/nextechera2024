<?php

namespace App\Models;

use App\Enums\ReplacementReason;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftReplacement extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'original_shift_id',
        'original_guard_id',
        'replacement_guard_id',
        'replacement_shift_id',
        'site_id',
        'reason',
        'notes',
        'authorized_by',
        'replaced_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'reason' => ReplacementReason::class,
            'replaced_at' => 'datetime',
        ];
    }

    public function originalShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'original_shift_id');
    }

    public function replacementShift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'replacement_shift_id');
    }

    public function originalGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'original_guard_id');
    }

    public function replacementGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'replacement_guard_id');
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function authorizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('notes', 'like', $like)
                ->orWhereHas('originalGuard', function ($guard) use ($like): void {
                    $guard->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                })
                ->orWhereHas('replacementGuard', function ($guard) use ($like): void {
                    $guard->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                })
                ->orWhereHas('originalShift', function ($shift) use ($like): void {
                    $shift->where('reference', 'like', $like);
                })
                ->orWhereHas('site', function ($site) use ($like): void {
                    $site->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
        });
    }
}
