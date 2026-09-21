<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\BankStatementLine;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\GlPeriod;
use App\Services\Finance\Ledger\GlPeriodService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LedgerDashboardController extends Controller
{
    public function __invoke(GlPeriodService $periods): View|RedirectResponse
    {
        if (! Gate::allows('viewFinance') && Gate::allows('viewPurchases')) {
            return redirect()->route('ledger.purchases.index');
        }

        Gate::authorize('viewFinance');

        $periods->ensureRollingWindow();

        $openPeriod = GlPeriod::query()
            ->where('year', now()->year)
            ->where('month', now()->month)
            ->first();

        $postedJournals = GlJournal::query()->posted()->count();
        $accounts = GlAccount::query()->active()->count();
        $unmatchedBank = BankStatementLine::query()->unmatched()->count();
        $closedPeriods = GlPeriod::query()->where('status', 'closed')->count();

        $recent = GlJournal::query()
            ->with(['period', 'lines'])
            ->latest('id')
            ->limit(8)
            ->get();

        $bankAccounts = BankAccount::query()->active()->with('glAccount')->orderBy('name')->get();

        return view('finance.ledger.index', [
            'openPeriod' => $openPeriod,
            'stats' => [
                'accounts' => $accounts,
                'journals' => $postedJournals,
                'unmatched' => $unmatchedBank,
                'closed_periods' => $closedPeriods,
            ],
            'recent' => $recent,
            'bankAccounts' => $bankAccounts,
            'currency' => Money::currency(),
        ]);
    }
}
