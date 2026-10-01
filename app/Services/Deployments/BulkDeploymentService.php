<?php

namespace App\Services\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\ShiftType;
use App\Models\Guard;
use App\Models\Site;
use App\Services\DeploymentService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class BulkDeploymentService
{
    public function __construct(private DeploymentService $deployments) {}

    /**
     * @param  list<array{
     *     guard_id: int,
     *     site_id: int,
     *     shift_type?: string,
     *     duty_type?: string|null,
     *     start_date?: string|null,
     *     duty_date_to?: string|null,
     *     notes?: string|null
     * }>  $rows
     * @return array{created: int, skipped: int, errors: list<string>, deployed_guard_ids: list<int>}
     */
    public function deployMany(array $rows): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];
        $deployedGuardIds = [];

        $guardIds = collect($rows)->pluck('guard_id')->unique()->filter()->all();
        $siteIds = collect($rows)->pluck('site_id')->unique()->filter()->all();

        $guards = Guard::query()->whereIn('id', $guardIds)->get()->keyBy('id');
        $sites = Site::query()->whereIn('id', $siteIds)->get()->keyBy('id');

        foreach ($rows as $index => $row) {
            $guard = $guards->get((int) ($row['guard_id'] ?? 0));
            $site = $sites->get((int) ($row['site_id'] ?? 0));

            if (! $guard || ! $site) {
                $skipped++;
                $errors[] = 'Row '.($index + 1).': guard or site missing.';

                continue;
            }

            if ((int) $guard->region_id !== (int) $site->region_id) {
                $skipped++;
                $errors[] = ($guard->employment_id ?? 'Guard').': site must be in the same region.';

                continue;
            }

            $shiftType = DeploymentShiftType::tryFrom((string) ($row['shift_type'] ?? ''))
                ?? DeploymentShiftType::Day;

            try {
                DB::transaction(function () use ($guard, $site, $shiftType, $row): void {
                    $this->deployments->deploy([
                        'guard_id' => $guard->id,
                        'site_id' => $site->id,
                        'shift_type' => $shiftType->value,
                        'duty_type' => $row['duty_type'] ?? ShiftType::Normal->value,
                        'start_date' => $row['start_date'] ?? now()->toDateString(),
                        'duty_date_to' => $row['duty_date_to'] ?? null,
                        'allow_overstaffing' => ! empty($row['allow_overstaffing']),
                        'notes' => $row['notes'] ?? 'Recorded from site posting board',
                    ]);
                });
                $created++;
                $deployedGuardIds[] = $guard->id;
            } catch (InvalidArgumentException $e) {
                $skipped++;
                $errors[] = ($guard->employment_id ?? 'Guard').': '.$e->getMessage();
            } catch (Throwable) {
                $skipped++;
                $errors[] = ($guard->employment_id ?? 'Guard').': could not deploy.';
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
            'deployed_guard_ids' => array_values(array_unique($deployedGuardIds)),
        ];
    }
}
