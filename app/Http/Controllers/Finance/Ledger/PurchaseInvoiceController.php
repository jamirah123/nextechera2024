<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Enums\PurchaseInvoiceStatus;
use App\Enums\VatTreatment;
use App\Http\Controllers\Controller;
use App\Models\GlAccount;
use App\Models\PurchaseInvoice;
use App\Models\Supplier;
use App\Services\Finance\Ledger\ChartOfAccountsService;
use App\Services\Finance\Ledger\PurchaseInvoiceService;
use App\Services\Finance\Ledger\VatPackService;
use App\Support\Attachments\InlineAttachmentResponse;
use App\Support\Attachments\StoredFilePreview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private PurchaseInvoiceService $purchases,
        private ChartOfAccountsService $coa,
        private VatPackService $vat,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewPurchases');

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
            'canManage' => $request->user()->can('managePurchases'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('managePurchases');

        return view('finance.ledger.purchases.create', [
            'expenseAccounts' => GlAccount::query()->postable()->where('type', 'expense')->orderBy('code')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'tin']),
            'treatments' => VatTreatment::cases(),
            'defaultExpenseId' => $this->coa->operatingExpense()->id,
            'vatRate' => $this->vat->defaultRate(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('managePurchases');

        $data = $this->validated($request);

        try {
            $bill = $this->purchases->createDraft($data, $request->user());
            if ($request->boolean('post_now')) {
                $bill = $this->purchases->post($bill, $request->user());
            }
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()])->withInput();
        }

        $message = $bill->status === PurchaseInvoiceStatus::Posted
            ? 'Supplier bill posted successfully.'
            : 'Draft supplier bill saved successfully.';

        return redirect()
            ->route('ledger.purchases.show', $bill)
            ->with('status', $message);
    }

    public function show(PurchaseInvoice $purchase): View
    {
        Gate::authorize('viewPurchases');

        return view('finance.ledger.purchases.show', [
            'bill' => $purchase->load(['expenseAccount', 'poster', 'supplier', 'canceller', 'creator']),
            'canManage' => request()->user()->can('managePurchases'),
        ]);
    }

    public function edit(PurchaseInvoice $purchase): View
    {
        Gate::authorize('managePurchases');
        abort_unless($purchase->isEditable(), 403);

        return view('finance.ledger.purchases.edit', [
            'bill' => $purchase,
            'expenseAccounts' => GlAccount::query()->postable()->where('type', 'expense')->orderBy('code')->get(),
            'suppliers' => Supplier::query()
                ->where(function ($query) use ($purchase): void {
                    $query->where('is_active', true);
                    if ($purchase->supplier_id) {
                        $query->orWhere('id', $purchase->supplier_id);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'name', 'tin']),
            'treatments' => VatTreatment::cases(),
            'vatRate' => $this->vat->defaultRate(),
        ]);
    }

    public function update(Request $request, PurchaseInvoice $purchase): RedirectResponse
    {
        Gate::authorize('managePurchases');

        try {
            $this->purchases->updateDraft($purchase, $this->validated($request));
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('ledger.purchases.show', $purchase)
            ->with('status', 'Draft supplier bill saved successfully.');
    }

    public function post(Request $request, PurchaseInvoice $purchase): RedirectResponse
    {
        Gate::authorize('managePurchases');

        try {
            $this->purchases->post($purchase, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()]);
        }

        return back()->with('status', 'Supplier bill posted successfully.');
    }

    public function cancel(Request $request, PurchaseInvoice $purchase): RedirectResponse
    {
        Gate::authorize('managePurchases');

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->purchases->cancel($purchase, $request->user(), $data['reason']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['purchase' => $e->getMessage()]);
        }

        return back()->with('status', 'Supplier bill cancelled and reversal recorded successfully.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'supplier_name' => ['required_without:supplier_id', 'nullable', 'string', 'max:160'],
            'supplier_tin' => ['nullable', 'string', 'max:40'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:80'],
            'bill_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'vat_treatment' => ['nullable', Rule::enum(VatTreatment::class)],
            'subtotal' => ['required', 'numeric', 'min:0.01'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'expense_account_id' => ['required', 'exists:gl_accounts,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ], [
            'attachment.max' => 'The invoice file must be 10 MB or smaller.',
            'attachment.uploaded' => 'The invoice file must be 10 MB or smaller.',
        ]);

        if ($request->hasFile('attachment')) {
            $data['attachment_path'] = $request->file('attachment')->store('procurement/bills', 'local');
        }

        unset($data['attachment']);

        return $data;
    }

    public function viewAttachment(PurchaseInvoice $purchase): View
    {
        Gate::authorize('viewPurchases');

        return view('finance.ledger.purchases.attachment', [
            'bill' => $purchase,
            'attachment' => $this->invoiceFile($purchase),
        ]);
    }

    public function streamAttachment(PurchaseInvoice $purchase): StreamedResponse
    {
        Gate::authorize('viewPurchases');

        return (new InlineAttachmentResponse($this->invoiceFile($purchase)))->toResponse(request());
    }

    public function attachment(PurchaseInvoice $purchase): StreamedResponse
    {
        Gate::authorize('viewPurchases');
        $file = $this->invoiceFile($purchase);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }

    private function invoiceFile(PurchaseInvoice $purchase): StoredFilePreview
    {
        abort_unless($purchase->attachment_path && Storage::disk('local')->exists($purchase->attachment_path), 404);

        $purchase->loadMissing('creator');

        return StoredFilePreview::fromPath(
            $purchase->attachment_path,
            $purchase->reference.' invoice',
            $purchase->creator,
        );
    }
}
