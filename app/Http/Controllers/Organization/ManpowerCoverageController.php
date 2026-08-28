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
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Site::class);

        $user = $request->user();
        $rows = $this->report->paginate($request);

        return view('organization.manpower.index', [
            'rows' => $rows,
            'company' => $this->manpower->forCompany(),
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $user->regionId()))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'filters' => $request->only(['region_id', 'status']),
            'statuses' => SiteStatus::cases(),
            'exportQuery' => array_filter($request->only(['region_id', 'status']), fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', Site::class);

        $rows = $this->report->rows($request);

        return $this->exporter->downloadCsv(
            $this->report->filename($request, 'csv'),
            $this->report->headers(),
            $this->report->exportRows($rows),
        );
    }
}
