<?php

namespace App\Services\Shifts;

use App\Enums\GuardClassification;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Models\Deployment;
use App\Services\ShiftService;
use App\Support\Shifts\ShiftDutyTypeResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class BulkShiftAllocationService
{
    public function __construct(private ShiftService $shifts)
    {
    }

    /**
     * @param  list<array{
     *     deployment_id: int,
     *     period: string,
     *     shift_type?: string,
     *     guard_classification?: string
     * }>  $rows
     * @return array{created: int, skipped: int, errors: list<string>}
     */
    public function allocate(string $shiftDate, array $rows): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];

        $deploymentIds = collect($rows)->pluck('deployment_id')->unique()->filter()->all();
        $deployments = Deployment::query()
            ->current()
            ->with(['site:id,name,region_id,supervisor_id', 'assignedGuard:id,employment_id,full_name,guard_classification'])
            ->whereIn('id', $deploymentIds)
            ->get()
            ->keyBy('id');

        foreach ($rows as $index => $row) {
            $deployment = $deployments->get((int) ($row['deployment_id'] ?? 0));
            if (! $deployment || ! $deployment->site) {
                $skipped++;
                $errors[] = 'Row '.($index + 1).': deployment not found or inactive.';

                continue;
            }

            $period = ShiftPeriod::tryFrom((string) ($row['period'] ?? '')) ?? ShiftPeriod::Day;
            [$start, $end] = $this->timesFor($period);
            $requestedType = ShiftType::tryFrom((string) ($row['shift_type'] ?? ''));
            $shiftType = ShiftDutyTypeResolver::resolve(
                $deployment->shift_type,
                $period,
                $requestedType,
            );
            $classification = GuardClassification::tryFrom((string) ($row['guard_classification'] ?? ''))
                ?? $deployment->assignedGuard?->guard_classification
                ?? GuardClassification::Unarmed;

            try {
                DB::transaction(function () use ($deployment, $shiftDate, $period, $start, $end, $shiftType, $classification): void {
                    $this->shifts->create([
                        'guard_id' => $deployment->guard_id,
                        'site_id' => $deployment->site_id,
                        'shift_date' => $shiftDate,
                        'start_time' => $start,
                        'end_time' => $end,
                        'period' => $period->value,
                        'shift_type' => $shiftType->value,
                        'guard_classification' => $classification->value,
                        'status' => \App\Enums\ShiftStatus::Recorded->value,
                        'acknowledge_warnings' => true,
                        'notes' => 'Shift recorded from duty roster',
                    ]);
                });
                $created++;
            } catch (InvalidArgumentException $e) {
                $skipped++;
                $label = $deployment->assignedGuard?->employment_id ?? '#'.$deployment->guard_id;
                $errors[] = $label.': '.$e->getMessage();
            } catch (Throwable $e) {
                $skipped++;
                $label = $deployment->assignedGuard?->employment_id ?? '#'.$deployment->guard_id;
                $errors[] = $label.': could not allocate shift.';
            }
        }

        return compact('created', 'skipped', 'errors');
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function timesFor(ShiftPeriod $period): array
    {
        if ($period === ShiftPeriod::Night) {
            return [
                config('psg.shift_defaults.night.start', '18:00'),
                config('psg.shift_defaults.night.end', '06:00'),
            ];
        }

        return [
            config('psg.shift_defaults.day.start', '06:00'),
            config('psg.shift_defaults.day.end', '18:00'),
        ];
    }
}
