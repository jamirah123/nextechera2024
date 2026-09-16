<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Enums\BankStatementLineStatus;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\GlAccount;
use App\Models\Payment;
use App\Services\Finance\Ledger\BankAccountService;
use App\Services\Finance\Ledger\BankReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BankReconciliationController extends Controller
{
    public function __construct(
        private BankReconciliationService $bank,
        private BankAccountService $accounts,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $accounts = BankAccount::query()
            ->with('glAccount')
            ->withCount(['statementLines as unmatched_count' => fn ($q) => $q->unmatched()])
            ->orderBy('name')
            ->get();

        return view('finance.ledger.bank.index', [
            'accounts' => $accounts,
            'canManage' => $request->user()->can('manageFinance'),
            'glAccounts' => GlAccount::query()->postable()->where('type', 'asset')->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:80'],
            'opening_balance' => ['nullable', 'numeric'],
            'gl_account_id' => ['nullable', 'exists:gl_accounts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $account = $this->accounts->create($data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['gl_account_id' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('ledger.bank.show', $account)
            ->with('status', 'Bank account created.');
    }

    public function show(Request $request, BankAccount $bankAccount): View
    {
        Gate::authorize('viewFinance');

        $lines = $bankAccount->statementLines()
            ->with(['matchedPayment:id,reference,amount,payment_date'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->paginate(table_per_page())
            ->withQueryString();

        $candidatePayments = Payment::query()
            ->with(['invoice:id,reference', 'client:id,name', 'payrollRun:id,reference'])
            ->whereDoesntHave('matchedStatementLines')
            ->latest('payment_date')
            ->limit(100)
            ->get();

        return view('finance.ledger.bank.show', [
            'account' => $bankAccount->load('glAccount'),
            'lines' => $lines,
            'summary' => $this->bank->summary($bankAccount),
            'statuses' => BankStatementLineStatus::cases(),
            'filters' => $request->only(['status']),
            'candidatePayments' => $candidatePayments,
            'canManage' => $request->user()->can('manageFinance'),
        ]);
    }

    public function storeLine(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'transaction_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric'],
            'external_reference' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->bank->addStatementLine($bankAccount, $data);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Statement line added.');
    }

    public function match(Request $request, BankAccount $bankAccount, BankStatementLine $line): RedirectResponse
    {
        Gate::authorize('manageFinance');

        abort_unless($line->bank_account_id === $bankAccount->id, 404);

        $data = $request->validate([
            'payment_id' => ['required', 'exists:payments,id'],
        ]);

        try {
            $this->bank->matchToPayment($line, Payment::query()->findOrFail($data['payment_id']), $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['payment_id' => $e->getMessage()]);
        }

        return back()->with('status', 'Statement line matched.');
    }

    public function unmatch(Request $request, BankAccount $bankAccount, BankStatementLine $line): RedirectResponse
    {
        Gate::authorize('manageFinance');
        abort_unless($line->bank_account_id === $bankAccount->id, 404);

        $this->bank->unmatch($line, $request->user());

        return back()->with('status', 'Statement line unmatched.');
    }

    public function exclude(Request $request, BankAccount $bankAccount, BankStatementLine $line): RedirectResponse
    {
        Gate::authorize('manageFinance');
        abort_unless($line->bank_account_id === $bankAccount->id, 404);

        $this->bank->exclude($line, $request->user());

        return back()->with('status', 'Statement line excluded from reconciliation.');
    }

    public function autoMatch(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $count = $this->bank->autoMatch($bankAccount, $request->user());

        return back()->with('status', $count === 0
            ? 'No automatic matches found.'
            : "Auto-matched {$count} statement line(s).");
    }
}
