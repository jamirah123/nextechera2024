<?php

namespace App\Models;

use App\Enums\GuardClassification;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Concerns\TracksUserChanges;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'leave_id',
        'recurrence_id',
        'replaced_shift_id',
        'shift_date',
        'starts_at',
        'ends_at',
        'period',
        'shift_type',
        'guard_classification',
        'status',
        'same_shift_slot',
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

    protected static function booted(): void
    {
        static::saving(function (Shift $shift): void {
            $shift->same_shift_slot = $shift->resolveSameShiftSlot();
        });
    }

    public function resolveSameShiftSlot(): ?string
    {
        if (! $this->status instanceof ShiftStatus || ! $this->status->blocksCalendarSlot()) {
            return null;
        }

        if ($this->guard_id === null || $this->period === null || $this->shift_date === null) {
            return null;
        }

        $date = $this->shift_date instanceof CarbonInterface
            ? $this->shift_date->toDateString()
            : (string) $this->shift_date;

        $period = $this->period instanceof ShiftPeriod
            ? $this->period->value
            : (string) $this->period;

        return $this->guard_id.'|'.$date.'|'.$period;
    }

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'period' => ShiftPeriod::class,
            'shift_type' => ShiftType::class,
            'guard_classification' => GuardClassification::class,
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

    public function replacementRecord(): HasOne
    {
        return $this->hasOne(ShiftReplacement::class, 'original_shift_id');
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

    public function scopeForDate($query, ?string $date = null)
    {
        return $query->betweenDates($date ?: now()->toDateString(), $date ?: now()->toDateString());
    }

    /**
     * Inclusive calendar range that can use the shift_date index.
     * whereDate() wraps the column in DATE() and forces a scan of the duty history.
     */
    public function scopeBetweenDates($query, string $from, string $to)
    {
        $start = Carbon::parse($from)->toDateString();
        $end = Carbon::parse($to)->addDay()->toDateString();

        return $query->where('shift_date', '>=', $start)
            ->where('shift_date', '<', $end);
    }

    public function scopeBlocking($query)
    {
        return $query->whereIn('status', [
            ShiftStatus::Scheduled->value,
            ShiftStatus::Confirmed->value,
            ShiftStatus::InProgress->value,
            ShiftStatus::Recorded->value,
            ShiftStatus::Completed->value,
        ]);
    }
}
