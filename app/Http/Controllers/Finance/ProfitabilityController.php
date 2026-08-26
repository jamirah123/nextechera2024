<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\ProfitabilityService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfitabilityController extends Controller
{
    public function __construct(
        private ProfitabilityService $profitability,
        private ReportExportService $exports,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->endOfMonth()->toDateString();
        $report = $this->profitability->analyze($from, $to);

        return view('finance.profitability.index', [
            'report' => $report,
            'filters' => ['from' => $from, 'to' => $to],
            'exportQuery' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewFinance');

        $from = $request->string('from')->toString() ?: now()->startOfMonth()->toDateString();
        $to = $request->string('to')->toString() ?: now()->endOfMonth()->toDateString();
        $report = $this->profitability->analyze($from, $to);

        $headers = ['Section', 'Name', 'Code', 'Revenue', 'Est. cost', 'Profit', 'Margin %'];
        $data = collect();

        foreach ($report['by_client'] as $row) {
            $data->push(['Client', $row['label'], $row['code'] ?? '', $row['revenue'], $row['cost'], $row['profit'], $row['margin']]);
        }
        foreach ($report['by_site'] as $row) {
            $data->push(['Site', $row['label'], $row['code'] ?? '', $row['revenue'], $row['cost'], $row['profit'], $row['margin']]);
        }
        foreach ($report['by_region'] as $row) {
            $data->push(['Region', $row['label'], $row['code'] ?? '', $row['revenue'], $row['cost'], $row['profit'], $row['margin']]);
        }

        return $this->exports->downloadCsv('psg-profitability-'.$from.'-'.$to.'.csv', $headers, $data);
    }
}
