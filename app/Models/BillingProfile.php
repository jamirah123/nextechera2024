<?php

namespace App\Models;

use App\Enums\BillingMode;
use App\Enums\GuardClassification;
use App\Enums\ShiftPeriod;
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
        'billing_mode',
        'cash_no_tax',
        'monthly_site_fee',
        'contracted_armed_guards',
        'contracted_unarmed_guards',
        'contracted_day_armed_guards',
        'contracted_day_unarmed_guards',
        'contracted_night_armed_guards',
        'contracted_night_unarmed_guards',
        'monthly_rate_per_armed_guard',
        'monthly_rate_per_unarmed_guard',
        'monthly_rate_per_armed_day_guard',
        'monthly_rate_per_unarmed_day_guard',
        'monthly_rate_per_armed_night_guard',
        'monthly_rate_per_unarmed_night_guard',
        'monthly_cost_per_armed_guard',
        'monthly_cost_per_unarmed_guard',
        'rate_per_armed_shift',
        'rate_per_unarmed_shift',
        'rate_per_guard_shift',
        'rate_per_armed_day_shift',
        'rate_per_unarmed_day_shift',
        'rate_per_armed_night_shift',
        'rate_per_unarmed_night_shift',
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
            'billing_mode' => BillingMode::class,
            'cash_no_tax' => 'boolean',
            'monthly_site_fee' => 'decimal:2',
            'contracted_armed_guards' => 'integer',
            'contracted_unarmed_guards' => 'integer',
            'contracted_day_armed_guards' => 'integer',
            'contracted_day_unarmed_guards' => 'integer',
            'contracted_night_armed_guards' => 'integer',
            'contracted_night_unarmed_guards' => 'integer',
            'monthly_rate_per_armed_guard' => 'decimal:2',
            'monthly_rate_per_unarmed_guard' => 'decimal:2',
            'monthly_rate_per_armed_day_guard' => 'decimal:2',
            'monthly_rate_per_unarmed_day_guard' => 'decimal:2',
            'monthly_rate_per_armed_night_guard' => 'decimal:2',
            'monthly_rate_per_unarmed_night_guard' => 'decimal:2',
            'monthly_cost_per_armed_guard' => 'decimal:2',
            'monthly_cost_per_unarmed_guard' => 'decimal:2',
            'rate_per_armed_shift' => 'decimal:2',
            'rate_per_unarmed_shift' => 'decimal:2',
            'rate_per_guard_shift' => 'decimal:2',
            'rate_per_armed_day_shift' => 'decimal:2',
            'rate_per_unarmed_day_shift' => 'decimal:2',
            'rate_per_armed_night_shift' => 'decimal:2',
            'rate_per_unarmed_night_shift' => 'decimal:2',
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

    public static function activeForSite(Site $site): ?self
    {
        $today = now()->toDateString();

        $siteProfile = static::query()
            ->active()
            ->where('client_id', $site->client_id)
            ->where('site_id', $site->id)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->orderByDesc('effective_from')
            ->first();

        if ($siteProfile !== null) {
            return $siteProfile;
        }

        return static::query()
            ->active()
            ->where('client_id', $site->client_id)
            ->whereNull('site_id')
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->orderByDesc('effective_from')
            ->first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function contractedGuardTotal(): int
    {
        return (int) $this->contracted_day_armed_guards
            + (int) $this->contracted_day_unarmed_guards
            + (int) $this->contracted_night_armed_guards
            + (int) $this->contracted_night_unarmed_guards;
    }

    public function estimatedMonthlyHeadcountBill(): float
    {
        $armedRate = $this->monthlyArmedRate();
        $unarmedRate = $this->monthlyUnarmedRate();

        return (((int) $this->contracted_day_armed_guards + (int) $this->contracted_night_armed_guards) * $armedRate)
            + (((int) $this->contracted_day_unarmed_guards + (int) $this->contracted_night_unarmed_guards) * $unarmedRate);
    }

    public function monthlyArmedRate(): float
    {
        $rate = (float) $this->monthly_rate_per_armed_guard;

        if ($rate > 0) {
            return $rate;
        }

        return max(
            (float) $this->monthly_rate_per_armed_day_guard,
            (float) $this->monthly_rate_per_armed_night_guard,
        );
    }

    public function monthlyUnarmedRate(): float
    {
        $rate = (float) $this->monthly_rate_per_unarmed_guard;

        if ($rate > 0) {
            return $rate;
        }

        return max(
            (float) $this->monthly_rate_per_unarmed_day_guard,
            (float) $this->monthly_rate_per_unarmed_night_guard,
        );
    }

    public function estimatedMonthlyTotal(): float
    {
        return $this->estimatedMonthlyHeadcountBill();
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

    public function shiftBillRateFor(GuardClassification $classification, ShiftPeriod $period): float
    {
        $specific = match ($period) {
            ShiftPeriod::Day => $classification === GuardClassification::Armed
                ? $this->rate_per_armed_day_shift
                : $this->rate_per_unarmed_day_shift,
            ShiftPeriod::Night => $classification === GuardClassification::Armed
                ? $this->rate_per_armed_night_shift
                : $this->rate_per_unarmed_night_shift,
        };

        if ((float) $specific > 0) {
            return (float) $specific;
        }

        return $this->billRateFor($classification);
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
