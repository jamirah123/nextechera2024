<?php

namespace App\Models;

use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Concerns\TracksUserChanges;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory, TracksUserChanges;

    protected $fillable = [
        'reference',
        'guard_id',
        'site_id',
        'region_id',
        'supervisor_id',
        'deployment_id',
        'recurrence_id',
        'replaced_shift_id',
        'shift_date',
        'starts_at',
        'ends_at',
        'period',
        'shift_type',
        'status',
        'is_overnight',
        'notes',
        'override_used',
        'override_reason',
        'override_by',
        'override_at',
        'validation_snapshot',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'period' => ShiftPeriod::class,
            'shift_type' => ShiftType::class,
            'status' => ShiftStatus::class,
            'is_overnight' => 'boolean',
            'override_used' => 'boolean',
            'override_at' => 'datetime',
            'validation_snapshot' => 'array',
            'approved_at' => 'datetime',
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

    public function deployment(): BelongsTo
    {
        return $this->belongsTo(Deployment::class);
    }

    public function recurrence(): BelongsTo
    {
        return $this->belongsTo(ShiftRecurrence::class, 'recurrence_id');
    }

    public function replacedShift(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_shift_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replaced_shift_id');
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isActiveSlot(): bool
    {
        return $this->status->blocksCalendarSlot();
    }

    public function timeLabel(): string
    {
        return $this->starts_at->format('H:i').'–'.$this->ends_at->format('H:i');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhereHas('assignedGuard', function ($guard) use ($like): void {
                    $guard->where('employment_id', 'like', $like)
                        ->orWhere('full_name', 'like', $like);
                })
                ->orWhereHas('site', function ($site) use ($like): void {
                    $site->where('name', 'like', $like)
                        ->orWhere('code', 'like', $like);
                });
        });
    }

    public function scopeForDate($query, string $date)
    {
        return $query->whereDate('shift_date', $date);
    }

    public function scopeBlocking($query)
    {
        return $query->whereIn('status', [
            ShiftStatus::Scheduled->value,
            ShiftStatus::Confirmed->value,
            ShiftStatus::InProgress->value,
            ShiftStatus::Completed->value,
        ]);
    }
}
