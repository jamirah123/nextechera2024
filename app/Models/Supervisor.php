<?php

namespace App\Models;

use App\Enums\SupervisorStatus;
use App\Models\Concerns\TracksUserChanges;
use Database\Factories\SupervisorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supervisor extends Model
{
    /** @use HasFactory<SupervisorFactory> */
    use HasFactory, SoftDeletes, TracksUserChanges;

    protected $fillable = [
        'supervisor_code',
        'name',
        'phone',
        'email',
        'region_id',
        'status',
        'assignment_date',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SupervisorStatus::class,
            'assignment_date' => 'date',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(SupervisorAssignmentHistory::class)->latest('effective_at');
    }

    public function isActive(): bool
    {
        return $this->status === SupervisorStatus::Active;
    }

    public function scopeActive($query)
    {
        return $query->where('status', SupervisorStatus::Active);
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('name', 'like', $like)
                ->orWhere('supervisor_code', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('email', 'like', $like);
        });
    }
}
