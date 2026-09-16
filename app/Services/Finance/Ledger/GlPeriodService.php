<?php

namespace App\Services\Finance\Ledger;

use App\Enums\GlPeriodStatus;
use App\Models\GlPeriod;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GlPeriodService
{
    public function ensureForDate(string|Carbon $date): GlPeriod
    {
        $carbon = Carbon::parse($date)->startOfDay();
        $year = (int) $carbon->year;
        $month = (int) $carbon->month;

        $period = GlPeriod::query()->where('year', $year)->where('month', $month)->first();

        if ($period) {
            return $period;
        }

        return GlPeriod::query()->create([
            'year' => $year,
            'month' => $month,
            'starts_on' => $carbon->copy()->startOfMonth()->toDateString(),
            'ends_on' => $carbon->copy()->endOfMonth()->toDateString(),
            'status' => GlPeriodStatus::Open,
        ]);
    }

    public function assertOpenForDate(string|Carbon $date): GlPeriod
    {
        $period = $this->ensureForDate($date);

        if ($period->isClosed()) {
            throw new InvalidArgumentException(
                'Accounting period '.$period->label().' is closed. Re-open the period before posting.'
            );
        }

        return $period;
    }

    public function close(GlPeriod $period, ?User $actor = null, ?string $notes = null): GlPeriod
    {
        if ($period->isClosed()) {
            throw new InvalidArgumentException('This period is already closed.');
        }

        $period->update([
            'status' => GlPeriodStatus::Closed,
            'closed_at' => now(),
            'closed_by' => $actor?->id,
            'notes' => $notes ?? $period->notes,
        ]);

        return $period->fresh(['closer']);
    }

    public function reopen(GlPeriod $period, ?User $actor = null): GlPeriod
    {
        if ($period->isOpen()) {
            throw new InvalidArgumentException('This period is already open.');
        }

        $period->update([
            'status' => GlPeriodStatus::Open,
            'closed_at' => null,
            'closed_by' => null,
            'notes' => trim(($period->notes ? $period->notes."\n" : '').'Re-opened by '.($actor?->name ?? 'system').' on '.now()->toDateTimeString()),
        ]);

        return $period->fresh();
    }

    public function ensureRollingWindow(int $monthsAhead = 3, int $monthsBehind = 3): void
    {
        $start = now()->startOfMonth()->subMonths($monthsBehind);

        DB::transaction(function () use ($start, $monthsAhead, $monthsBehind): void {
            for ($i = 0; $i <= $monthsBehind + $monthsAhead; $i++) {
                $this->ensureForDate($start->copy()->addMonths($i));
            }
        });
    }
}
