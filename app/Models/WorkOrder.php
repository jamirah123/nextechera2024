<?php

namespace App\Models;

use App\Enums\WorkOrderCategory;
use App\Enums\WorkOrderPriority;
use App\Enums\WorkOrderStatus;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkOrder extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'reference',
        'title',
        'description',
        'category',
        'status',
        'priority',
        'assigned_to',
        'region_id',
        'due_at',
        'completed_at',
        'completed_by',
        'resolution_notes',
        'source_audit_log_id',
        'source_action',
        'dedup_key',
        'subject_type',
        'subject_id',
        'context',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'category' => WorkOrderCategory::class,
            'status' => WorkOrderStatus::class,
            'priority' => WorkOrderPriority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
            'context' => 'array',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function sourceAuditLog(): BelongsTo
    {
        return $this->belongsTo(AuditLog::class, 'source_audit_log_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
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
                ->orWhereHas('assignee', fn ($user) => $user->where('name', 'like', $like));
        });
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [
            WorkOrderStatus::Open->value,
            WorkOrderStatus::Assigned->value,
            WorkOrderStatus::InProgress->value,
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->status->isOpen()
            && $this->due_at !== null
            && $this->due_at->isPast();
    }
}
