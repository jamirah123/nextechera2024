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
    use \App\Models\Concerns\CapturesDeletionSnapshot;

    protected $fillable = [
        'supervisor_code',
        'name',
        'phone',
        'email',
        'region_id',
        'guard_id',
        'staff_id',
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

    public function guardProfile(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(SupervisorAssignmentHistory::class)->latest('effective_at');
    }

    public function loginAccounts(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    public function isActive(): bool
    {
        return $this->status === SupervisorStatus::Active;
    }

    /**
     * Company Employment ID (PSG…) when linked; otherwise internal SUP code.
     */
    public function employmentId(): ?string
    {
        $this->loadMissing(['guardProfile:id,employment_id', 'staffProfile:id,employment_id']);

        return $this->guardProfile?->employment_id
            ?? $this->staffProfile?->employment_id
            ?? $this->supervisor_code;
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
                ->orWhere('email', 'like', $like)
                ->orWhereHas('guardProfile', fn ($guard) => $guard->where('employment_id', 'like', $like));
        });
    }
}
