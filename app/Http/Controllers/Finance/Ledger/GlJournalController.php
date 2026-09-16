<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Enums\GlJournalSource;
use App\Enums\GlJournalStatus;
use App\Http\Controllers\Controller;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Services\Finance\Ledger\LedgerPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GlJournalController extends Controller
{
    public function __construct(private LedgerPostingService $ledger) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $journals = GlJournal::query()
            ->with(['period', 'lines'])
            ->when($request->filled('q'), fn ($q) => $q->search($request->string('q')->toString()))
            ->when($request->filled('source'), fn ($q) => $q->where('source', $request->string('source')->toString()))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('journal_date')
            ->latest('id')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('finance.ledger.journals.index', [
            'journals' => $journals,
            'sources' => GlJournalSource::cases(),
            'statuses' => GlJournalStatus::cases(),
            'filters' => $request->only(['q', 'source', 'status']),
            'canManage' => $request->user()->can('manageFinance'),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('manageFinance');

        return view('finance.ledger.journals.create', [
            'accounts' => GlAccount::query()->postable()->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'journal_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', 'exists:gl_accounts,id'],
            'lines.*.debit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
            'lines.*.memo' => ['nullable', 'string', 'max:255'],
        ]);

        $accounts = GlAccount::query()->whereIn('id', collect($data['lines'])->pluck('account_id'))->get()->keyBy('id');

        $lines = [];
        foreach ($data['lines'] as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($debit == 0.0 && $credit == 0.0) {
                continue;
            }
            $lines[] = [
                'account' => $accounts[(int) $line['account_id']],
                'debit' => $debit,
                'credit' => $credit,
                'memo' => $line['memo'] ?? null,
            ];
        }

        try {
            $journal = $this->ledger->post(
                source: GlJournalSource::Manual,
                document: null,
                journalDate: $data['journal_date'],
                description: $data['description'],
                lines: $lines,
                actor: $request->user(),
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['journal' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('ledger.journals.show', $journal)
            ->with('status', 'Journal '.$journal->reference.' posted.');
    }

    public function show(GlJournal $journal): View
    {
        Gate::authorize('viewFinance');

        $journal->load(['lines.account', 'period', 'poster', 'voider', 'sourceDocument', 'reversalOf', 'reversals']);

        return view('finance.ledger.journals.show', [
            'journal' => $journal,
        ]);
    }
}
