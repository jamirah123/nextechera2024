<?php

namespace App\Models;

use App\Enums\GlPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlPeriod extends Model
{
    protected $fillable = [
        'year',
        'month',
        'starts_on',
        'ends_on',
        'status',
        'closed_at',
        'closed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => GlPeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function journals(): HasMany
    {
        return $this->hasMany(GlJournal::class, 'period_id');
    }

    public function isOpen(): bool
    {
        return $this->status === GlPeriodStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === GlPeriodStatus::Closed;
    }

    public function label(): string
    {
        return $this->starts_on->format('F Y');
    }
}
