<?php

namespace App\Models;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Models\Concerns\TracksUserChanges;
use Database\Factories\GuardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guard extends Model
{
    /** @use HasFactory<GuardFactory> */
    use HasFactory, SoftDeletes, TracksUserChanges;

    protected $fillable = [
        'employment_id',
        'first_name',
        'middle_name',
        'last_name',
        'full_name',
        'gender',
        'date_of_birth',
        'phone',
        'alternative_phone',
        'address',
        'national_id',
        'date_employed',
        'employment_status',
        'rank_designation',
        'region_id',
        'current_site_id',
        'current_supervisor_id',
        'operational_status',
        'emergency_contact_name',
        'emergency_contact_phone',
        'photo_path',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'gender' => GuardGender::class,
            'employment_status' => EmploymentStatus::class,
            'operational_status' => OperationalStatus::class,
            'date_of_birth' => 'date',
            'date_employed' => 'date',
        ];
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    public function currentSupervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class, 'current_supervisor_id');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(GuardStatusHistory::class)->latest('effective_at');
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->latest('start_date');
    }

    public function currentDeployment(): HasOne
    {
        return $this->hasOne(Deployment::class)->current()->latestOfMany('start_date');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class)->latest('starts_at');
    }

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class)->latest('start_date');
    }

    public function absences(): HasMany
    {
        return $this->hasMany(Absence::class)->latest('absence_date');
    }

    public function desertions(): HasMany
    {
        return $this->hasMany(Desertion::class)->latest('date_reported');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class)->latest('occurred_at');
    }

    public function isEmploymentActive(): bool
    {
        return $this->employment_status === EmploymentStatus::Active;
    }

    public function scopeActiveEmployment($query)
    {
        return $query->where('employment_status', EmploymentStatus::Active);
    }

    /**
     * Guards without an active site posting (deployment board pool).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Guard>  $query
     */
    public function scopeAwaitingDeployment($query): void
    {
        $query->whereDoesntHave('deployments', fn ($q) => $q->current());
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('employment_id', 'like', $like)
                ->orWhere('full_name', 'like', $like)
                ->orWhere('first_name', 'like', $like)
                ->orWhere('last_name', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('national_id', 'like', $like)
                ->orWhere('rank_designation', 'like', $like);
        });
    }
}
