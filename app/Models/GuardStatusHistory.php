<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuardStatusHistory extends Model
{
    protected $fillable = [
        'guard_id',
        'status_type',
        'previous_status',
        'new_status',
        'reason',
        'notes',
        'meta',
        'changed_by',
        'effective_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'effective_at' => 'datetime',
        ];
    }

    public function guardRecord(): BelongsTo
    {
        return $this->belongsTo(Guard::class);
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function statusTypeLabel(): string
    {
        return match ($this->status_type) {
            'employment' => 'Employment',
            'operational' => 'Operational',
            default => ucfirst((string) $this->status_type),
        };
    }

    public function previousStatusLabel(): string
    {
        return $this->formatStatus($this->previous_status) ?? '—';
    }

    public function newStatusLabel(): string
    {
        return $this->formatStatus($this->new_status) ?? '—';
    }

    private function formatStatus(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($this->status_type === 'employment') {
            return \App\Enums\EmploymentStatus::tryFrom($value)?->label() ?? str_replace('_', ' ', ucfirst($value));
        }

        if ($this->status_type === 'operational') {
            return \App\Enums\OperationalStatus::tryFrom($value)?->label() ?? str_replace('_', ' ', ucfirst($value));
        }

        return str_replace('_', ' ', ucfirst($value));
    }
}
