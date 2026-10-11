<?php

namespace App\Models;

use App\Enums\BillingMode;
use App\Enums\GuardClassification;
use App\Enums\ShiftPeriod;
use App\Enums\VatTreatment;
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
        'vat_treatment',
        'approved_at',
        'approved_by',
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
            'vat_treatment' => VatTreatment::class,
            'approved_at' => 'datetime',
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

    protected static function booted(): void
    {
        static::saving(function (self $profile): void {
            if ($profile->vat_treatment === null) {
                $profile->vat_treatment = VatTreatment::Standard;
            }

            if ($profile->is_active && $profile->approved_at === null) {
                $profile->approved_at = now();
                $profile->approved_by ??= auth()->id();
            }

            if (! $profile->is_active) {
                $profile->approved_at = null;
                $profile->approved_by = null;
            }
        });
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

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_active', true)->whereNotNull('approved_at');
    }

    public function isApproved(): bool
    {
        return (bool) $this->is_active && $this->approved_at !== null;
    }

    public function scopeLabel(): string
    {
        if ($this->site) {
            return trim(($this->site->code ? $this->site->code.' — ' : '').$this->site->name);
        }

        return 'All sites';
    }

    public function vatTreatment(): VatTreatment
    {
        return $this->vat_treatment ?? VatTreatment::Standard;
    }

    public function coversDate(string $date): bool
    {
        $day = \Illuminate\Support\Carbon::parse($date)->toDateString();

        if ($this->effective_from !== null && $this->effective_from->toDateString() > $day) {
            return false;
        }

        return $this->effective_to === null || $this->effective_to->toDateString() >= $day;
    }

    /**
     * Current is the latest approved profile for a client and site that covers today.
     *
     * @param  iterable<int, self>  $profiles
     * @return array<int, string> profile id => current|upcoming|superseded|ended
     */
    public static function standingsFor(iterable $profiles, ?string $on = null): array
    {
        $on ??= now()->toDateString();
        $approved = static::query()
            ->approved()
            ->get(['id', 'client_id', 'site_id', 'effective_from', 'effective_to']);

        $currentByScope = [];
        foreach ($approved as $candidate) {
            if (! $candidate->coversDate($on)) {
                continue;
            }

            $key = $candidate->client_id.'|'.($candidate->site_id ?? 'client');
            $existing = $currentByScope[$key] ?? null;
            if ($existing === null || static::isNewerThan($candidate, $existing)) {
                $currentByScope[$key] = $candidate;
            }
        }

        $standings = [];
        foreach ($profiles as $profile) {
            $key = $profile->client_id.'|'.($profile->site_id ?? 'client');
            if ($profile->isApproved() && $profile->effective_from !== null && $profile->effective_to !== null) {
                $standings[$profile->id] = 'period';
            } elseif ($profile->isApproved() && $profile->effective_from !== null && $profile->effective_from->toDateString() > $on) {
                $standings[$profile->id] = 'upcoming';
            } elseif (! $profile->isApproved() || ! $profile->coversDate($on)) {
                $standings[$profile->id] = 'ended';
            } elseif (($currentByScope[$key]->id ?? null) === $profile->id) {
                $standings[$profile->id] = 'current';
            } else {
                $standings[$profile->id] = 'superseded';
            }
        }

        return $standings;
    }

    public static function standingLabel(string $standing): string
    {
        return match ($standing) {
            'current' => 'Current',
            'period' => 'Service period',
            'upcoming' => 'Upcoming',
            'superseded' => 'Superseded',
            default => 'Ended',
        };
    }

    public static function standingMessage(self $profile, string $standing): string
    {
        if ($standing === 'superseded') {
            return 'A newer billing profile is already in effect for this client and site. Choose the current profile.';
        }

        if ($profile->effective_to !== null) {
            return 'This billing profile ended on '.$profile->effective_to->format('d M Y').'. Choose the current profile for this client and site.';
        }

        return 'This billing profile is not current. Choose the current profile for this client and site.';
    }

    /**
     * A profile with both dates is billed for that service period.
     * An open profile is billed for the current month, or its first month if it has not started.
     *
     * @return array{0: string, 1: string}|null
     */
    public function invoicePeriod(string $standing): ?array
    {
        if ($this->effective_from !== null && $this->effective_to !== null) {
            if ($this->effective_to->toDateString() < $this->effective_from->toDateString()) {
                return null;
            }

            return [$this->effective_from->toDateString(), $this->effective_to->toDateString()];
        }

        if (! in_array($standing, ['current', 'upcoming'], true)) {
            return null;
        }

        if ($standing === 'upcoming') {
            $start = $this->effective_from?->copy()->startOfDay() ?? now()->startOfMonth();
            $end = $start->copy()->endOfMonth();
        } else {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();
        }

        if ($this->effective_from !== null && $this->effective_from->gt($start)) {
            $start = $this->effective_from->copy();
        }

        if ($this->effective_to !== null && $this->effective_to->lt($end)) {
            $end = $this->effective_to->copy();
        }

        if ($end->toDateString() < $start->toDateString()) {
            return null;
        }

        return [$start->toDateString(), $end->toDateString()];
    }

    private static function isNewerThan(self $candidate, self $existing): bool
    {
        $candidateFrom = $candidate->effective_from?->toDateString() ?? '0000-01-01';
        $existingFrom = $existing->effective_from?->toDateString() ?? '0000-01-01';

        if ($candidateFrom !== $existingFrom) {
            return $candidateFrom > $existingFrom;
        }

        return $candidate->id > $existing->id;
    }

    public function contractedGuardTotal(): int
    {
        $split = (int) $this->contracted_day_armed_guards
            + (int) $this->contracted_day_unarmed_guards
            + (int) $this->contracted_night_armed_guards
            + (int) $this->contracted_night_unarmed_guards;

        if ($split > 0) {
            return $split;
        }

        // Legacy profiles stored totals without day/night split.
        return (int) $this->contracted_armed_guards + (int) $this->contracted_unarmed_guards;
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
