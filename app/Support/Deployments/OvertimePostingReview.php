<?php

namespace App\Support\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Support\Carbon;

class OvertimePostingReview
{
    /**
     * Decide which posting-board rows need an overtime confirmation, and which
     * are conflicts that confirmation must not override.
     *
     * A second shift is overtime only when the guard already has a normal shift
     * on the same operational date in a window that does not overlap the new one.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{prompts: list<array<string, mixed>>, conflicts: list<array<string, mixed>>}
     */
    public function review(array $rows): array
    {
        $prompts = [];
        $conflicts = [];

        foreach ($rows as $row) {
            $finding = $this->inspect($row);

            if ($finding['prompt'] !== null) {
                $prompts[] = $finding['prompt'];
            }

            if ($finding['conflict'] !== null) {
                $conflicts[] = $finding['conflict'];
            }
        }

        return [
            'prompts' => $prompts,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{prompt: ?array<string, mixed>, conflict: ?array<string, mixed>}
     */
    private function inspect(array $row): array
    {
        $empty = ['prompt' => null, 'conflict' => null];
        $shiftType = DeploymentShiftType::tryFrom((string) ($row['shift_type'] ?? ''));

        if ($shiftType === null || $shiftType === DeploymentShiftType::Rotating) {
            return $empty;
        }

        $guard = Guard::query()->find((int) ($row['guard_id'] ?? 0));
        $site = Site::query()->find((int) ($row['site_id'] ?? 0));

        if ($guard === null || $site === null) {
            return $empty;
        }

        $incoming = $shiftType === DeploymentShiftType::Night ? ShiftPeriod::Night : ShiftPeriod::Day;
        $from = Carbon::parse((string) ($row['start_date'] ?? now()->toDateString()))->startOfDay();
        $to = filled($row['duty_date_to'] ?? null)
            ? Carbon::parse((string) $row['duty_date_to'])->startOfDay()
            : $from->copy();

        if ($to->lt($from)) {
            $to = $from->copy();
        }

        $prompt = null;

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $shifts = Shift::query()
                ->with('site:id,name')
                ->blocking()
                ->where('guard_id', $guard->id)
                ->forDate($day->toDateString())
                ->get();

            foreach ($shifts as $shift) {
                $period = $shift->period instanceof ShiftPeriod ? $shift->period : ShiftPeriod::tryFrom((string) $shift->period);

                if ($period === null) {
                    continue;
                }

                if ($period === $incoming || $this->windowsOverlap($shift, $incoming)) {
                    return [
                        'prompt' => null,
                        'conflict' => [
                            'guard_id' => $guard->id,
                            'name' => $guard->full_name,
                            'employment_id' => $guard->employment_id,
                            'operational_date' => $day->format('j F Y'),
                            'message' => $guard->full_name.' already has a '.$period->label().' shift on '.$day->format('j F Y')
                                .' that overlaps this posting. Confirming overtime does not override that conflict.',
                        ],
                    ];
                }

                $duty = $shift->shift_type instanceof ShiftType ? $shift->shift_type : ShiftType::tryFrom((string) $shift->shift_type);

                if ($duty !== ShiftType::Normal || $prompt !== null) {
                    continue;
                }

                $prompt = [
                    'guard_id' => $guard->id,
                    'name' => $guard->full_name,
                    'employment_id' => $guard->employment_id,
                    'operational_date' => $day->format('j F Y'),
                    'operational_date_iso' => $day->toDateString(),
                    'previous_shift_id' => $shift->id,
                    'previous_period' => $period->label(),
                    'previous_duty' => $duty->label(),
                    'previous_site' => $shift->site?->name ?? 'Unknown site',
                    'new_period' => $incoming->label(),
                    'new_site' => $site->name,
                    'new_site_id' => $site->id,
                ];
            }
        }

        return ['prompt' => $prompt, 'conflict' => null];
    }

    private function windowsOverlap(Shift $existing, ShiftPeriod $incoming): bool
    {
        $existingPeriod = $existing->period instanceof ShiftPeriod
            ? $existing->period
            : ShiftPeriod::tryFrom((string) $existing->period);

        if ($existingPeriod === null || $existingPeriod === $incoming) {
            return $existingPeriod === $incoming;
        }

        [$incomingStart, $incomingEnd] = $this->configuredWindow($incoming);
        $existingStart = $existing->starts_at?->format('H:i') ?? $this->configuredWindow($existingPeriod)[0];
        $existingEnd = $existing->ends_at?->format('H:i') ?? $this->configuredWindow($existingPeriod)[1];

        return $this->clockRangesOverlap($existingStart, $existingEnd, $incomingStart, $incomingEnd);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function configuredWindow(ShiftPeriod $period): array
    {
        $key = $period === ShiftPeriod::Night ? 'night' : 'day';

        return [
            (string) config("psg.shift_defaults.{$key}.start", $period === ShiftPeriod::Night ? '18:00' : '06:00'),
            (string) config("psg.shift_defaults.{$key}.end", $period === ShiftPeriod::Night ? '06:00' : '18:00'),
        ];
    }

    private function clockRangesOverlap(string $startA, string $endA, string $startB, string $endB): bool
    {
        $base = Carbon::parse('2026-01-01')->startOfDay();
        $span = function (string $start, string $end) use ($base): array {
            $from = $base->copy()->setTimeFromTimeString($start);
            $to = $base->copy()->setTimeFromTimeString($end);

            if ($to->lte($from)) {
                $to->addDay();
            }

            return [$from, $to];
        };

        [$fromA, $toA] = $span($startA, $endA);
        [$fromB, $toB] = $span($startB, $endB);

        return $fromA->lt($toB) && $fromB->lt($toA);
    }
}
