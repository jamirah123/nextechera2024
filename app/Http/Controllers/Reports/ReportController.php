<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Site;
use App\Services\ReportExportService;
use App\Services\Reports\MonthlyShiftCalculationService;
use App\Services\Reports\OperationalReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private MonthlyShiftCalculationService $monthlyShifts,
        private OperationalReportService $reports,
        private ReportExportService $exports,
    ) {
    }

    public function index(): View
    {
        $this->authorizeReports();

        return view('reports.index', [
            'cards' => [
                [
                    'title' => 'Monthly shift summary',
                    'description' => 'Normal, overtime and totals by guard for payroll-ready month-end review.',
                    'href' => route('reports.monthly-shifts'),
                    'tone' => 'brand',
                ],
                [
                    'title' => 'Daily shifts',
                    'description' => 'Today or any date: scheduled, completed, missed and overtime counts.',
                    'href' => route('reports.daily-shifts'),
                    'tone' => 'sky',
                ],
                [
                    'title' => 'Weekly shifts',
                    'description' => 'Week board with completion and overtime rollups.',
                    'href' => route('reports.weekly-shifts'),
                    'tone' => 'indigo',
                ],
                [
                    'title' => 'Guards',
                    'description' => 'Employment and operational status across the company.',
                    'href' => route('reports.guards'),
                    'tone' => 'emerald',
                ],
                [
                    'title' => 'Deployments',
                    'description' => 'Active deployments and recent transfers.',
                    'href' => route('reports.deployments'),
                    'tone' => 'amber',
                ],
                [
                    'title' => 'Manpower coverage',
                    'description' => 'Required vs deployed with shortage and surplus.',
                    'href' => route('manpower.coverage'),
                    'tone' => 'violet',
                ],
                [
                    'title' => 'HR summary',
                    'description' => 'Leave, absences and desertions for a selected period.',
                    'href' => route('reports.hr'),
                    'tone' => 'rose',
                ],
            ],
        ]);
    }

    public function monthlyShifts(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->monthFilters($request);
        $rows = $this->monthlyShifts->paginate($filters);
        $totals = $this->monthlyShifts->summaryTotals($filters);

        return view('reports.monthly-shifts', [
            'rows' => $rows,
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'region_id']),
            'totals' => $totals,
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportMonthlyShifts(Request $request): StreamedResponse
    {
        $this->authorizeReports();

        $filters = $this->monthFilters($request);
        $rows = $this->monthlyShifts->calculate($filters);
        $filename = sprintf(
            'psg-monthly-shifts-%04d-%02d',
            $filters['year'],
            $filters['month'],
        );

        return $this->downloadCsv(
            $filename,
            $this->monthlyShifts->exportHeaders(),
            $this->monthlyShifts->exportRows($rows),
        );
    }

    public function dailyShifts(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->dailyFilters($request);
        $report = $this->reports->dailyShifts($filters);

        return view('reports.daily-shifts', [
            ...$report,
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportDailyShifts(Request $request): StreamedResponse
    {
        $this->authorizeReports();

        $filters = $this->dailyFilters($request);
        $report = $this->reports->dailyShifts($filters);
        $headers = ['#', 'Reference', 'Employment ID', 'Guard', 'Site', 'Period', 'Type', 'Status', 'Start', 'End'];
        $data = $report['rows']->values()->map(fn ($shift, int $index) => [
            $index + 1,
            $shift->reference,
            $shift->assignedGuard?->employment_id,
            $shift->assignedGuard?->full_name,
            $shift->site?->name,
            $shift->period->label(),
            $shift->shift_type->label(),
            $shift->status->label(),
            $shift->starts_at->format('H:i'),
            $shift->ends_at->format('H:i'),
        ]);

        return $this->downloadCsv('psg-daily-shifts-'.$filters['date'], $headers, $data);
    }

    public function weeklyShifts(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->weeklyFilters($request);
        $report = $this->reports->weeklyShifts($filters);

        return view('reports.weekly-shifts', [
            ...$report,
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportWeeklyShifts(Request $request): StreamedResponse
    {
        $this->authorizeReports();

        $filters = $this->weeklyFilters($request);
        $report = $this->reports->weeklyShifts($filters);
        $headers = ['#', 'Date', 'Reference', 'Employment ID', 'Guard', 'Site', 'Period', 'Type', 'Status', 'Start', 'End'];
        $data = $report['rows']->values()->map(fn ($shift, int $index) => [
            $index + 1,
            $shift->shift_date->toDateString(),
            $shift->reference,
            $shift->assignedGuard?->employment_id,
            $shift->assignedGuard?->full_name,
            $shift->site?->name,
            $shift->period->label(),
            $shift->shift_type->label(),
            $shift->status->label(),
            $shift->starts_at->format('H:i'),
            $shift->ends_at->format('H:i'),
        ]);

        return $this->downloadCsv(
            'psg-weekly-shifts-'.$report['week_start'].'-to-'.$report['week_end'],
            $headers,
            $data,
        );
    }

    public function guards(Request $request): View
    {
        $this->authorizeReports();

        $filters = $request->only(['region_id', 'employment_status', 'operational_status']);
        $report = $this->reports->guards($filters);

        return view('reports.guards', [
            ...$report,
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportGuards(Request $request): StreamedResponse
    {
        $this->authorizeReports();

        $report = $this->reports->guards($request->only(['region_id', 'employment_status', 'operational_status']));
        $headers = ['#', 'Employment ID', 'Name', 'Region', 'Site', 'Employment', 'Operational'];
        $data = $report['rows']->values()->map(fn ($guard, int $index) => [
            $index + 1,
            $guard->employment_id,
            $guard->full_name,
            $guard->region?->name,
            $guard->currentSite?->name,
            $guard->employment_status->label(),
            $guard->operational_status->label(),
        ]);

        return $this->downloadCsv('psg-guards-report', $headers, $data);
    }

    public function deployments(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->deploymentFilters($request);

        return view('reports.deployments', [
            ...$this->reports->deployments($filters),
            'filters' => $filters,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'exportQuery' => $this->cleanQuery([
                ...$filters,
                'current_only' => $filters['current_only'] ? '1' : '0',
            ]),
        ]);
    }

    public function exportDeployments(Request $request): StreamedResponse
    {
        $this->authorizeReports();

        $filters = $this->deploymentFilters($request);
        $report = $this->reports->deployments($filters);

        $headers = ['#', 'Record type', 'Employment ID', 'Guard', 'Site / From', 'To site', 'Status', 'Date'];
        $data = collect();
        $serial = 1;

        foreach ($report['deployments'] as $deployment) {
            $data->push([
                $serial++,
                'Deployment',
                $deployment->assignedGuard?->employment_id,
                $deployment->assignedGuard?->full_name,
                $deployment->site?->name,
                '',
                $deployment->status->label(),
                optional($deployment->start_date)?->toDateString() ?? '',
            ]);
        }

        foreach ($report['transfers'] as $transfer) {
            $data->push([
                $serial++,
                'Transfer',
                '',
                '',
                $transfer->fromSite?->name,
                $transfer->toSite?->name,
                'Transferred',
                optional($transfer->effective_at)?->toDateString() ?? '',
            ]);
        }

        return $this->downloadCsv('psg-deployments-report', $headers, $data);
    }

    public function hr(Request $request): View
    {
        $this->authorizeReports();

        $filters = $this->hrFilters($request);

        return view('reports.hr', $this->reports->hr($filters) + [
            'filters' => $filters,
            'exportQuery' => $this->cleanQuery($filters),
        ]);
    }

    public function exportHr(Request $request): StreamedResponse
    {
        $this->authorizeReports();

        $filters = $this->hrFilters($request);
        $report = $this->reports->hr($filters);

        $headers = ['#', 'Category', 'Employment ID', 'Guard', 'Detail', 'Status', 'From / Date', 'To', 'Site'];
        $data = collect();
        $serial = 1;

        foreach ($report['leaves'] as $leave) {
            $data->push([
                $serial++,
                'Leave',
                $leave->assignedGuard?->employment_id,
                $leave->assignedGuard?->full_name,
                $leave->leave_type->label(),
                $leave->status->label(),
                $leave->start_date->toDateString(),
                $leave->end_date->toDateString(),
                '',
            ]);
        }

        foreach ($report['absences'] as $absence) {
            $data->push([
                $serial++,
                'Absence',
                $absence->assignedGuard?->employment_id,
                $absence->assignedGuard?->full_name,
                $absence->reason?->label() ?? '',
                'Recorded',
                $absence->absence_date->toDateString(),
                '',
                $absence->site?->name,
            ]);
        }

        foreach ($report['desertions'] as $desertion) {
            $data->push([
                $serial++,
                'Desertion',
                $desertion->assignedGuard?->employment_id,
                $desertion->assignedGuard?->full_name,
                '',
                $desertion->hr_status->label(),
                $desertion->date_reported->toDateString(),
                '',
                $desertion->lastKnownSite?->name,
            ]);
        }

        return $this->downloadCsv(
            'psg-hr-report-'.$filters['from'].'-to-'.$filters['to'],
            $headers,
            $data,
        );
    }

    /** @return array{year: int, month: int, region_id: int|null, site_id: int|null} */
    private function monthFilters(Request $request): array
    {
        return [
            'year' => (int) $request->input('year', now()->year),
            'month' => (int) $request->input('month', now()->month),
            'region_id' => $request->integer('region_id') ?: null,
            'site_id' => $request->integer('site_id') ?: null,
        ];
    }

    /** @return array{date: string, region_id: int|null, site_id: int|null} */
    private function dailyFilters(Request $request): array
    {
        return [
            'date' => $request->input('date', now()->toDateString()),
            'region_id' => $request->integer('region_id') ?: null,
            'site_id' => $request->integer('site_id') ?: null,
        ];
    }

    /** @return array{week_start: string, region_id: int|null, site_id: int|null} */
    private function weeklyFilters(Request $request): array
    {
        return [
            'week_start' => $request->input('week_start', now()->startOfWeek()->toDateString()),
            'region_id' => $request->integer('region_id') ?: null,
            'site_id' => $request->integer('site_id') ?: null,
        ];
    }

    /** @return array{current_only: bool, region_id: int|null, site_id: int|null, from: mixed, to: mixed} */
    private function deploymentFilters(Request $request): array
    {
        return [
            'current_only' => $request->boolean('current_only', true),
            'region_id' => $request->integer('region_id') ?: null,
            'site_id' => $request->integer('site_id') ?: null,
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ];
    }

    /** @return array{from: string, to: string} */
    private function hrFilters(Request $request): array
    {
        return [
            'from' => $request->input('from', now()->startOfMonth()->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function cleanQuery(array $filters): array
    {
        return array_filter($filters, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  list<string>  $headers
     * @param  iterable<int, list<string|int|float|null>>|\Illuminate\Support\Collection<int, list<string|int|float|null>>  $rows
     */
    private function downloadCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return $this->exports->downloadCsv($filename.'.csv', $headers, $rows);
    }

    private function authorizeReports(): void
    {
        Gate::authorize('viewReports');
    }
}
