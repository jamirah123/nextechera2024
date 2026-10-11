<?php

namespace App\Http\Controllers\Finance;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Concerns\ServesPdfDownload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreInvoiceDraftRequest;
use App\Http\Requests\Finance\UpdateInvoiceDraftRequest;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Services\EntityTimelineService;
use App\Services\Finance\FinanceHistoryService;
use App\Services\Finance\InvoicePdfService;
use App\Services\Finance\InvoiceService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    use ServesPdfDownload;

    public function __construct(
        private InvoiceService $invoices,
        private FinanceHistoryService $history,
        private EntityTimelineService $timeline,
        private ReportExportService $exports,
        private InvoicePdfService $pdf,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'current';

        $invoices = Invoice::query()
            ->when($scope === 'all', fn ($q) => $q->withTrashed())
            ->with(['client:id,name', 'site:id,name,code', 'creator:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($scope === 'current', fn ($q) => $q->whereNotIn('status', [
                InvoiceStatus::Paid->value,
                InvoiceStatus::Cancelled->value,
            ]))
            ->latest('id')
            ->paginate(table_per_page())
            ->withQueryString();

        $invoiceStatusCounts = status_counts(Invoice::query());

        return view('finance.invoices.index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::cases(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'status', 'client_id', 'scope']),
            'scope' => $scope,
            'canManage' => $request->user()->can('manageFinance'),
            'exportQuery' => array_filter($request->only(['q', 'status', 'client_id', 'scope']), fn ($v) => filled($v)),
            'stats' => [
                'draft' => (int) ($invoiceStatusCounts[InvoiceStatus::Draft->value] ?? 0),
                'open' => Invoice::query()->open()->count(),
                'overdue' => (int) ($invoiceStatusCounts[InvoiceStatus::Overdue->value] ?? 0),
                'paid' => (int) ($invoiceStatusCounts[InvoiceStatus::Paid->value] ?? 0),
                'all_time' => Invoice::withTrashed()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageFinance');

        $profiles = BillingProfile::query()
            ->approved()
            ->with(['client:id,name', 'site:id,name,code'])
            ->orderBy('id')
            ->get();

        $standings = BillingProfile::standingsFor($profiles);
        $profiles = $profiles
            ->sort(function (BillingProfile $left, BillingProfile $right): int {
                $byDate = ($right->effective_from?->timestamp ?? 0) <=> ($left->effective_from?->timestamp ?? 0);

                return $byDate !== 0 ? $byDate : ($right->id <=> $left->id);
            })
            ->values();

        return view('finance.invoices.create', [
            'profiles' => $profiles->map(fn (BillingProfile $profile) => $this->profileOption($profile, $standings[$profile->id] ?? 'ended'))->values(),
            'currency' => Money::currency(),
            'vatRate' => (float) config('psg.vat_rate', 18),
            'dueDays' => (int) config('psg.invoice_due_days', 14),
        ]);
    }

    public function store(StoreInvoiceDraftRequest $request): RedirectResponse
    {
        try {
            $invoice = $this->invoices->createDraft($request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice created successfully.');
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('viewFinance');

        $invoice->load(['client', 'site', 'billingProfile.site', 'lines.site', 'payments.recorder', 'approver', 'creator', 'updater']);

        return view('finance.invoices.show', array_merge($this->pdf->viewData($invoice), [
            'canManage' => request()->user()->can('manageFinance'),
            'history' => $this->history->forSubject($invoice),
            'recordMeta' => $this->history->recordMeta($invoice),
            'timeline' => $this->timeline->for($invoice, request()->user()),
            'lifecycle' => [
                'steps' => InvoiceStatus::lifecycleSteps(),
                'current' => max(0, $invoice->status->lifecycleStep()),
                'terminal' => $invoice->status === InvoiceStatus::Cancelled ? 'Cancelled' : ($invoice->status === InvoiceStatus::Overdue ? 'Overdue' : null),
                'terminal_tone' => $invoice->status === InvoiceStatus::Overdue ? 'rose' : 'slate',
            ],
        ]));
    }

    public function edit(Invoice $invoice): View
    {
        Gate::authorize('manageFinance');

        if (! $invoice->isEditable()) {
            abort(403, 'Only draft invoices can be edited.');
        }

        $invoice->load('lines');

        $invoice->loadMissing('billingProfile.site', 'client', 'site');

        return view('finance.invoices.edit', [
            'invoice' => $invoice,
            'currency' => Money::currency(),
        ]);
    }

    public function update(UpdateInvoiceDraftRequest $request, Invoice $invoice): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->invoices->updateDraft($invoice, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice updated.');
    }

    public function issue(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->invoices->issue($invoice);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', 'Invoice issued successfully.');
    }

    public function cancel(Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->invoices->cancel($invoice);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('status', 'Invoice cancelled.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'current';

        $rows = Invoice::query()
            ->when($scope === 'all', fn ($q) => $q->withTrashed())
            ->with(['client:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($scope === 'current', fn ($q) => $q->whereNotIn('status', [
                InvoiceStatus::Paid->value,
                InvoiceStatus::Cancelled->value,
            ]))
            ->latest('id')
            ->limit(5000)
            ->get();

        $headers = ['#', 'Reference', 'Client', 'Period', 'Status', 'Total', 'Paid', 'Balance', 'Due', 'Issued'];
        $data = $rows->values()->map(fn (Invoice $inv, int $i) => [
            $i + 1,
            $inv->reference,
            $inv->client?->name,
            $inv->period_start?->format('Y-m-d').' to '.$inv->period_end?->format('Y-m-d'),
            $inv->status->label(),
            $inv->total,
            $inv->amount_paid,
            $inv->balance,
            $inv->due_date?->format('Y-m-d'),
            $inv->issue_date?->format('Y-m-d'),
        ]);

        return $this->exports->downloadCsv('psg-invoices.csv', $headers, $data);
    }

    public function exportDocument(Invoice $invoice): StreamedResponse
    {
        Gate::authorize('viewFinance');

        $invoice->load(['client', 'site', 'lines', 'payments', 'approver', 'creator']);

        $headers = ['Description', 'Quantity', 'Unit price', 'Line total'];
        $lineRows = $invoice->lines->map(fn ($line) => [
            $line->description,
            $line->quantity,
            $line->unit_price,
            $line->line_total,
        ]);

        $data = $lineRows->concat([
            ['', '', 'Subtotal', $invoice->subtotal],
            ['', '', 'Tax', $invoice->tax_amount],
            ['', '', 'Total', $invoice->total],
            ['', '', 'Paid', $invoice->amount_paid],
            ['', '', 'Balance', $invoice->balance],
        ]);

        return $this->exports->downloadCsv('psg-invoice-'.$invoice->reference.'.csv', $headers, $data);
    }

    public function downloadPdf(Invoice $invoice): Response
    {
        Gate::authorize('viewFinance');

        $invoice->load(['client', 'site', 'lines']);

        return $this->pdfDownload(
            $this->pdf->renderBinary($invoice),
            $this->pdf->filename($invoice),
        );
    }

    /** @return array<string, mixed> */
    private function profileOption(BillingProfile $profile, string $standing): array
    {
        $treatment = $profile->vatTreatment();
        $rate = $treatment->chargesVat() ? (float) config('psg.vat_rate', 18) : 0;
        $period = $profile->invoicePeriod($standing);
        $billable = $period !== null;

        return [
            'id' => $profile->id,
            'label' => trim(($profile->client?->name ?? 'Client').' — '.$profile->scopeLabel().' — '.($profile->billing_mode?->label() ?? 'Monthly')),
            'client' => $profile->client?->name ?? '—',
            'scope' => $profile->scopeLabel(),
            'mode' => $profile->billing_mode?->label() ?? 'Monthly day / night posts',
            'vat' => $treatment->label().($treatment->chargesVat() ? ' · '.$rate.'%' : ''),
            'cash' => (bool) $profile->cash_no_tax,
            'posts' => 'Day '.(int) $profile->contracted_day_armed_guards.' armed / '.(int) $profile->contracted_day_unarmed_guards.' unarmed · Night '.(int) $profile->contracted_night_armed_guards.' armed / '.(int) $profile->contracted_night_unarmed_guards.' unarmed',
            'monthly_armed' => (float) $profile->monthlyArmedRate(),
            'monthly_unarmed' => (float) $profile->monthlyUnarmedRate(),
            'shift_armed_day' => (float) $profile->rate_per_armed_day_shift,
            'shift_armed_night' => (float) $profile->rate_per_armed_night_shift,
            'shift_unarmed_day' => (float) $profile->rate_per_unarmed_day_shift,
            'shift_unarmed_night' => (float) $profile->rate_per_unarmed_night_shift,
            'uses_monthly' => $profile->billing_mode?->usesMonthlyRates() ?? true,
            'uses_shift' => $profile->billing_mode?->usesShiftRates() ?? false,
            'effective' => ($profile->effective_from?->format('d M Y') ?? '—').' – '.($profile->effective_to?->format('d M Y') ?? 'Open'),
            'standing' => $standing,
            'standing_label' => BillingProfile::standingLabel($standing),
            'billable' => $billable,
            'period' => $period === null
                ? ''
                : \Illuminate\Support\Carbon::parse($period[0])->format('d M Y').' – '.\Illuminate\Support\Carbon::parse($period[1])->format('d M Y'),
            'due_on' => $period === null
                ? ''
                : \Illuminate\Support\Carbon::parse($period[1])->addDays((int) config('psg.invoice_due_days', 14))->toDateString(),
            'block_reason' => $billable ? '' : BillingProfile::standingMessage($profile, $standing),
        ];
    }
}
