<?php

namespace App\Services;

use App\Enums\CoverageStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\SiteStatus;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Support\Collection;

class ManpowerService
{
    /**
     * @return array{
     *     required: int,
     *     required_day: int,
     *     required_night: int,
     *     required_day_armed: int,
     *     required_day_unarmed: int,
     *     required_night_armed: int,
     *     required_night_unarmed: int,
     *     scheduled: int,
     *     available: int,
     *     deployed: int,
     *     deployed_day: int,
     *     deployed_night: int,
     *     shortage: int,
     *     surplus: int,
     *     shortage_day: int,
     *     shortage_night: int,
     *     coverage_percent: float,
     *     status: CoverageStatus,
     *     contracted: int,
     *     sla_shortage: int,
     *     sla_percent: float|null,
     *     billing_profile: \App\Models\BillingProfile|null
     * }
     */
    public function forSite(Site $site): array
    {
        $required = (int) $site->required_guards;
        $requiredDay = (int) $site->required_day_guards;
        $requiredNight = (int) $site->required_night_guards;
        $requiredDayArmed = (int) $site->required_day_armed_guards;
        $requiredDayUnarmed = (int) $site->required_day_unarmed_guards;
        $requiredNightArmed = (int) $site->required_night_armed_guards;
        $requiredNightUnarmed = (int) $site->required_night_unarmed_guards;

        $deployed = app(DeploymentService::class)->activeCountForSite($site);
        $deployedDay = app(DeploymentService::class)->activeCountForSiteByShift($site, DeploymentShiftType::Day);
        $deployedNight = app(DeploymentService::class)->activeCountForSiteByShift($site, DeploymentShiftType::Night);
        $scheduled = $deployed;
        $available = $deployed;

        $shortage = max(0, $required - $deployed);
        $surplus = max(0, $deployed - $required);
        $coverage = $required > 0 ? round(($deployed / $required) * 100, 1) : 0.0;

        $billingProfile = \App\Models\BillingProfile::activeForSite($site);
        $contracted = $billingProfile?->contractedGuardTotal() ?? 0;
        $slaShortage = $contracted > 0 ? max(0, $contracted - $deployed) : 0;
        $slaPercent = $contracted > 0 ? round(($deployed / $contracted) * 100, 1) : null;

        return [
            'required' => $required,
            'required_day' => $requiredDay,
            'required_night' => $requiredNight,
            'required_day_armed' => $requiredDayArmed,
            'required_day_unarmed' => $requiredDayUnarmed,
            'required_night_armed' => $requiredNightArmed,
            'required_night_unarmed' => $requiredNightUnarmed,
            'scheduled' => $scheduled,
            'available' => $available,
            'deployed' => $deployed,
            'deployed_day' => $deployedDay,
            'deployed_night' => $deployedNight,
            'shortage' => $shortage,
            'surplus' => $surplus,
            'shortage_day' => max(0, $requiredDay - $deployedDay),
            'shortage_night' => max(0, $requiredNight - $deployedNight),
            'coverage_percent' => $coverage,
            'status' => $this->coverageStatus($required, $deployed),
            'contracted' => $contracted,
            'sla_shortage' => $slaShortage,
            'sla_percent' => $slaPercent,
            'billing_profile' => $billingProfile,
        ];
    }

    /**
     * Required vs standing deployments vs shifts allocated for a specific date.
     *
     * @return array{
     *     date: string,
     *     required: int,
     *     required_day: int,
     *     required_night: int,
     *     deployed: int,
     *     deployed_day: int,
     *     deployed_night: int,
     *     allocated: int,
     *     allocated_day: int,
     *     allocated_night: int,
     *     shortage: int,
     *     shortage_day: int,
     *     shortage_night: int,
     *     allocation_shortage: int,
     *     allocation_shortage_day: int,
     *     allocation_shortage_night: int,
     *     coverage_percent: float,
     *     allocation_coverage_percent: float,
     *     status: CoverageStatus,
     *     allocation_status: CoverageStatus
     * }
     */
    public function forSiteOnDate(Site $site, string $date): array
    {
        $base = $this->forSite($site);

        $allocatedDay = Shift::query()
            ->forDate($date)
            ->where('site_id', $site->id)
            ->where('period', ShiftPeriod::Day->value)
            ->whereIn('status', ShiftStatus::blockingAllocationValues())
            ->count();

        $allocatedNight = Shift::query()
            ->forDate($date)
            ->where('site_id', $site->id)
            ->where('period', ShiftPeriod::Night->value)
            ->whereIn('status', ShiftStatus::blockingAllocationValues())
            ->count();

        $allocated = $allocatedDay + $allocatedNight;
        $required = $base['required'];
        $requiredDay = $base['required_day'];
        $requiredNight = $base['required_night'];

        return [
            'date' => $date,
            'required' => $required,
            'required_day' => $requiredDay,
            'required_night' => $requiredNight,
            'deployed' => $base['deployed'],
            'deployed_day' => $base['deployed_day'],
            'deployed_night' => $base['deployed_night'],
            'allocated' => $allocated,
            'allocated_day' => $allocatedDay,
            'allocated_night' => $allocatedNight,
            'shortage' => $base['shortage'],
            'shortage_day' => $base['shortage_day'],
            'shortage_night' => $base['shortage_night'],
            'allocation_shortage' => max(0, $required - $allocated),
            'allocation_shortage_day' => max(0, $requiredDay - $allocatedDay),
            'allocation_shortage_night' => max(0, $requiredNight - $allocatedNight),
            'coverage_percent' => $base['coverage_percent'],
            'allocation_coverage_percent' => $required > 0 ? round(($allocated / $required) * 100, 1) : 0.0,
            'status' => $base['status'],
            'allocation_status' => $this->coverageStatus($required, $allocated),
        ];
    }

    /**
     * @return array{
     *     required: int,
     *     deployed: int,
     *     allocated: int,
     *     shortage: int,
     *     allocation_shortage: int,
     *     surplus: int,
     *     coverage_percent: float,
     *     allocation_coverage_percent: float,
     *     status: CoverageStatus,
     *     allocation_status: CoverageStatus,
     *     sites_count: int,
     *     understaffed_sites: int,
     *     under_allocated_sites: int
     * }
     */
    public function forCompanyOnDate(string $date, ?int $regionId = null): array
    {
        $sites = Site::query()
            ->where('status', SiteStatus::Active)
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->get();

        $required = 0;
        $deployed = 0;
        $allocated = 0;
        $understaffed = 0;
        $underAllocated = 0;

        foreach ($sites as $site) {
            $snap = $this->forSiteOnDate($site, $date);
            $required += $snap['required'];
            $deployed += $snap['deployed'];
            $allocated += $snap['allocated'];
            if ($snap['status'] === CoverageStatus::Understaffed) {
                $understaffed++;
            }
            if ($snap['allocation_status'] === CoverageStatus::Understaffed) {
                $underAllocated++;
            }
        }

        $shortage = max(0, $required - $deployed);
        $allocationShortage = max(0, $required - $allocated);
        $surplus = max(0, $deployed - $required);

        return [
            'required' => $required,
            'deployed' => $deployed,
            'allocated' => $allocated,
            'shortage' => $shortage,
            'allocation_shortage' => $allocationShortage,
            'surplus' => $surplus,
            'coverage_percent' => $required > 0 ? round(($deployed / $required) * 100, 1) : 0.0,
            'allocation_coverage_percent' => $required > 0 ? round(($allocated / $required) * 100, 1) : 0.0,
            'status' => $this->coverageStatus($required, $deployed),
            'allocation_status' => $this->coverageStatus($required, $allocated),
            'sites_count' => $sites->count(),
            'understaffed_sites' => $understaffed,
            'under_allocated_sites' => $underAllocated,
        ];
    }

    /**
     * @return array{
     *     required: int,
     *     deployed: int,
     *     shortage: int,
     *     surplus: int,
     *     coverage_percent: float,
     *     status: CoverageStatus,
     *     sites_count: int,
     *     understaffed_sites: int
     * }
     */
    public function forRegion(Region $region): array
    {
        $sites = $region->sites()->where('status', SiteStatus::Active)->get();

        return $this->aggregate($sites);
    }

    /**
     * @return array{
     *     required: int,
     *     deployed: int,
     *     shortage: int,
     *     surplus: int,
     *     coverage_percent: float,
     *     status: CoverageStatus,
     *     sites_count: int,
     *     understaffed_sites: int
     * }
     */
    public function forCompany(): array
    {
        $sites = Site::query()->where('status', SiteStatus::Active)->get();

        return $this->aggregate($sites);
    }

    /**
     * @param  Collection<int, Site>  $sites
     * @return array{
     *     required: int,
     *     deployed: int,
     *     shortage: int,
     *     surplus: int,
     *     coverage_percent: float,
     *     status: CoverageStatus,
     *     sites_count: int,
     *     understaffed_sites: int
     * }
     */
    private function aggregate(Collection $sites): array
    {
        $required = 0;
        $deployed = 0;
        $understaffed = 0;

        foreach ($sites as $site) {
            $snap = $this->forSite($site);
            $required += $snap['required'];
            $deployed += $snap['deployed'];
            if ($snap['status'] === CoverageStatus::Understaffed) {
                $understaffed++;
            }
        }

        $shortage = max(0, $required - $deployed);
        $surplus = max(0, $deployed - $required);
        $coverage = $required > 0 ? round(($deployed / $required) * 100, 1) : 0.0;

        return [
            'required' => $required,
            'deployed' => $deployed,
            'shortage' => $shortage,
            'surplus' => $surplus,
            'coverage_percent' => $coverage,
            'status' => $this->coverageStatus($required, $deployed),
            'sites_count' => $sites->count(),
            'understaffed_sites' => $understaffed,
        ];
    }

    private function coverageStatus(int $required, int $deployed): CoverageStatus
    {
        if ($required <= 0) {
            return CoverageStatus::Unconfigured;
        }

        if ($deployed < $required) {
            return CoverageStatus::Understaffed;
        }

        if ($deployed > $required) {
            return CoverageStatus::Overstaffed;
        }

        return CoverageStatus::FullyStaffed;
    }
}
