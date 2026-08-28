<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Finance\FinanceHistoryService;
use App\Services\Finance\PaymentService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $payments,
        private FinanceHistoryService $history,
        private ReportExportService $exports,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'month' ? 'month' : 'all';

        $payments = Payment::query()
            ->with(['client:id,name', 'invoice:id,reference', 'recorder:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('payment_date', $request->string('date')))
            ->when($scope === 'month', fn ($q) => $q->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year))
            ->latest('payment_date')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('finance.payments.index', [
            'payments' => $payments,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'client_id', 'date', 'scope']),
            'scope' => $scope,
            'canManage' => $request->user()->can('manageFinance'),
            'exportQuery' => array_filter($request->only(['q', 'client_id', 'date', 'scope']), fn ($v) => filled($v)),
            'stats' => [
                'today' => (float) Payment::query()->whereDate('payment_date', now()->toDateString())->sum('amount'),
                'month' => (float) Payment::query()
                    ->whereMonth('payment_date', now()->month)
                    ->whereYear('payment_date', now()->year)
                    ->sum('amount'),
                'all_time' => (float) Payment::query()->sum('amount'),
                'count' => Payment::query()->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manageFinance');

        $openInvoices = Invoice::query()
            ->open()
            ->with('client:id,name')
            ->orderBy('due_date')
            ->get();

        return view('finance.payments.create', [
            'invoices' => $openInvoices,
            'methods' => PaymentMethod::cases(),
            'selectedInvoiceId' => $request->integer('invoice_id') ?: null,
            'currency' => Money::currency(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'invoice_id' => ['required', 'exists:invoices,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $payment = $this->payments->record($data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['payment' => $e->getMessage()]);
        }

        return redirect()->route('payments.show', $payment)->with('status', 'Payment recorded.');
    }

    public function show(Payment $payment): View
    {
        Gate::authorize('viewFinance');

        $payment->load(['client', 'invoice', 'recorder', 'creator']);

        return view('finance.payments.show', [
            'payment' => $payment,
            'history' => $this->history->forSubject($payment),
            'recordMeta' => $this->history->recordMeta($payment),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'month' ? 'month' : 'all';

        $rows = Payment::query()
            ->with(['client:id,name', 'invoice:id,reference'])
            ->search($request->string('q')->toString())
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('payment_date', $request->string('date')))
            ->when($scope === 'month', fn ($q) => $q->whereMonth('payment_date', now()->month)->whereYear('payment_date', now()->year))
            ->latest('payment_date')
            ->limit(5000)
            ->get();

        $headers = ['#', 'Reference', 'Client', 'Invoice', 'Date', 'Amount', 'Method', 'External ref'];
        $data = $rows->values()->map(fn (Payment $p, int $i) => [
            $i + 1,
            $p->reference,
            $p->client?->name,
            $p->invoice?->reference,
            $p->payment_date?->format('Y-m-d'),
            $p->amount,
            $p->method->label(),
            $p->external_reference,
        ]);

        return $this->exports->downloadCsv('psg-payments.csv', $headers, $data);
    }
}
