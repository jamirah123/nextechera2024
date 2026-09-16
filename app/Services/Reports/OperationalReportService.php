<?php

namespace App\Services\Reports;

use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Absence;
use App\Models\Deployment;
use App\Models\DeploymentTransfer;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\Shift;
use App\Services\ManpowerService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class OperationalReportService
{
    public function __construct(private ManpowerService $manpower) {}

    /**
     * @param  array{date?: string, region_id?: int|null, site_id?: int|null}  $filters
     * @return array{summary: array<string, int>, rows: Collection<int, Shift>}
     */
    public function dailyShifts(array $filters): array
    {
        $date = $filters['date'] ?? now()->toDateString();

        $rows = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code', 'region:id,name'])
            ->forDate($date)
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['site_id']), fn ($q) => $q->where('site_id', $filters['site_id']))
            ->orderBy('starts_at')
            ->get();

        return [
            'summary' => [
                'total' => $rows->count(),
                'scheduled' => $rows->where('status', ShiftStatus::Scheduled)->count(),
                'confirmed' => $rows->where('status', ShiftStatus::Confirmed)->count(),
                'in_progress' => $rows->where('status', ShiftStatus::InProgress)->count(),
                'completed' => $rows->where('status', ShiftStatus::Completed)->count(),
                'missed' => $rows->where('status', ShiftStatus::Missed)->count(),
                'cancelled' => $rows->where('status', ShiftStatus::Cancelled)->count(),
                'normal' => $rows->where('shift_type', ShiftType::Normal)->count(),
                'overtime' => $rows->where('shift_type', ShiftType::Overtime)->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @param  array{week_start?: string, region_id?: int|null, site_id?: int|null}  $filters
     * @return array{summary: array<string, int>, rows: Collection<int, Shift>, week_start: string, week_end: string}
     */
    public function weeklyShifts(array $filters): array
    {
        $start = Carbon::parse($filters['week_start'] ?? now()->startOfWeek()->toDateString())->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $rows = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->whereBetween('shift_date', [$start->toDateString(), $end->toDateString()])
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['site_id']), fn ($q) => $q->where('site_id', $filters['site_id']))
            ->orderBy('shift_date')
            ->orderBy('starts_at')
            ->get();

        return [
            'week_start' => $start->toDateString(),
            'week_end' => $end->toDateString(),
            'summary' => [
                'total' => $rows->count(),
                'completed' => $rows->where('status', ShiftStatus::Completed)->count(),
                'overtime' => $rows->where('shift_type', ShiftType::Overtime)->count(),
                'missed' => $rows->where('status', ShiftStatus::Missed)->count(),
                'cancelled' => $rows->where('status', ShiftStatus::Cancelled)->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{summary: array<string, int>, rows: Collection<int, Guard>}
     */
    public function guards(array $filters = []): array
    {
        $rows = Guard::query()
            ->with(['region:id,name', 'currentSite:id,name'])
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['employment_status']), fn ($q) => $q->where('employment_status', $filters['employment_status']))
            ->when(! empty($filters['operational_status']), fn ($q) => $q->where('operational_status', $filters['operational_status']))
            ->orderBy('full_name')
            ->get();

        return [
            'summary' => [
                'total' => $rows->count(),
                'active' => $rows->where('employment_status', EmploymentStatus::Active)->count(),
                'on_leave' => $rows->where('operational_status', OperationalStatus::OnLeave)->count(),
                'absent' => $rows->where('operational_status', OperationalStatus::Absent)->count(),
                'deserted' => $rows->where('operational_status', OperationalStatus::Deserted)->count(),
                'awaiting' => $rows->where('operational_status', OperationalStatus::AwaitingDeployment)->count(),
            ],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{summary: array<string, int>, deployments: Collection<int, Deployment>, transfers: Collection<int, DeploymentTransfer>}
     */
    public function deployments(array $filters = []): array
    {
        $deployments = Deployment::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code', 'region:id,name'])
            ->when(! empty($filters['current_only']), fn ($q) => $q->current())
            ->when(! empty($filters['region_id']), fn ($q) => $q->where('region_id', $filters['region_id']))
            ->when(! empty($filters['site_id']), fn ($q) => $q->where('site_id', $filters['site_id']))
            ->latest('start_date')
            ->limit(200)
            ->get();

        $transfers = DeploymentTransfer::query()
            ->with(['fromSite:id,name', 'toSite:id,name'])
            ->when(! empty($filters['from']), fn ($q) => $q->whereDate('effective_at', '>=', $filters['from']))
            ->when(! empty($filters['to']), fn ($q) => $q->whereDate('effective_at', '<=', $filters['to']))
            ->latest('effective_at')
            ->limit(100)
            ->get();

        return [
            'summary' => [
                'active' => Deployment::query()->current()->count(),
                'transferred' => Deployment::query()->where('status', DeploymentStatus::Transferred)->count(),
                'ended' => Deployment::query()->where('status', DeploymentStatus::Ended)->count(),
                'transfers' => $transfers->count(),
            ],
            'deployments' => $deployments,
            'transfers' => $transfers,
        ];
    }

    /**
     * @return array{summary: array<string, int|float>, company: array<string, mixed>}
     */
    public function manpower(): array
    {
        $company = $this->manpower->forCompany();

        return [
            'summary' => [
                'required' => $company['required'],
                'deployed' => $company['deployed'],
                'shortage' => $company['shortage'],
                'surplus' => $company['surplus'],
                'coverage_percent' => $company['coverage_percent'],
            ],
            'company' => $company,
        ];
    }

    /**
     * @param  array{from?: string, to?: string}  $filters
     * @return array{summary: array<string, int>, leaves: Collection, absences: Collection, desertions: Collection}
     */
    public function hr(array $filters = []): array
    {
        $from = $filters['from'] ?? now()->startOfMonth()->toDateString();
        $to = $filters['to'] ?? now()->toDateString();

        $leaves = Leave::query()
            ->with('assignedGuard:id,employment_id,full_name')
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->latest('start_date')
            ->limit(200)
            ->get();

        $absences = Absence::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name'])
            ->whereBetween('absence_date', [$from, $to])
            ->latest('absence_date')
            ->limit(200)
            ->get();

        $desertions = Desertion::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'lastKnownSite:id,name'])
            ->whereBetween('date_reported', [$from, $to])
            ->latest('date_reported')
            ->limit(100)
            ->get();

        return [
            'summary' => [
                'leaves' => $leaves->count(),
                'approved_leaves' => $leaves->where('status', LeaveStatus::Approved)->count(),
                'absences' => $absences->count(),
                'desertions' => $desertions->count(),
            ],
            'leaves' => $leaves,
            'absences' => $absences,
            'desertions' => $desertions,
            'from' => $from,
            'to' => $to,
        ];
    }
}
