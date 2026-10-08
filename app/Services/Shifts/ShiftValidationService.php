<?php

namespace App\Services\Shifts;

use App\Enums\ContractStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\SiteStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Services\LeaveService;
use Carbon\CarbonInterface;

class ShiftValidationService
{
    public function __construct(
        private LeaveService $leaves,
    ) {}

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     starts_at: CarbonInterface,
     *     ends_at: CarbonInterface,
     *     ignore_shift_id?: int|null,
     *     guard_classification?: string|null,
     *     period?: string|null,
     *     is_correction?: bool
     * }  $data
     */
    public function validate(array $data): ShiftValidationResult
    {
        $result = ShiftValidationResult::make();

        $guard = Guard::query()->find($data['guard_id']);
        $site = Site::query()->with('client')->find($data['site_id']);
        $startsAt = $data['starts_at'];
        $endsAt = $data['ends_at'];
        $ignoreId = $data['ignore_shift_id'] ?? null;
        $isCorrection = (bool) ($data['is_correction'] ?? false);
        $classification = GuardClassification::tryFrom((string) ($data['guard_classification'] ?? ''))
            ?? GuardClassification::Unarmed;
        $period = ShiftPeriod::tryFrom((string) ($data['period'] ?? ''))
            ?? ($startsAt->hour < 12 ? ShiftPeriod::Day : ShiftPeriod::Night);

        if (! $guard) {
            return $result->critical('guard_missing', 'Selected guard was not found.');
        }

        if (! $site) {
            return $result->critical('site_missing', 'Selected site was not found.');
        }

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            $result->critical('invalid_window', 'Shift end must be after shift start.');
        }

        if ($guard->employment_status !== EmploymentStatus::Active) {
            $result->critical(
                'employment_inactive',
                'Guard employment status is '.$guard->employment_status->label().' and cannot be scheduled.'
            );
        }

        if (in_array($guard->employment_status, [EmploymentStatus::Terminated, EmploymentStatus::Resigned, EmploymentStatus::Retired], true)) {
            $result->critical('employment_ended', 'Guard employment has ended.');
        }

        if ($guard->employment_status === EmploymentStatus::Suspended
            || $guard->operational_status === OperationalStatus::Suspended) {
            if ($isCorrection) {
                $result->warning('suspended', 'Guard is suspended and cannot be scheduled.');
            } else {
                $result->critical('suspended', 'Guard is suspended and cannot be scheduled.');
            }
        }

        if ($guard->operational_status === OperationalStatus::Deserted) {
            $result->critical('deserted', 'Deserted guards cannot be scheduled without an authorized override.');
        }

        if (in_array($guard->operational_status, [
            OperationalStatus::OnLeave,
            OperationalStatus::Absent,
            OperationalStatus::SickUnavailable,
        ], true)) {
            $message = 'Guard operational status is '.$guard->operational_status->label().'.';
            if ($isCorrection) {
                $result->warning('unavailable_status', $message);
            } else {
                $result->critical('unavailable_status', $message);
            }
        }

        if ($this->leaves->hasApprovedLeaveOn($guard->id, $startsAt->toDateString())) {
            $message = 'Guard has approved leave covering this shift date.';
            if ($isCorrection) {
                $result->warning('approved_leave', $message);
            } else {
                $result->critical('approved_leave', $message);
            }
        }

        if ($site->status !== SiteStatus::Active) {
            $result->critical('site_inactive', 'Site is not active.');
        }

        if ($guard->region_id !== null && (int) $guard->region_id !== (int) $site->region_id) {
            $result->critical('region_mismatch', 'Guard and site must be in the same region.');
        }

        $deployment = Deployment::query()
            ->current()
            ->where('guard_id', $guard->id)
            ->first();

        if (! $deployment && ! $isCorrection) {
            $result->warning(
                'not_deployed',
                'Guard has no posting covering today. The shift is still recorded for this date.',
            );
        }

        if ($classification === GuardClassification::Armed
            && $guard->guard_classification !== GuardClassification::Armed) {
            $result->critical(
                'armed_mismatch',
                'Armed shifts require an armed-classified guard. Update the guard profile or allocate as unarmed.'
            );
        }

        if ($site->client && $site->client->contract_status !== ContractStatus::Active) {
            $result->warning(
                'contract_inactive',
                'Client contract status is '.$site->client->contract_status->label().'.'
            );
        }

        $sameShiftElsewhere = $this->sameShiftAtAnotherSite($guard->id, $site->id, $startsAt->toDateString(), $period, $ignoreId);
        if ($sameShiftElsewhere !== null) {
            $message = 'Guard already deployed for the '.$period->label().' shift on '
                .$startsAt->format('j F Y').' at '.$sameShiftElsewhere.'.';
            $result->critical('same_shift_site', $message);
        }

        if ($this->hasOverlap($guard->id, $startsAt, $endsAt, $ignoreId)) {
            $message = 'Deployment Conflict: Guard '.$guard->employment_id
                .' already has an overlapping duty for this shift window.';
            if ($isCorrection) {
                $result->warning('overlap', $message);
            } else {
                $result->critical('overlap', $message);
            }
        }

        if ($this->isDuplicate($guard->id, $site->id, $startsAt, $endsAt, $ignoreId)) {
            $message = 'An identical shift already exists for this guard and site.';
            if ($isCorrection) {
                $result->warning('duplicate', $message);
            } else {
                $result->critical('duplicate', $message);
            }
        }

        $requiredForPeriod = $period === ShiftPeriod::Night
            ? (int) $site->required_night_guards
            : (int) $site->required_day_guards;

        if ($requiredForPeriod <= 0) {
            $requiredForPeriod = (int) $site->required_guards;
        }

        if ($requiredForPeriod > 0) {
            $allocatedForPeriod = Shift::query()
                ->blocking()
                ->where('site_id', $site->id)
                ->where('shift_date', $startsAt->toDateString())
                ->where('period', $period->value)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->count();

            if (($allocatedForPeriod + 1) > $requiredForPeriod) {
                $message = 'Site '.$period->label().' requirement is '.$requiredForPeriod
                    .' guard(s); '.$allocatedForPeriod.' already allocated for '
                    .$startsAt->toDateString().'. End/cancel a shift, transfer a guard, or raise the site '.$period->label().' requirement.';
                if ($isCorrection) {
                    $result->warning('manpower_surplus', $message);
                } else {
                    $result->critical('manpower_surplus', $message);
                }
            }
        }

        $requiredArmed = $period === ShiftPeriod::Night
            ? (int) $site->required_night_armed_guards
            : (int) $site->required_day_armed_guards;

        if ($classification === GuardClassification::Armed && $requiredArmed > 0) {
            $armedAllocated = Shift::query()
                ->blocking()
                ->where('site_id', $site->id)
                ->where('shift_date', $startsAt->toDateString())
                ->where('period', $period->value)
                ->where('guard_classification', GuardClassification::Armed->value)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->count();

            if (($armedAllocated + 1) > $requiredArmed) {
                $result->critical(
                    'armed_surplus',
                    'Site '.$period->label().' armed requirement is '.$requiredArmed
                    .'; '.$armedAllocated.' armed shift(s) already allocated for this date.'
                );
            }
        }

        return $result;
    }

    private function sameShiftAtAnotherSite(
        int $guardId,
        int $siteId,
        string $dutyDate,
        ShiftPeriod $period,
        ?int $ignoreId,
    ): ?string {
        $existing = Shift::query()
            ->blocking()
            ->with('site:id,name')
            ->where('guard_id', $guardId)
            ->where('shift_date', $dutyDate)
            ->where('period', $period->value)
            ->where('site_id', '!=', $siteId)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();

        return $existing?->site?->name;
    }

    private function hasOverlap(int $guardId, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreId): bool
    {
        return Shift::query()
            ->blocking()
            ->where('guard_id', $guardId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function isDuplicate(int $guardId, int $siteId, CarbonInterface $startsAt, CarbonInterface $endsAt, ?int $ignoreId): bool
    {
        return Shift::query()
            ->where('guard_id', $guardId)
            ->where('site_id', $siteId)
            ->where('starts_at', $startsAt)
            ->where('ends_at', $endsAt)
            ->whereNotIn('status', [ShiftStatus::Cancelled->value])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
    }
}
