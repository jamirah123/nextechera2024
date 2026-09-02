<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'reference',
        'site_id',
        'guard_id',
        'shift_id',
        'incident_type',
        'severity',
        'status',
        'occurred_at',
        'reported_at',
        'title',
        'description',
        'action_taken',
        'follow_up_notes',
        'assigned_to',
        'follow_up_due_at',
        'police_reference',
        'client_notified',
        'reported_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'incident_type' => IncidentType::class,
            'severity' => IncidentSeverity::class,
            'status' => IncidentStatus::class,
            'occurred_at' => 'datetime',
            'reported_at' => 'datetime',
            'follow_up_due_at' => 'date',
            'client_notified' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function assignedGuard(): BelongsTo
    {
        return $this->belongsTo(Guard::class, 'guard_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(IncidentAttachment::class)->latest('id');
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('reference', 'like', $like)
                ->orWhere('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('police_reference', 'like', $like)
                ->orWhereHas('site', fn ($site) => $site->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orWhereHas('assignedGuard', fn ($guard) => $guard
                    ->where('full_name', 'like', $like)
                    ->orWhere('employment_id', 'like', $like));
        });
    }

    public function scopeForSiteOnDate($query, int $siteId, string $date)
    {
        return $query
            ->where('site_id', $siteId)
            ->whereDate('occurred_at', $date);
    }
}
