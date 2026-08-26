<?php

namespace App\Models;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Models\Concerns\TracksUserChanges;
use Database\Factories\DeploymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deployment extends Model
{
    /** @use HasFactory<DeploymentFactory> */
    use HasFactory, TracksUserChanges;

    protected $fillable = [
        'guard_id',
        'site_id',
        'region_id',
        'supervisor_id',
        'shift_type',
        'status',
        'start_date',
        'end_date',
        'is_current',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'shift_type' => DeploymentShiftType::class,
            'status' => DeploymentStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
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

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(DeploymentTransfer::class, 'from_deployment_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(DeploymentTransfer::class, 'to_deployment_id');
    }

    public function isActive(): bool
    {
        return $this->status === DeploymentStatus::Active && $this->is_current;
    }

    public function scopeCurrent($query)
    {
        return $query->where('is_current', true)->where('status', DeploymentStatus::Active);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->whereHas('assignedGuard', function ($guard) use ($like): void {
                $guard->where('employment_id', 'like', $like)
                    ->orWhere('full_name', 'like', $like);
            })->orWhereHas('site', function ($site) use ($like): void {
                $site->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        });
    }
}
