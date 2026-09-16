<?php

namespace App\Http\Controllers\Finance\Ledger;

use App\Http\Controllers\Controller;
use App\Models\GlPeriod;
use App\Services\Finance\Ledger\GlPeriodService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class GlPeriodController extends Controller
{
    public function __construct(private GlPeriodService $periods) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $this->periods->ensureRollingWindow();

        $periods = GlPeriod::query()
            ->with('closer:id,name')
            ->withCount(['journals as posted_journals_count' => fn ($q) => $q->posted()])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('finance.ledger.periods.index', [
            'periods' => $periods,
            'canManage' => $request->user()->can('manageFinance'),
        ]);
    }

    public function close(Request $request, GlPeriod $period): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->periods->close($period, $request->user(), $data['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('status', $period->label().' closed. No further journals can post into this period.');
    }

    public function reopen(Request $request, GlPeriod $period): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->periods->reopen($period, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('status', $period->label().' re-opened.');
    }
}
