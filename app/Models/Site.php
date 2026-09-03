<?php

namespace App\Models;

use App\Enums\SiteStatus;
use App\Models\Concerns\TracksUserChanges;
use App\Services\ManpowerService;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory, SoftDeletes, TracksUserChanges;
    use \App\Models\Concerns\CapturesDeletionSnapshot;

    protected $fillable = [
        'name',
        'code',
        'client_id',
        'region_id',
        'supervisor_id',
        'physical_location',
        'latitude',
        'longitude',
        'site_contact_person',
        'site_contact_phone',
        'contract_start_date',
        'contract_end_date',
        'required_guards',
        'required_day_guards',
        'required_day_armed_guards',
        'required_day_unarmed_guards',
        'required_night_guards',
        'required_night_armed_guards',
        'required_night_unarmed_guards',
        'number_of_posts',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => SiteStatus::class,
            'contract_start_date' => 'date',
            'contract_end_date' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'required_guards' => 'integer',
            'required_day_guards' => 'integer',
            'required_day_armed_guards' => 'integer',
            'required_day_unarmed_guards' => 'integer',
            'required_night_guards' => 'integer',
            'required_night_armed_guards' => 'integer',
            'required_night_unarmed_guards' => 'integer',
            'number_of_posts' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Supervisor::class);
    }

    public function manpowerRequirements(): HasMany
    {
        return $this->hasMany(SiteManpowerRequirement::class);
    }

    public function deployments(): HasMany
    {
        return $this->hasMany(Deployment::class);
    }

    public function currentDeployments(): HasMany
    {
        return $this->hasMany(Deployment::class)->current();
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function currentManpowerRequirement(): HasOne
    {
        return $this->hasOne(SiteManpowerRequirement::class)->where('is_current', true)->latestOfMany();
    }

    public function isActive(): bool
    {
        return $this->status === SiteStatus::Active;
    }

    public function scopeActive($query)
    {
        return $query->where('status', SiteStatus::Active);
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
                ->orWhere('physical_location', 'like', $like)
                ->orWhere('site_contact_person', 'like', $like);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function manpowerSnapshot(): array
    {
        return app(ManpowerService::class)->forSite($this);
    }
}
