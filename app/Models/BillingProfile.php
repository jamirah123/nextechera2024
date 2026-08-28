<?php

namespace App\Models;

use App\Enums\GuardClassification;
use App\Models\Concerns\TracksUserChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingProfile extends Model
{
    use TracksUserChanges;

    protected $fillable = [
        'client_id',
        'site_id',
        'currency',
        'monthly_site_fee',
        'contracted_armed_guards',
        'contracted_unarmed_guards',
        'monthly_rate_per_armed_guard',
        'monthly_rate_per_unarmed_guard',
        'monthly_cost_per_armed_guard',
        'monthly_cost_per_unarmed_guard',
        'rate_per_armed_shift',
        'rate_per_unarmed_shift',
        'rate_per_guard_shift',
        'cost_per_armed_shift',
        'cost_per_unarmed_shift',
        'cost_per_guard_shift',
        'effective_from',
        'effective_to',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'monthly_site_fee' => 'decimal:2',
            'contracted_armed_guards' => 'integer',
            'contracted_unarmed_guards' => 'integer',
            'monthly_rate_per_armed_guard' => 'decimal:2',
            'monthly_rate_per_unarmed_guard' => 'decimal:2',
            'monthly_cost_per_armed_guard' => 'decimal:2',
            'monthly_cost_per_unarmed_guard' => 'decimal:2',
            'rate_per_armed_shift' => 'decimal:2',
            'rate_per_unarmed_shift' => 'decimal:2',
            'rate_per_guard_shift' => 'decimal:2',
            'cost_per_armed_shift' => 'decimal:2',
            'cost_per_unarmed_shift' => 'decimal:2',
            'cost_per_guard_shift' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function contractedGuardTotal(): int
    {
        return (int) $this->contracted_armed_guards + (int) $this->contracted_unarmed_guards;
    }

    public function billRateFor(GuardClassification $classification): float
    {
        $rate = match ($classification) {
            GuardClassification::Armed => $this->rate_per_armed_shift,
            GuardClassification::Unarmed => $this->rate_per_unarmed_shift,
        };

        if ((float) $rate > 0) {
            return (float) $rate;
        }

        return (float) $this->rate_per_guard_shift;
    }

    public function costRateFor(GuardClassification $classification): float
    {
        $cost = match ($classification) {
            GuardClassification::Armed => $this->cost_per_armed_shift,
            GuardClassification::Unarmed => $this->cost_per_unarmed_shift,
        };

        if ((float) $cost > 0) {
            return (float) $cost;
        }

        return (float) $this->cost_per_guard_shift;
    }

    public function scopeSearch($query, ?string $term)
    {
        if (! filled($term)) {
            return $query;
        }

        $like = '%'.$term.'%';

        return $query->where(function ($q) use ($like): void {
            $q->where('notes', 'like', $like)
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', $like))
                ->orWhereHas('site', fn ($s) => $s->where('name', 'like', $like)->orWhere('code', 'like', $like));
        });
    }
}
