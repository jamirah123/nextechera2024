<?php

namespace App\Services\Dashboards;

use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\User;
use App\Support\Performance\DashboardCache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class ShiftDeskService
{
    /**
     * @return array{
     *     date: string,
     *     awaiting_deployment: int,
     *     needs_allocation: int,
     *     missed_today: int,
     *     recorded_today: int,
     *     deployed: int,
     *     links: array<string, string>
     * }
     */
    public function snapshot(?User $user = null, ?Carbon $asOf = null): array
    {
        $build = fn (): array => $this->buildSnapshot($user, $asOf);

        if (app()->runningUnitTests()) {
            return $build();
        }

        $date = ($asOf ?? now())->toDateString();
        $region = $user?->mustStayInOwnRegion() ? (string) ($user->regionId() ?? 'none') : 'all';
        $key = 'psg.shift_desk.'.DashboardCache::version().'.'.$date.'.'.$region;
        $ttl = max(15, (int) config('psg.performance.dashboard_cache_seconds', 45));

        return Cache::remember($key, $ttl, $build);
    }

    /**
     * @return array{
     *     date: string,
     *     awaiting_deployment: int,
     *     needs_allocation: int,
     *     missed_today: int,
     *     recorded_today: int,
     *     deployed: int,
     *     links: array<string, string>
     * }
     */
    private function buildSnapshot(?User $user = null, ?Carbon $asOf = null): array
    {
        $date = ($asOf ?? now())->toDateString();
        $regionScoped = $user?->mustStayInOwnRegion() ?? false;
        $regionId = $user?->regionId();

        $awaitingDeployment = Guard::query()
            ->activeEmployment()
            ->when($regionScoped, fn ($q) => $q->where('region_id', $regionId))
            ->where('operational_status', OperationalStatus::AwaitingDeployment)
            ->count();

        $deployedQuery = Deployment::query()
            ->current()
            ->when($regionScoped, fn ($q) => $q->where('region_id', $regionId));

        $needsAllocation = (clone $deployedQuery)
            ->whereDoesntHave('assignedGuard.shifts', function ($shift) use ($date): void {
                $shift->where('shift_date', $date)
                    ->whereIn('status', ShiftStatus::blockingAllocationValues());
            })
            ->count();

        $shiftCounts = Shift::query()
            ->forDate($date)
            ->when($regionScoped, fn ($q) => $q->where('region_id', $regionId))
            ->whereIn('status', [ShiftStatus::Missed->value, ShiftStatus::Recorded->value])
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $missedToday = 0;
        $recordedToday = 0;
        foreach ($shiftCounts as $status => $aggregate) {
            $value = $status instanceof ShiftStatus ? $status->value : (string) $status;
            if ($value === ShiftStatus::Missed->value) {
                $missedToday = (int) $aggregate;
            }
            if ($value === ShiftStatus::Recorded->value) {
                $recordedToday = (int) $aggregate;
            }
        }

        return [
            'date' => $date,
            'awaiting_deployment' => $awaitingDeployment,
            'needs_allocation' => $needsAllocation,
            'missed_today' => $missedToday,
            'recorded_today' => $recordedToday,
            'deployed' => (clone $deployedQuery)->count(),
            'links' => [
                'deploy_board' => route('deployments.board'),
                'allocate' => route('deployments.board', ['start_date' => $date]),
                'missed_shifts' => route('shifts.index', ['date' => $date, 'status' => ShiftStatus::Missed->value]),
                'shifts_today' => route('shifts.index', ['date' => $date, 'status' => ShiftStatus::Recorded->value]),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     * @return list<array{label: string, value: string, hint: string, tone: string}>
     */
    public function kpis(User $user, ?array $snapshot = null): array
    {
        $desk = $snapshot ?? $this->snapshot($user);

        return [
            [
                'label' => 'Awaiting deploy',
                'value' => (string) $desk['awaiting_deployment'],
                'hint' => 'Guards ready for site posting',
                'tone' => 'brand',
            ],
            [
                'label' => 'Needs allocation',
                'value' => (string) $desk['needs_allocation'],
                'hint' => 'Posted today without a duty shift',
                'tone' => 'amber',
            ],
            [
                'label' => 'Missed today',
                'value' => (string) $desk['missed_today'],
                'hint' => 'No-shows requiring follow-up',
                'tone' => 'rose',
            ],
            [
                'label' => 'Shifts recorded',
                'value' => (string) $desk['recorded_today'],
                'hint' => 'Duties recorded for today',
                'tone' => 'emerald',
            ],
        ];
    }
}
