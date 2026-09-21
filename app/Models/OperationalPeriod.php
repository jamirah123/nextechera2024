<?php

namespace App\Models;

use App\Enums\OperationalPeriodStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationalPeriod extends Model
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
            'status' => OperationalPeriodStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === OperationalPeriodStatus::Open;
    }

    public function isClosed(): bool
    {
        return $this->status === OperationalPeriodStatus::Closed;
    }

    public function label(): string
    {
        return $this->starts_on->format('F Y');
    }
}
