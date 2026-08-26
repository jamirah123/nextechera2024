<?php

namespace App\Services\Dashboards;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Services\ManpowerService;
use Illuminate\Support\Collection;

class OperationalDashboardService
{
    public function __construct(private ManpowerService $manpower)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function company(): array
    {
        $today = now()->toDateString();
        $manpower = $this->manpower->forCompany();

        $todayShifts = Shift::query()->forDate($today)->get(['id', 'status', 'shift_type', 'site_id']);

        $regions = Region::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'status'])
            ->map(function (Region $region) {
                $mp = $this->manpower->forRegion($region);

                return [
                    'region' => $region,
                    'manpower' => $mp,
                    'sites' => $mp['sites_count'],
                    'href' => route('ops-dashboards.region', $region),
                ];
            });

        $understaffedSites = Site::query()
            ->active()
            ->with(['region:id,name', 'client:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site) => [
                'site' => $site,
                'manpower' => $this->manpower->forSite($site),
            ])
            ->filter(fn (array $row) => $row['manpower']['shortage'] > 0)
            ->sortByDesc(fn (array $row) => $row['manpower']['shortage'])
            ->take(8)
            ->values();

        return [
            'today' => $today,
            'manpower' => $manpower,
            'kpis' => [
                'active_guards' => Guard::query()->where('employment_status', EmploymentStatus::Active)->count(),
                'on_duty' => Guard::query()->where('operational_status', OperationalStatus::OnDuty)->count(),
                'on_leave' => Guard::query()->where('operational_status', OperationalStatus::OnLeave)->count(),
                'absent' => Guard::query()->where('operational_status', OperationalStatus::Absent)->count(),
                'deserted' => Guard::query()->where('operational_status', OperationalStatus::Deserted)->count(),
                'active_deployments' => Deployment::query()->current()->count(),
                'shifts_today' => $todayShifts->count(),
                'completed_today' => $todayShifts->where('status', ShiftStatus::Completed)->count(),
                'missed_today' => $todayShifts->where('status', ShiftStatus::Missed)->count(),
                'overtime_today' => $todayShifts->where('shift_type', ShiftType::Overtime)->count(),
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
        $manpower = $this->manpower->forRegion($region);

        $sites = $region->sites()
            ->with(['client:id,name', 'supervisor:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site) => [
                'site' => $site,
                'manpower' => $this->manpower->forSite($site),
                'href' => route('ops-dashboards.site', $site),
            ]);

        $todayShifts = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->forDate($today)
            ->where('region_id', $region->id)
            ->orderBy('starts_at')
            ->limit(30)
            ->get();

        $guards = Guard::query()
            ->where('region_id', $region->id)
            ->where('employment_status', EmploymentStatus::Active)
            ->get(['id', 'operational_status']);

        return [
            'today' => $today,
            'region' => $region,
            'manpower' => $manpower,
            'kpis' => [
                'sites' => $sites->count(),
                'active_guards' => $guards->count(),
                'on_duty' => $guards->where('operational_status', OperationalStatus::OnDuty)->count(),
                'on_leave' => $guards->where('operational_status', OperationalStatus::OnLeave)->count(),
                'absent' => $guards->where('operational_status', OperationalStatus::Absent)->count(),
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
            'understaffed' => $company['understaffed_sites']->take(5),
        ];
    }
}
