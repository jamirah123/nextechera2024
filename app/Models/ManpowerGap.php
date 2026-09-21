<?php

namespace App\Models;

use App\Enums\ManpowerGapStatus;
use App\Enums\ShiftPeriod;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManpowerGap extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'site_id',
        'region_id',
        'gap_date',
        'period',
        'required',
        'permanent_deployed',
        'original_shortage',
        'overtime_covered',
        'remaining_shortage',
        'status',
        'resolved_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gap_date' => 'date',
            'period' => ShiftPeriod::class,
            'required' => 'integer',
            'permanent_deployed' => 'integer',
            'original_shortage' => 'integer',
            'overtime_covered' => 'integer',
            'remaining_shortage' => 'integer',
            'status' => ManpowerGapStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function overtimeDeployments(): HasMany
    {
        return $this->hasMany(Deployment::class, 'manpower_gap_id');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [
            ManpowerGapStatus::Open,
            ManpowerGapStatus::PartiallyResolved,
        ], true);
    }

    public function reference(): string
    {
        return 'GAP-'.$this->gap_date?->format('Ymd').'-'.$this->period?->value.'-'.$this->id;
    }
}
