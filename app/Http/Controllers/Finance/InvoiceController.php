<?php

namespace App\Http\Controllers\Finance;

use App\Enums\InvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Site;
use App\Services\Finance\FinanceHistoryService;
use App\Services\Finance\InvoiceService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends Controller
{
    public function __construct(
        private InvoiceService $invoices,
        private FinanceHistoryService $history,
        private ReportExportService $exports,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');
        $this->invoices->markOverdueInvoices();

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

        return view('finance.invoices.index', [
            'invoices' => $invoices,
            'statuses' => InvoiceStatus::cases(),
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'status', 'client_id', 'scope']),
            'scope' => $scope,
            'canManage' => $request->user()->can('manageFinance'),
            'exportQuery' => array_filter($request->only(['q', 'status', 'client_id', 'scope']), fn ($v) => filled($v)),
            'stats' => [
                'draft' => Invoice::query()->where('status', InvoiceStatus::Draft)->count(),
                'open' => Invoice::query()->open()->count(),
                'overdue' => Invoice::query()->where('status', InvoiceStatus::Overdue)->count(),
                'paid' => Invoice::query()->where('status', InvoiceStatus::Paid)->count(),
                'all_time' => Invoice::withTrashed()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageFinance');

        return view('finance.invoices.create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'client_id']),
            'currency' => Money::currency(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_date' => ['nullable', 'date'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'auto_generate' => ['sometimes', 'boolean'],
            'lines' => ['nullable', 'array'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:255'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.site_id' => ['nullable', 'exists:sites,id'],
        ]);

        $data['auto_generate'] = $request->boolean('auto_generate');
        $data['lines'] = array_values(array_filter($data['lines'] ?? [], fn ($line) => filled($line['description'] ?? null)));

        try {
            $invoice = $this->invoices->createDraft($data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['invoice' => $e->getMessage()]);
        }

        return redirect()->route('invoices.show', $invoice)->with('status', 'Draft invoice created.');
    }

    public function show(Invoice $invoice): View
    {
        Gate::authorize('viewFinance');

        $invoice->load(['client', 'site', 'lines.site', 'payments.recorder', 'approver', 'creator', 'updater']);

        return view('finance.invoices.show', [
            'invoice' => $invoice,
            'canManage' => request()->user()->can('manageFinance'),
            'history' => $this->history->forSubject($invoice),
            'recordMeta' => $this->history->recordMeta($invoice),
        ]);
    }

    public function edit(Invoice $invoice): View
    {
        Gate::authorize('manageFinance');

        if (! $invoice->isEditable()) {
            abort(403, 'Only draft invoices can be edited.');
        }

        $invoice->load('lines');

        return view('finance.invoices.edit', [
            'invoice' => $invoice,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'client_id']),
            'currency' => Money::currency(),
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'due_date' => ['nullable', 'date'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.site_id' => ['nullable', 'exists:sites,id'],
        ]);

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

        return back()->with('status', 'Invoice issued.');
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
}
