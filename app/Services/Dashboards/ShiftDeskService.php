<?php

namespace App\Services\Dashboards;

use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Support\Carbon;

class ShiftDeskService
{
    /**
     * @return array{
     *     date: string,
     *     awaiting_deployment: int,
     *     needs_allocation: int,
     *     missed_today: int,
     *     in_progress_today: int,
     *     deployed: int,
     *     links: array<string, string>
     * }
     */
    public function snapshot(?User $user = null, ?Carbon $asOf = null): array
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
                $shift->whereDate('shift_date', $date)
                    ->whereIn('status', ShiftStatus::blockingAllocationValues());
            })
            ->count();

        $shiftBase = Shift::query()
            ->forDate($date)
            ->when($regionScoped, fn ($q) => $q->where('region_id', $regionId));

        return [
            'date' => $date,
            'awaiting_deployment' => $awaitingDeployment,
            'needs_allocation' => $needsAllocation,
            'missed_today' => (clone $shiftBase)->where('status', ShiftStatus::Missed)->count(),
            'in_progress_today' => (clone $shiftBase)->where('status', ShiftStatus::InProgress)->count(),
            'deployed' => (clone $deployedQuery)->count(),
            'links' => [
                'deploy_board' => route('deployments.board'),
                'allocate' => route('shifts.allocate', ['date' => $date]),
                'missed_shifts' => route('shifts.index', ['date' => $date, 'status' => ShiftStatus::Missed->value]),
                'shifts_today' => route('shifts.index', ['date' => $date]),
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: string, hint: string, tone: string}>
     */
    public function kpis(User $user): array
    {
        $desk = $this->snapshot($user);

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
                'label' => 'In progress',
                'value' => (string) $desk['in_progress_today'],
                'hint' => 'Live duty shifts right now',
                'tone' => 'emerald',
            ],
        ];
    }
}
