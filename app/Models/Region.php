<?php

namespace App\Models;

use App\Enums\RegionStatus;
use App\Models\Concerns\TracksUserChanges;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory, SoftDeletes, TracksUserChanges;
    use \App\Models\Concerns\CapturesDeletionSnapshot;

    protected $fillable = [
        'name',
        'code',
        'description',
        'manager_name',
        'manager_phone',
        'status',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => RegionStatus::class,
        ];
    }

    public function supervisors(): HasMany
    {
        return $this->hasMany(Supervisor::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function isActive(): bool
    {
        return $this->status === RegionStatus::Active;
    }

    public function scopeActive($query)
    {
        return $query->where('status', RegionStatus::Active);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('code', 'like', $like)
                ->orWhere('manager_name', 'like', $like);
        });
    }
}
