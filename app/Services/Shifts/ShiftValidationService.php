<?php

namespace App\Services\Shifts;

use App\Enums\ContractStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\SiteStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Services\DeploymentService;
use Carbon\CarbonInterface;

class ShiftValidationService
{
    public function __construct(private DeploymentService $deployments)
    {
    }

    /**
     * @param  array{
     *     guard_id: int,
     *     site_id: int,
     *     starts_at: CarbonInterface,
     *     ends_at: CarbonInterface,
     *     ignore_shift_id?: int|null
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
            $result->critical('suspended', 'Guard is suspended and cannot be scheduled.');
        }

        if ($guard->operational_status === OperationalStatus::Deserted) {
            $result->critical('deserted', 'Deserted guards cannot be scheduled without an authorized override.');
        }

        if (in_array($guard->operational_status, [
            OperationalStatus::OnLeave,
            OperationalStatus::Absent,
            OperationalStatus::SickUnavailable,
        ], true)) {
            $result->critical(
                'unavailable_status',
                'Guard operational status is '.$guard->operational_status->label().'.'
            );
        }

        if ($site->status !== SiteStatus::Active) {
            $result->critical('site_inactive', 'Site is not active.');
        }

        $deployment = Deployment::query()
            ->current()
            ->where('guard_id', $guard->id)
            ->first();

        if (! $deployment) {
            $result->critical('not_deployed', 'Guard has no active deployment. Deploy them before scheduling.');
        } elseif ((int) $deployment->site_id !== (int) $site->id) {
            $result->critical(
                'wrong_site',
                'Guard is currently deployed to another site ('.$deployment->site?->name.'). Transfer first or use an authorized override.'
            );
        }

        if ($site->client && $site->client->contract_status !== ContractStatus::Active) {
            $result->warning(
                'contract_inactive',
                'Client contract status is '.$site->client->contract_status->label().'.'
            );
        }

        if ($this->hasOverlap($guard->id, $startsAt, $endsAt, $ignoreId)) {
            $result->critical('overlap', 'This shift overlaps another scheduled shift for the same guard.');
        }

        if ($this->isDuplicate($guard->id, $site->id, $startsAt, $endsAt, $ignoreId)) {
            $result->critical('duplicate', 'An identical shift already exists for this guard and site.');
        }

        $deployedCount = $this->deployments->activeCountForSite($site);
        $scheduledSameWindow = Shift::query()
            ->blocking()
            ->where('site_id', $site->id)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->count();

        $required = max(1, (int) $site->required_guards);
        if (($scheduledSameWindow + 1) > $required && $deployedCount >= $required) {
            $result->warning(
                'manpower_surplus',
                'Scheduling this shift may exceed the site manpower requirement ('.$required.').'
            );
        }

        return $result;
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
