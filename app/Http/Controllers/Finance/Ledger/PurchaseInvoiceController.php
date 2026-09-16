<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Enums\PurchaseInvoiceStatus;
use App\Http\Controllers\Controller;
use App\Models\GlAccount;
use App\Models\PurchaseInvoice;
use App\Services\Finance\Ledger\ChartOfAccountsService;
use App\Services\Finance\Ledger\PurchaseInvoiceService;
use App\Services\Finance\Ledger\VatPackService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private PurchaseInvoiceService $purchases,
        private ChartOfAccountsService $coa,
        private VatPackService $vat,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $bills = PurchaseInvoice::query()
            ->with('expenseAccount:id,code,name')
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('bill_date')
            ->latest('id')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('finance.ledger.purchases.index', [
            'bills' => $bills,
            'statuses' => PurchaseInvoiceStatus::cases(),
            'filters' => $request->only(['q', 'status']),
            'canManage' => $request->user()->can('manageFinance'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manageFinance');

        return view('finance.ledger.purchases.create', [
            'expenseAccounts' => GlAccount::query()->postable()->where('type', 'expense')->orderBy('code')->get(),
            'defaultExpenseId' => $this->coa->operatingExpense()->id,
            'vatRate' => $this->vat->defaultRate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $this->validated($request);

        try {
            $bill = $this->purchases->createDraft($data, $request->user());
            if ($request->boolean('post_now')) {
                $bill = $this->purchases->post($bill, $request->user());
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('ledger.purchases.show', $bill)
            ->with('status', 'Purchase '.$bill->reference.' saved.');
    }

    public function show(PurchaseInvoice $purchase): View
    {
        Gate::authorize('viewFinance');

        return view('finance.ledger.purchases.show', [
            'bill' => $purchase->load(['expenseAccount', 'poster']),
            'canManage' => request()->user()->can('manageFinance'),
        ]);
    }

    public function edit(PurchaseInvoice $purchase): View
    {
        Gate::authorize('manageFinance');
        abort_unless($purchase->isEditable(), 403);

        return view('finance.ledger.purchases.edit', [
            'bill' => $purchase,
            'expenseAccounts' => GlAccount::query()->postable()->where('type', 'expense')->orderBy('code')->get(),
            'vatRate' => $this->vat->defaultRate(),
        ]);
    }

    public function update(Request $request, PurchaseInvoice $purchase): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->purchases->updateDraft($purchase, $this->validated($request));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('ledger.purchases.show', $purchase)
            ->with('status', 'Purchase updated.');
    }

    public function post(Request $request, PurchaseInvoice $purchase): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->purchases->post($purchase, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()]);
        }

        return back()->with('status', 'Purchase posted to the ledger.');
    }

    public function cancel(Request $request, PurchaseInvoice $purchase): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->purchases->cancel($purchase, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()]);
        }

        return back()->with('status', 'Purchase cancelled.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'supplier_name' => ['required', 'string', 'max:160'],
            'supplier_tin' => ['nullable', 'string', 'max:40'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:80'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'subtotal' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'expense_account_id' => ['required', 'exists:gl_accounts,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
