<?php

namespace App\Http\Controllers\Organization;

use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\ResolveManpowerGapOvertimeRequest;
use App\Models\Deployment;
use App\Models\ManpowerGap;
use App\Models\Region;
use App\Models\Site;
use App\Services\ManpowerCoverageReportService;
use App\Services\ManpowerGapService;
use App\Services\ManpowerService;
use App\Services\ReportExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManpowerCoverageController extends Controller
{
    public function __construct(
        private ManpowerService $manpower,
        private ManpowerCoverageReportService $report,
        private ManpowerGapService $gaps,
        private ReportExportService $exporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Site::class);

        $user = $request->user();
        $date = $request->filled('date') ? (string) $request->string('date') : null;
        $rows = $this->report->paginate($request);
        $regionFilter = $request->filled('region_id') ? $request->integer('region_id') : null;
        $resolvedRegion = $user->mustStayInOwnRegion() ? $user->regionId() : $regionFilter;

        $summary = $date
            ? $this->manpower->forCompanyOnDate($date, $resolvedRegion)
            : $this->manpower->forCompany();

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
            'company' => $summary,
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
}
