<?php

namespace App\Models;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'action',
        'category',
        'severity',
        'summary',
        'subject_type',
        'subject_id',
        'actor_id',
        'actor_name',
        'actor_role',
        'ip_address',
        'user_agent',
        'context',
        'is_override',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => AuditCategory::class,
            'severity' => AuditSeverity::class,
            'context' => 'array',
            'is_override' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new LogicException('Audit logs are immutable and cannot be deleted.');
        });
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOverrides(Builder $query): Builder
    {
        return $query->where('is_override', true);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function (Builder $inner) use ($like): void {
            $inner->where('summary', 'like', $like)
                ->orWhere('action', 'like', $like)
                ->orWhere('actor_name', 'like', $like);
        });
    }
}
