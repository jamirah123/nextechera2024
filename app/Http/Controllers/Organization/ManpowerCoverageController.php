<?php

namespace App\Http\Controllers\Organization;

use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Models\Region;
use App\Models\Site;
use App\Services\ManpowerCoverageReportService;
use App\Services\ManpowerService;
use App\Services\ReportExportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManpowerCoverageController extends Controller
{
    public function __construct(
        private ManpowerService $manpower,
        private ManpowerCoverageReportService $report,
        private ReportExportService $exporter,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Site::class);

        $user = $request->user();
        $date = $request->filled('date') ? (string) $request->string('date') : null;
        $rows = $this->report->paginate($request);
        $regionFilter = $request->filled('region_id') ? $request->integer('region_id') : null;

        $summary = $date
            ? $this->manpower->forCompanyOnDate(
                $date,
                $user->mustStayInOwnRegion() ? $user->regionId() : $regionFilter,
            )
            : $this->manpower->forCompany();

        return view('organization.manpower.index', [
            'rows' => $rows,
            'company' => $summary,
            'dateMode' => filled($date),
            'coverageDate' => $date,
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
