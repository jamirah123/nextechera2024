<?php

namespace App\Services;

use App\Enums\CoverageStatus;
use App\Enums\SiteStatus;
use App\Models\Region;
use App\Models\Site;
use Illuminate\Support\Collection;

class ManpowerService
{
    /**
     * @return array{
     *     required: int,
     *     required_day: int,
     *     required_night: int,
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

        $deployed = app(DeploymentService::class)->activeCountForSite($site);
        $deployedDay = app(DeploymentService::class)->activeCountForSiteByShift($site, \App\Enums\DeploymentShiftType::Day);
        $deployedNight = app(DeploymentService::class)->activeCountForSiteByShift($site, \App\Enums\DeploymentShiftType::Night);
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
