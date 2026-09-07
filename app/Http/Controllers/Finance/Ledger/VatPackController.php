<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Http\Controllers\Controller;
use App\Models\GlPeriod;
use App\Services\Finance\Ledger\GlPeriodService;
use App\Services\Finance\Ledger\VatPackService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VatPackController extends Controller
{
    public function __construct(
        private VatPackService $vat,
        private GlPeriodService $periods,
        private ReportExportService $exports,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $this->periods->ensureRollingWindow();

        $period = $this->resolvePeriod($request);
        $pack = $this->vat->returnForPeriod($period);

        $periodOptions = GlPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(24)
            ->get();

        return view('finance.ledger.vat.index', [
            'pack' => $pack,
            'period' => $period,
            'periodOptions' => $periodOptions,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewFinance');

        $period = $this->resolvePeriod($request);
        $pack = $this->vat->returnForPeriod($period);

        $headers = ['#', 'Issue date', 'Invoice', 'Client', 'Taxable', 'VAT', 'Total', 'Status'];
        $rows = $pack['invoices']->values()->map(fn ($invoice, int $i) => [
            $i + 1,
            optional($invoice->issue_date)?->toDateString(),
            $invoice->reference,
            $invoice->client?->name,
            Money::format($invoice->subtotal),
            Money::format($invoice->tax_amount),
            Money::format($invoice->total),
            $invoice->status->label(),
        ]);

        return $this->exports->downloadCsv(
            'psg-vat-return-'.$period->starts_on->format('Y-m').'.csv',
            $headers,
            $rows
        );
    }

    private function resolvePeriod(Request $request): GlPeriod
    {
        if ($request->filled('period_id')) {
            return GlPeriod::query()->findOrFail((int) $request->input('period_id'));
        }

        return $this->periods->ensureForDate(now());
    }
}
