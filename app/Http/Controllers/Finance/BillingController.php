<?php

namespace App\Http\Controllers\Finance;

use App\Enums\BillingMode;
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
use Illuminate\Validation\Rule;
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
            ->with(['client:id,name', 'site:id,name,code', 'creator:id,name'])
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
            ->paginate(table_per_page())
            ->withQueryString();

        return view('finance.billing.index', [
            'profiles' => $profiles,
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['q', 'client_id', 'status', 'scope']),
            'scope' => $scope,
            'canManage' => $request->user()->can('manageFinance'),
            'currency' => Money::currency(),
            'exportQuery' => array_filter($request->only(['q', 'client_id', 'status', 'scope']), fn ($v) => filled($v)),
            'stats' => [
                'active' => BillingProfile::query()->where('is_active', true)->count(),
                'inactive' => BillingProfile::query()->where('is_active', false)->count(),
                'clients' => BillingProfile::query()->distinct('client_id')->count('client_id'),
                'total' => BillingProfile::query()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageFinance');

        return view('finance.billing.create', [
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'code',
                    'client_id',
                    'required_day_armed_guards',
                    'required_day_unarmed_guards',
                    'required_night_armed_guards',
                    'required_night_unarmed_guards',
                ]),
            'currency' => Money::currency(),
            'billingModes' => BillingMode::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $this->validatedBilling($request);
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
            'clients' => Client::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                    'code',
                    'client_id',
                    'required_day_armed_guards',
                    'required_day_unarmed_guards',
                    'required_night_armed_guards',
                    'required_night_unarmed_guards',
                ]),
            'currency' => Money::currency(),
            'billingModes' => BillingMode::cases(),
        ]);
    }

    public function update(Request $request, BillingProfile $billing): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $this->validatedBilling($request);
        $this->billing->update($billing, $data);

        return redirect()->route('billing.show', $billing)->with('status', 'Billing profile updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'current';

        $rows = BillingProfile::query()
            ->with(['client:id,name', 'site:id,name,code'])
            ->search($request->string('q')->toString())
            ->when($request->filled('client_id'), fn ($q) => $q->where('client_id', $request->integer('client_id')))
            ->when($scope === 'current', fn ($q) => $q->where('is_active', true))
            ->latest()
            ->limit(5000)
            ->get();

            $headers = ['#', 'Client', 'Site', 'Mode', 'Cash/no VAT', 'Armed posts', 'Unarmed posts', 'Total', 'Armed rate/mo', 'Unarmed rate/mo', 'Day shift', 'Night shift', 'Effective from', 'Effective to', 'Status'];
        $data = $rows->values()->map(fn (BillingProfile $p, int $i) => [
            $i + 1,
            $p->client?->name,
            $p->site?->name ?? 'Client-wide',
            $p->billing_mode?->label() ?? 'Monthly',
            $p->cash_no_tax ? 'Yes' : 'No',
            $p->contracted_armed_guards,
            $p->contracted_unarmed_guards,
            $p->contractedGuardTotal(),
            $p->monthlyArmedRate(),
            $p->monthlyUnarmedRate(),
            $p->rate_per_armed_day_shift,
            $p->rate_per_armed_night_shift,
            $p->effective_from?->format('Y-m-d'),
            $p->effective_to?->format('Y-m-d'),
            $p->is_active ? 'Active' : 'Inactive',
        ]);

        return $this->exports->downloadCsv('psg-billing-profiles.csv', $headers, $data);
    }

    /** @return array<string, mixed> */
    private function validatedBilling(Request $request): array
    {
        $request->merge([
            'site_id' => $request->filled('site_id') ? $request->input('site_id') : null,
        ]);

        $data = $request->validate($this->billingRules($request));
        $data['is_active'] = $request->boolean('is_active', true);
        $data['cash_no_tax'] = $request->boolean('cash_no_tax');

        $manpower = $this->manpowerFor(
            (int) $data['client_id'],
            isset($data['site_id']) ? (int) $data['site_id'] : null,
        );

        $data['contracted_day_armed_guards'] = $manpower['day_armed'];
        $data['contracted_day_unarmed_guards'] = $manpower['day_unarmed'];
        $data['contracted_night_armed_guards'] = $manpower['night_armed'];
        $data['contracted_night_unarmed_guards'] = $manpower['night_unarmed'];
        $data['contracted_armed_guards'] = $manpower['day_armed'] + $manpower['night_armed'];
        $data['contracted_unarmed_guards'] = $manpower['day_unarmed'] + $manpower['night_unarmed'];

        $mode = BillingMode::from($data['billing_mode']);
        $totalGuards = array_sum($manpower);
        $armedPosts = $manpower['day_armed'] + $manpower['night_armed'];
        $unarmedPosts = $manpower['day_unarmed'] + $manpower['night_unarmed'];
        $monthlyBillable = (
            ($armedPosts > 0 && (float) ($data['monthly_rate_per_armed_guard'] ?? 0) > 0)
            || ($unarmedPosts > 0 && (float) ($data['monthly_rate_per_unarmed_guard'] ?? 0) > 0)
        );
        $shiftBillable = (
            (float) ($data['rate_per_armed_day_shift'] ?? 0) > 0
            || (float) ($data['rate_per_unarmed_day_shift'] ?? 0) > 0
            || (float) ($data['rate_per_armed_night_shift'] ?? 0) > 0
            || (float) ($data['rate_per_unarmed_night_shift'] ?? 0) > 0
        );

        if ($mode->usesMonthlyRates() && $totalGuards === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'client_id' => 'No site manpower found for this client/site. Set armed/unarmed day and night guards on sites first.',
            ]);
        }

        if ($mode->usesMonthlyRates() && ! $monthlyBillable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'monthly_rate_per_unarmed_guard' => 'Enter monthly rates for the armed and/or unarmed posts that have manpower.',
            ]);
        }

        if ($mode->usesShiftRates() && ! $shiftBillable) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'rate_per_armed_day_shift' => 'Set at least one day/night armed or unarmed shift rate for per-shift or hybrid billing.',
            ]);
        }

        return $data;
    }

    /**
     * @return array{day_armed: int, day_unarmed: int, night_armed: int, night_unarmed: int}
     */
    private function manpowerFor(int $clientId, ?int $siteId): array
    {
        $query = Site::query()->where('client_id', $clientId);

        if ($siteId) {
            $query->where('id', $siteId);
        }

        $row = $query
            ->selectRaw('
                COALESCE(SUM(required_day_armed_guards), 0) as day_armed,
                COALESCE(SUM(required_day_unarmed_guards), 0) as day_unarmed,
                COALESCE(SUM(required_night_armed_guards), 0) as night_armed,
                COALESCE(SUM(required_night_unarmed_guards), 0) as night_unarmed
            ')
            ->first();

        return [
            'day_armed' => (int) ($row->day_armed ?? 0),
            'day_unarmed' => (int) ($row->day_unarmed ?? 0),
            'night_armed' => (int) ($row->night_armed ?? 0),
            'night_unarmed' => (int) ($row->night_unarmed ?? 0),
        ];
    }

    /** @return array<string, mixed> */
    private function billingRules(Request $request): array
    {
        return [
            'client_id' => ['required', 'exists:clients,id'],
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('client_id', $request->integer('client_id'))),
            ],
            'billing_mode' => ['required', Rule::enum(BillingMode::class)],
            'cash_no_tax' => ['sometimes', 'boolean'],
            'monthly_rate_per_armed_guard' => ['nullable', 'numeric', 'min:0'],
            'monthly_rate_per_unarmed_guard' => ['nullable', 'numeric', 'min:0'],
            'monthly_cost_per_armed_guard' => ['nullable', 'numeric', 'min:0'],
            'monthly_cost_per_unarmed_guard' => ['nullable', 'numeric', 'min:0'],
            'monthly_site_fee' => ['nullable', 'numeric', 'min:0'],
            'rate_per_armed_day_shift' => ['nullable', 'numeric', 'min:0'],
            'rate_per_unarmed_day_shift' => ['nullable', 'numeric', 'min:0'],
            'rate_per_armed_night_shift' => ['nullable', 'numeric', 'min:0'],
            'rate_per_unarmed_night_shift' => ['nullable', 'numeric', 'min:0'],
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
