<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\ProfitabilityService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
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
            'tables' => [
                ['title' => 'By client', 'rows' => $this->paginateRows($report['by_client'], $request, 'client_page')],
                ['title' => 'By site', 'rows' => $this->paginateRows($report['by_site'], $request, 'site_page')],
                ['title' => 'By region', 'rows' => $this->paginateRows($report['by_region'], $request, 'region_page')],
            ],
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

        $headers = ['Section', 'Name', 'Code', 'Revenue', 'Payroll cost', 'Cost basis', 'Profit', 'Margin %'];
        $data = collect();

        $formatRow = fn (array $row) => [
            $row['label'],
            $row['code'] ?? '',
            $row['revenue'],
            $row['cost'],
            $row['cost_source'] === 'actual' ? 'Paid payroll' : 'Estimated',
            $row['profit'],
            $row['margin'],
        ];

        foreach ($report['by_client'] as $row) {
            $data->push(array_merge(['Client'], $formatRow($row)));
        }
        foreach ($report['by_site'] as $row) {
            $data->push(array_merge(['Site'], $formatRow($row)));
        }
        foreach ($report['by_region'] as $row) {
            $data->push(array_merge(['Region'], $formatRow($row)));
        }

        return $this->exports->downloadCsv('psg-profitability-'.$from.'-'.$to.'.csv', $headers, $data);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateRows(array $rows, Request $request, string $pageName): LengthAwarePaginator
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
            ]
        ))->appends($request->except($pageName));
    }
}
