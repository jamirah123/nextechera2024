<?php

namespace App\Http\Controllers\Organization;

use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ResolveManpowerGapOvertimeRequest;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\ManpowerGap;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\SystemSetting;
use App\Services\ManpowerCoverageReportService;
use App\Services\ManpowerGapService;
use App\Services\ManpowerMonitorService;
use App\Services\ReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManpowerCoverageController extends Controller
{
    public function __construct(
        private ManpowerCoverageReportService $report,
        private ManpowerGapService $gaps,
        private ReportExportService $exporter,
        private ManpowerMonitorService $monitor,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Site::class);

        $user = $request->user();
        $date = $request->filled('date') ? (string) $request->string('date') : null;
        $rows = $this->report->paginate($request);
        $regionFilter = $request->filled('region_id') ? $request->integer('region_id') : null;
        $resolvedRegion = $user->mustStayInOwnRegion() ? $user->regionId() : $regionFilter;

        $gapSummary = null;
        $gapRows = collect();
        $gapGuardOptions = [];
        $canResolveGaps = $user->can('create', Deployment::class);

        if ($date) {
            $sitesToSync = $rows->getCollection()->pluck('site')->filter();
            foreach ($sitesToSync as $site) {
                $this->gaps->syncSiteDate($site, $date);
            }

            $gapRows = $this->gaps->forDate($date, $resolvedRegion)->load([
                'overtimeDeployments.assignedGuard:id,employment_id,full_name',
            ]);
            $gapSummary = $this->gaps->summaryForDate($date, $resolvedRegion);

            $gapGuardOptions = [];
            if ($canResolveGaps) {
                foreach ($gapRows->filter(fn (ManpowerGap $g) => $g->isOpen()) as $openGap) {
                    $gapGuardOptions[$openGap->id] = $this->gaps->availableGuardsForGap($openGap);
                }
            }
        }

        return view('organization.manpower.index', [
            'rows' => $rows,
            'dateMode' => filled($date),
            'coverageDate' => $date,
            'gapSummary' => $gapSummary,
            'gapRows' => $gapRows,
            'gapGuardOptions' => $gapGuardOptions ?? [],
            'canResolveGaps' => $canResolveGaps,
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $user->regionId()))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'filters' => $request->only(['region_id', 'status', 'date']),
            'statuses' => SiteStatus::cases(),
            'exportQuery' => array_filter(
                $request->only(['region_id', 'status', 'date']),
                fn ($v) => $v !== null && $v !== ''
            ),
            'overview' => $this->monitor->overview($resolvedRegion, $date ?? now()->toDateString()),
            'trends' => $this->monitor->trends($resolvedRegion, $date ?? now()->toDateString()),
            'monitorRules' => $this->monitor->rules(),
            'canConfigureMonitor' => $user->can('update', SystemSetting::class),
        ]);
    }

    public function resolveOvertime(
        ResolveManpowerGapOvertimeRequest $request,
        ManpowerGap $gap,
    ): RedirectResponse {
        $this->authorize('create', Deployment::class);

        try {
            $result = $this->gaps->resolveWithOvertime($gap, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['guard_id' => $e->getMessage()])->withInput();
        }

        $fresh = $result['gap'];

        return redirect()
            ->route('manpower.coverage', [
                'date' => $fresh->gap_date->toDateString(),
                'region_id' => $request->query('region_id'),
            ])
            ->with(
                'status',
                'Overtime coverage applied to '.$fresh->reference()
                .'. Original shortage '.$fresh->original_shortage
                .'; remaining '.$fresh->remaining_shortage.'.'
            );
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Site::class);

        $rows = $this->report->rows($request);

        return $this->exporter->downloadCsv(
            $this->report->filename($request, 'csv'),
            $this->report->headers($request),
            $this->report->exportRows($rows, $request),
        );
    }

    public function report(Request $request): View
    {
        $this->authorize('viewAny', Site::class);

        $filters = $this->reportFilters($request);
        $rows = $this->paginateReportRows($this->monitor->siteReport($filters), $request, 'page');
        $guards = $this->paginateReportRows($this->filteredGuardFlags($filters), $request, 'guards');

        return view('organization.manpower.report', [
            'rows' => $rows,
            'guards' => $guards,
            'filters' => $filters,
            'trends' => $this->monitor->trends($filters['region_id'], $filters['date']),
            'regions' => $this->regionOptions($request),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'supervisors' => Supervisor::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()
                ->where('status', SiteStatus::Active)
                ->when($request->user()->mustStayInOwnRegion(), fn ($query) => $query->where('region_id', $request->user()->regionId()))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function exportReport(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Site::class);

        $filters = $this->reportFilters($request);

        if ($request->string('section')->toString() === 'guards') {
            $guards = $this->filteredGuardFlags($filters);

            return $this->exporter->downloadCsv(
                'manpower-guard-overtime-'.$filters['date'].'.csv',
                ['Guard', 'Code', 'Region', 'OT shifts', 'Consecutive shifts', 'Consecutive OT', 'Hours', 'Shortest rest (h)', 'Reasons'],
                array_map(fn (array $flag) => [
                    $flag['guard'],
                    $flag['code'],
                    $flag['region'],
                    $flag['ot_shifts'],
                    $flag['consecutive_shifts'],
                    $flag['consecutive_ot'],
                    $flag['hours'],
                    $flag['shortest_rest'] ?? '',
                    implode('; ', $flag['reasons']),
                ], $guards),
            );
        }

        $rows = $this->monitor->siteReport($filters);

        return $this->exporter->downloadCsv(
            'manpower-deficit-'.$filters['date'].'.csv',
            ['Site', 'Code', 'Region', 'Client', 'Supervisor', 'Shift', 'Required', 'Normal', 'OT', 'Total deployed', 'Remaining shortage', 'Manpower deficit', 'OT shifts', 'Status'],
            array_map(fn (array $row) => [
                $row['site'],
                $row['code'],
                $row['region'],
                $row['client'],
                $row['supervisor'],
                $row['period'],
                $row['required'],
                $row['normal'],
                $row['ot'],
                $row['deployed'],
                $row['remaining'],
                $row['deficit'],
                $row['ot_shifts'],
                $row['status'],
            ], $rows),
        );
    }

    public function updateMonitor(Request $request): RedirectResponse
    {
        $this->authorize('update', SystemSetting::class);

        $validated = $request->validate([
            'period_days' => ['required', 'integer', 'min:1', 'max:90'],
            'min_rest_hours' => ['required', 'integer', 'min:0', 'max:48'],
            'max_consecutive_shifts' => ['required', 'integer', 'min:1', 'max:30'],
            'max_consecutive_ot' => ['required', 'integer', 'min:1', 'max:30'],
            'max_ot_shifts' => ['required', 'integer', 'min:1', 'max:90'],
            'max_hours' => ['required', 'integer', 'min:1', 'max:400'],
            'site_ot_shift_alert' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        $this->monitor->saveRules($validated);

        return back()->with('status', 'Manpower monitoring thresholds saved.');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateReportRows(array $rows, Request $request, string $pageName): LengthAwarePaginator
    {
        $perPage = table_per_page();
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $items = collect($rows);

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => $pageName,
            ],
        ))->withQueryString();
    }

    /** @return array{date: string, region_id: int|null, client_id: int|null, supervisor_id: int|null, site_id: int|null, period: string|null, guard: string|null} */
    private function reportFilters(Request $request): array
    {
        $user = $request->user();
        $regionId = $request->filled('region_id') ? $request->integer('region_id') : null;
        if ($user->mustStayInOwnRegion()) {
            $regionId = $user->regionId();
        }

        $period = $request->string('period')->toString();

        return [
            'date' => $request->filled('date') ? (string) $request->string('date') : now()->toDateString(),
            'region_id' => $regionId,
            'client_id' => $request->filled('client_id') ? $request->integer('client_id') : null,
            'supervisor_id' => $request->filled('supervisor_id') ? $request->integer('supervisor_id') : null,
            'site_id' => $request->filled('site_id') ? $request->integer('site_id') : null,
            'period' => in_array($period, ['day', 'night'], true) ? $period : null,
            'guard' => $request->filled('guard') ? trim((string) $request->string('guard')) : null,
        ];
    }

    /** @param  array{date: string, region_id: int|null, guard: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredGuardFlags(array $filters): array
    {
        $flags = $this->monitor->guardFlags($filters['date'], $filters['region_id'], 200);
        $term = mb_strtolower((string) ($filters['guard'] ?? ''));
        if ($term === '') {
            return $flags;
        }

        return array_values(array_filter($flags, function (array $flag) use ($term): bool {
            return str_contains(mb_strtolower($flag['guard'].' '.$flag['code']), $term);
        }));
    }

    private function regionOptions(Request $request)
    {
        $user = $request->user();

        return Region::query()
            ->when($user->mustStayInOwnRegion(), fn ($query) => $query->where('id', $user->regionId()))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    }
}
