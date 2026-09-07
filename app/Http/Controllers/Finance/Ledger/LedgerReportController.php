<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Http\Controllers\Controller;
use App\Models\GlPeriod;
use App\Services\Finance\Ledger\GlPeriodService;
use App\Services\Finance\Ledger\LedgerReportService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LedgerReportController extends Controller
{
    public function __construct(
        private LedgerReportService $reports,
        private GlPeriodService $periods,
        private ReportExportService $exports,
    ) {
    }

    public function trialBalance(Request $request): View|StreamedResponse
    {
        Gate::authorize('viewFinance');

        $this->periods->ensureRollingWindow();
        $period = $this->resolvePeriod($request);
        $report = $this->reports->trialBalance($period);

        if ($request->boolean('export')) {
            $rows = $report['rows']->values()->map(fn (array $row, int $i) => [
                $i + 1,
                $row['account']->code,
                $row['account']->name,
                $row['account']->type->label(),
                Money::format($row['debit']),
                Money::format($row['credit']),
            ]);

            return $this->exports->downloadCsv(
                'psg-trial-balance-'.$period->starts_on->format('Y-m').'.csv',
                ['#', 'Code', 'Account', 'Type', 'Debit', 'Credit'],
                $rows
            );
        }

        return view('finance.ledger.reports.trial-balance', [
            'report' => $report,
            'period' => $period,
            'periodOptions' => $this->periodOptions(),
        ]);
    }

    public function profitAndLoss(Request $request): View|StreamedResponse
    {
        Gate::authorize('viewFinance');

        $this->periods->ensureRollingWindow();
        $period = $this->resolvePeriod($request);
        $report = $this->reports->profitAndLoss($period);

        if ($request->boolean('export')) {
            $rows = collect();
            foreach ($report['revenue'] as $row) {
                $rows->push(['Revenue', $row['account']->code, $row['account']->name, Money::format($row['amount'])]);
            }
            foreach ($report['expenses'] as $row) {
                $rows->push(['Expense', $row['account']->code, $row['account']->name, Money::format($row['amount'])]);
            }
            $rows->push(['Net', '', '', Money::format($report['net'])]);

            return $this->exports->downloadCsv(
                'psg-profit-loss-'.$period->starts_on->format('Y-m').'.csv',
                ['Section', 'Code', 'Account', 'Amount'],
                $rows
            );
        }

        return view('finance.ledger.reports.profit-loss', [
            'report' => $report,
            'period' => $period,
            'periodOptions' => $this->periodOptions(),
        ]);
    }

    private function resolvePeriod(Request $request): GlPeriod
    {
        if ($request->filled('period_id')) {
            return GlPeriod::query()->findOrFail((int) $request->input('period_id'));
        }

        return $this->periods->ensureForDate(now());
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, GlPeriod> */
    private function periodOptions()
    {
        return GlPeriod::query()->orderByDesc('year')->orderByDesc('month')->limit(24)->get();
    }
}
