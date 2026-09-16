<?php

namespace App\Services\Dashboards;

use App\Enums\CoverageStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Services\ManpowerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class OperationalDashboardService
{
    public function __construct(private ManpowerService $manpower) {}

    /**
     * @return array<string, mixed>
     */
    public function company(): array
    {
        $today = now()->toDateString();

        $todayShiftStats = Shift::query()
            ->forDate($today)
            ->selectRaw('status, shift_type, COUNT(*) as aggregate')
            ->groupBy('status', 'shift_type')
            ->get();

        $shiftsToday = (int) $todayShiftStats->sum('aggregate');
        $completedToday = (int) $todayShiftStats->where('status', ShiftStatus::Completed->value)->sum('aggregate');
        $missedToday = (int) $todayShiftStats->where('status', ShiftStatus::Missed->value)->sum('aggregate');
        $overtimeToday = (int) $todayShiftStats->where('shift_type', ShiftType::Overtime->value)->sum('aggregate');

        $guardStatusCounts = Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->selectRaw('operational_status, COUNT(*) as aggregate')
            ->groupBy('operational_status')
            ->pluck('aggregate', 'operational_status');

        $activeSites = Site::query()
            ->where('status', SiteStatus::Active)
            ->with(['region:id,name', 'client:id,name'])
            ->orderBy('name')
            ->get();

        $siteManpower = $this->manpower->forSites($activeSites);
        $manpower = $this->aggregateSnapshots($siteManpower, $activeSites->count());

        $regions = Region::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'status'])
            ->map(function (Region $region) use ($activeSites, $siteManpower) {
                $regionSites = $activeSites->where('region_id', $region->id);
                $snapshots = $regionSites
                    ->map(fn (Site $site) => $siteManpower->get($site->id))
                    ->filter();
                $mp = $this->aggregateSnapshots($snapshots, $regionSites->count());

                return [
                    'region' => $region,
                    'manpower' => $mp,
                    'sites' => $mp['sites_count'],
                    'href' => route('ops-dashboards.region', $region),
                ];
            });

        $understaffedSites = $activeSites
            ->map(fn (Site $site) => [
                'site' => $site,
                'manpower' => $siteManpower->get($site->id),
            ])
            ->filter(fn (array $row) => ($row['manpower']['shortage'] ?? 0) > 0)
            ->sortByDesc(fn (array $row) => $row['manpower']['shortage'])
            ->take(8)
            ->values();

        return [
            'today' => $today,
            'manpower' => $manpower,
            'kpis' => [
                'active_guards' => (int) $guardStatusCounts->sum(),
                'on_duty' => (int) ($guardStatusCounts[OperationalStatus::OnDuty->value] ?? 0),
                'on_leave' => (int) ($guardStatusCounts[OperationalStatus::OnLeave->value] ?? 0),
                'absent' => (int) ($guardStatusCounts[OperationalStatus::Absent->value] ?? 0),
                'deserted' => (int) ($guardStatusCounts[OperationalStatus::Deserted->value] ?? 0),
                'active_deployments' => Deployment::query()->current()->count(),
                'shifts_today' => $shiftsToday,
                'completed_today' => $completedToday,
                'missed_today' => $missedToday,
                'overtime_today' => $overtimeToday,
                'pending_leave' => Leave::query()->where('status', LeaveStatus::Pending)->count(),
            ],
            'regions' => $regions,
            'understaffed_sites' => $understaffedSites,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function region(Region $region): array
    {
        $today = now()->toDateString();
        $siteModels = $region->sites()
            ->with(['client:id,name', 'supervisor:id,name'])
            ->orderBy('name')
            ->get();
        $siteManpower = $this->manpower->forSites($siteModels);
        $manpower = $this->aggregateSnapshots($siteManpower, $siteModels->count());

        $sites = $siteModels->map(fn (Site $site) => [
            'site' => $site,
            'manpower' => $siteManpower->get($site->id) ?? $this->manpower->forSite($site),
            'href' => route('ops-dashboards.site', $site),
        ]);

        $todayShifts = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->forDate($today)
            ->where('region_id', $region->id)
            ->orderBy('starts_at')
            ->limit(30)
            ->get();

        $guardStatusCounts = Guard::query()
            ->where('region_id', $region->id)
            ->where('employment_status', EmploymentStatus::Active)
            ->selectRaw('operational_status, COUNT(*) as aggregate')
            ->groupBy('operational_status')
            ->pluck('aggregate', 'operational_status');

        return [
            'today' => $today,
            'region' => $region,
            'manpower' => $manpower,
            'kpis' => [
                'sites' => $sites->count(),
                'active_guards' => (int) $guardStatusCounts->sum(),
                'on_duty' => (int) ($guardStatusCounts[OperationalStatus::OnDuty->value] ?? 0),
                'on_leave' => (int) ($guardStatusCounts[OperationalStatus::OnLeave->value] ?? 0),
                'absent' => (int) ($guardStatusCounts[OperationalStatus::Absent->value] ?? 0),
                'shifts_today' => $todayShifts->count(),
                'completed_today' => $todayShifts->where('status', ShiftStatus::Completed)->count(),
                'missed_today' => $todayShifts->where('status', ShiftStatus::Missed)->count(),
            ],
            'sites' => $sites,
            'today_shifts' => $todayShifts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function site(Site $site): array
    {
        $today = now()->toDateString();
        $site->loadMissing(['region:id,name,code', 'client:id,name', 'supervisor:id,name']);
        $manpower = $this->manpower->forSite($site);

        $deployments = Deployment::query()
            ->with(['assignedGuard:id,employment_id,full_name,operational_status'])
            ->where('site_id', $site->id)
            ->current()
            ->latest('start_date')
            ->limit(50)
            ->get();

        $todayShifts = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name'])
            ->forDate($today)
            ->where('site_id', $site->id)
            ->orderBy('starts_at')
            ->get();

        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd = now()->endOfWeek()->toDateString();
        $weekShifts = Shift::query()
            ->where('site_id', $site->id)
            ->whereBetween('shift_date', [$weekStart, $weekEnd])
            ->get(['status', 'shift_type']);

        return [
            'today' => $today,
            'site' => $site,
            'manpower' => $manpower,
            'kpis' => [
                'deployed' => $deployments->count(),
                'shifts_today' => $todayShifts->count(),
                'completed_today' => $todayShifts->where('status', ShiftStatus::Completed)->count(),
                'missed_today' => $todayShifts->where('status', ShiftStatus::Missed)->count(),
                'week_total' => $weekShifts->count(),
                'week_overtime' => $weekShifts->where('shift_type', ShiftType::Overtime)->count(),
                'week_missed' => $weekShifts->where('status', ShiftStatus::Missed)->count(),
            ],
            'deployments' => $deployments,
            'today_shifts' => $todayShifts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function guard(Guard $guard): array
    {
        $guard->loadMissing(['region:id,name', 'currentSite:id,name,code', 'currentSupervisor:id,name']);

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $monthShifts = Shift::query()
            ->where('guard_id', $guard->id)
            ->whereBetween('shift_date', [$monthStart, $monthEnd])
            ->get(['status', 'shift_type', 'shift_date']);

        $completed = $monthShifts->where('status', ShiftStatus::Completed);
        $upcoming = Shift::query()
            ->with(['site:id,name,code'])
            ->where('guard_id', $guard->id)
            ->whereDate('shift_date', '>=', now()->toDateString())
            ->whereIn('status', [ShiftStatus::Scheduled, ShiftStatus::Confirmed])
            ->orderBy('shift_date')
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        $recentShifts = Shift::query()
            ->with(['site:id,name,code'])
            ->where('guard_id', $guard->id)
            ->latest('shift_date')
            ->limit(12)
            ->get();

        $deployments = Deployment::query()
            ->with(['site:id,name,code', 'region:id,name'])
            ->where('guard_id', $guard->id)
            ->latest('start_date')
            ->limit(8)
            ->get();

        $activeLeave = Leave::query()
            ->where('guard_id', $guard->id)
            ->whereIn('status', [LeaveStatus::Approved, LeaveStatus::Pending])
            ->whereDate('end_date', '>=', now()->toDateString())
            ->orderBy('start_date')
            ->get();

        return [
            'guard' => $guard,
            'kpis' => [
                'month_normal' => $completed->where('shift_type', ShiftType::Normal)->count(),
                'month_overtime' => $completed->where('shift_type', ShiftType::Overtime)->count(),
                'month_total' => $completed->count(),
                'month_missed' => $monthShifts->where('status', ShiftStatus::Missed)->count(),
                'upcoming' => $upcoming->count(),
                'active_leave' => $activeLeave->where('status', LeaveStatus::Approved)->count(),
            ],
            'upcoming' => $upcoming,
            'recent_shifts' => $recentShifts,
            'deployments' => $deployments,
            'active_leave' => $activeLeave,
            'current_deployment' => $deployments->first(fn (Deployment $d) => $d->is_current),
        ];
    }

    /**
     * Compact snapshot for role landing pages.
     *
     * @return array{manpower: array<string, mixed>, today: array<string, int>, understaffed: Collection}
     */
    public function landingSnapshot(): array
    {
        return Cache::remember('psg.dashboard.landing_snapshot', max(15, (int) config('psg.performance.dashboard_cache_seconds', 45)), function () {
            $company = $this->company();

            return [
                'manpower' => $company['manpower'],
                'today' => [
                    'shifts' => $company['kpis']['shifts_today'],
                    'completed' => $company['kpis']['completed_today'],
                    'missed' => $company['kpis']['missed_today'],
                    'overtime' => $company['kpis']['overtime_today'],
                    'on_leave' => $company['kpis']['on_leave'],
                    'absent' => $company['kpis']['absent'],
                ],
                'understaffed' => $company['understaffed_sites']->take(5)->values(),
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>|null>  $snapshots
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
    private function aggregateSnapshots(Collection $snapshots, int $sitesCount): array
    {
        $required = 0;
        $deployed = 0;
        $understaffed = 0;

        foreach ($snapshots as $snap) {
            if (! is_array($snap)) {
                continue;
            }
            $required += (int) ($snap['required'] ?? 0);
            $deployed += (int) ($snap['deployed'] ?? 0);
            if (($snap['shortage'] ?? 0) > 0 && ($snap['required'] ?? 0) > 0) {
                $understaffed++;
            }
        }

        $shortage = max(0, $required - $deployed);

        return [
            'required' => $required,
            'deployed' => $deployed,
            'shortage' => $shortage,
            'surplus' => max(0, $deployed - $required),
            'coverage_percent' => $required > 0 ? round(($deployed / $required) * 100, 1) : 0.0,
            'status' => match (true) {
                $required <= 0 => CoverageStatus::Unconfigured,
                $deployed < $required => CoverageStatus::Understaffed,
                $deployed > $required => CoverageStatus::Overstaffed,
                default => CoverageStatus::FullyStaffed,
            },
            'sites_count' => $sitesCount,
            'understaffed_sites' => $understaffed,
        ];
    }
}
