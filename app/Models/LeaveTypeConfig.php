<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveTypeConfig extends Model
{
    protected $table = 'leave_types';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_paid',
        'pay_percent',
        'max_days_per_year',
        'requires_document',
        'requires_approval',
        'count_weekends',
        'count_public_holidays',
        'eligibility',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'pay_percent' => 'decimal:2',
            'max_days_per_year' => 'decimal:1',
            'requires_document' => 'boolean',
            'requires_approval' => 'boolean',
            'count_weekends' => 'boolean',
            'count_public_holidays' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(LeaveEntitlement::class, 'leave_type_id');
    }

    public function chargeableDays(Carbon $start, Carbon $end): float
    {
        $holidays = PublicHoliday::query()
            ->whereDate('holiday_date', '>=', $start->toDateString())
            ->whereDate('holiday_date', '<=', $end->toDateString())
            ->pluck('holiday_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->all();

        $days = 0.0;
        for ($cursor = $start->copy()->startOfDay(); $cursor->lte($end); $cursor->addDay()) {
            if (! $this->count_weekends && $cursor->isWeekend()) {
                continue;
            }

            if (! $this->count_public_holidays && in_array($cursor->toDateString(), $holidays, true)) {
                continue;
            }

            $days++;
        }

        return $days;
    }

    public function isUnlimited(): bool
    {
        return $this->max_days_per_year === null;
    }
}
