<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Site;
use App\Services\Finance\BillingService;
use App\Services\Finance\FinanceHistoryService;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(
        private BillingService $billing,
        private FinanceHistoryService $history,
        private ReportExportService $exports,
    ) {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'current';

        $profiles = BillingProfile::query()
            ->with(['client:id,name,code', 'site:id,name,code', 'creator:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($scope === 'current', fn ($q) => $q->where('is_active', true))
            ->when($request->filled('status') && $scope === 'all', function ($q) use ($request): void {
                if ($request->string('status')->toString() === 'active') {
                    $q->where('is_active', true);
                } elseif ($request->string('status')->toString() === 'inactive') {
                    $q->where('is_active', false);
                }
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('finance.billing.index', [
            'profiles' => $profiles,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $request->only(['q', 'client_id', 'status', 'scope']),
            'scope' => $scope,
            'canManage' => $request->user()->can('manageFinance'),
            'currency' => Money::currency(),
            'exportQuery' => array_filter($request->only(['q', 'client_id', 'status', 'scope']), fn ($v) => filled($v)),
            'stats' => [
                'active' => BillingProfile::query()->where('is_active', true)->count(),
                'total' => BillingProfile::query()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageFinance');

        return view('finance.billing.create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'code']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'client_id']),
            'currency' => Money::currency(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate($this->billingRules());
        $data['is_active'] = $request->boolean('is_active', true);
        $profile = $this->billing->create($data);

        return redirect()->route('billing.show', $profile)->with('status', 'Billing profile saved.');
    }

    public function show(BillingProfile $billing): View
    {
        Gate::authorize('viewFinance');

        $billing->load(['client', 'site', 'creator', 'updater']);

        return view('finance.billing.show', [
            'profile' => $billing,
            'canManage' => request()->user()->can('manageFinance'),
            'history' => $this->history->forSubject($billing),
            'recordMeta' => $this->history->recordMeta($billing),
        ]);
    }

    public function edit(BillingProfile $billing): View
    {
        Gate::authorize('manageFinance');

        return view('finance.billing.edit', [
            'profile' => $billing,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name', 'code']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'client_id']),
            'currency' => Money::currency(),
        ]);
    }

    public function update(Request $request, BillingProfile $billing): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate($this->billingRules());
        $data['is_active'] = $request->boolean('is_active');
        $this->billing->update($billing, $data);

        return redirect()->route('billing.show', $billing)->with('status', 'Billing profile updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'current';

        $rows = BillingProfile::query()
            ->with(['client:id,name,code', 'site:id,name,code'])
            ->search($request->string('q')->toString())
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($scope === 'current', fn ($q) => $q->where('is_active', true))
            ->latest()
            ->limit(5000)
            ->get();

        $headers = ['#', 'Client', 'Site', 'Armed', 'Unarmed', 'Total', 'Monthly armed', 'Monthly unarmed', 'Site fee', 'Effective from', 'Effective to', 'Status'];
        $data = $rows->values()->map(fn (BillingProfile $p, int $i) => [
            $i + 1,
            $p->client?->name,
            $p->site?->name ?? 'Client-wide',
            $p->contracted_armed_guards,
            $p->contracted_unarmed_guards,
            $p->contractedGuardTotal(),
            $p->monthly_rate_per_armed_guard,
            $p->monthly_rate_per_unarmed_guard,
            $p->monthly_site_fee,
            $p->effective_from?->format('Y-m-d'),
            $p->effective_to?->format('Y-m-d'),
            $p->is_active ? 'Active' : 'Inactive',
        ]);

        return $this->exports->downloadCsv('psg-billing-profiles.csv', $headers, $data);
    }

    /** @return array<string, mixed> */
    private function billingRules(): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'contracted_armed_guards' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'contracted_unarmed_guards' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'monthly_rate_per_armed_guard' => ['nullable', 'numeric', 'min:0'],
            'monthly_rate_per_unarmed_guard' => ['nullable', 'numeric', 'min:0'],
            'monthly_cost_per_armed_guard' => ['nullable', 'numeric', 'min:0'],
            'monthly_cost_per_unarmed_guard' => ['nullable', 'numeric', 'min:0'],
            'monthly_site_fee' => ['required', 'numeric', 'min:0'],
            'rate_per_armed_shift' => ['nullable', 'numeric', 'min:0'],
            'rate_per_unarmed_shift' => ['nullable', 'numeric', 'min:0'],
            'cost_per_armed_shift' => ['nullable', 'numeric', 'min:0'],
            'cost_per_unarmed_shift' => ['nullable', 'numeric', 'min:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
