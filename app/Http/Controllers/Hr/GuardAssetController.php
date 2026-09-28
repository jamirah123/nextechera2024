<?php

namespace App\Http\Controllers\Hr;

use App\Enums\AssetCategory;
use App\Enums\AssetIssuanceType;
use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\GuardAssetLine;
use App\Models\GuardAssetRecovery;
use App\Models\Region;
use App\Services\GuardAssetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class GuardAssetController extends Controller
{
    public function __construct(private GuardAssetService $assets) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', GuardAssetIssuance::class);

        $request->validate([
            'date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $regionId = $user->regionId();

        $issuances = GuardAssetIssuance::query()
            ->with(['assignedGuard:id,employment_id,full_name,region_id', 'issuer:id,name', 'lines'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('assignedGuard', fn ($g) => $g->where('region_id', $regionId)))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('category'), fn ($q) => $q->whereHas('lines', fn ($line) => $line->where('asset_category', $request->string('category'))))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('issued_at', $request->date('date')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest('issued_at')
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = GuardAssetLine::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('issuance.assignedGuard', fn ($g) => $g->where('region_id', $regionId)));

        $outstanding = (clone $statsBase)
            ->whereColumn('quantity_returned', '<', 'quantity')
            ->whereNotIn('status', ['returned', 'written_off', 'lost'])
            ->count();

        return view('hr.assets.index', [
            'issuances' => $issuances,
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'categories' => AssetCategory::cases(),
            'issuanceTypes' => AssetIssuanceType::cases(),
            'filters' => $request->only(['q', 'region_id', 'category', 'status', 'date']),
            'canManage' => $user->can('create', GuardAssetIssuance::class),
            'stats' => [
                'issued_month' => GuardAssetIssuance::query()
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('assignedGuard', fn ($g) => $g->where('region_id', $regionId)))
                    ->whereMonth('issued_at', now()->month)
                    ->whereYear('issued_at', now()->year)
                    ->count(),
                'outstanding' => $outstanding,
                'recoveries' => (float) GuardAssetRecovery::query()
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('assignedGuard', fn ($g) => $g->where('region_id', $regionId)))
                    ->active()
                    ->sum('balance_remaining'),
                'weapons' => (clone $statsBase)
                    ->where('asset_category', AssetCategory::Weapon)
                    ->whereColumn('quantity_returned', '<', 'quantity')
                    ->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', GuardAssetIssuance::class);

        $user = $request->user();
        $regionId = $user->regionId();

        return view('hr.assets.create', [
            'guards' => Guard::query()
                ->activeEmployment()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('full_name')
                ->get(['id', 'employment_id', 'full_name', 'region_id']),
            'categories' => AssetCategory::cases(),
            'issuanceTypes' => AssetIssuanceType::cases(),
            'selectedGuardId' => $request->integer('guard_id') ?: null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', GuardAssetIssuance::class);

        $data = $request->validate([
            'guard_id' => ['required', 'exists:guards,id'],
            'issuance_type' => ['required', Rule::enum(AssetIssuanceType::class)],
            'issued_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.asset_category' => ['required', Rule::enum(AssetCategory::class)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.size' => ['nullable', 'string', 'max:32'],
            'lines.*.serial_number' => ['nullable', 'string', 'max:64'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'lines.*.unit_value' => ['nullable', 'numeric', 'min:0'],
            'lines.*.recover_cost' => ['sometimes', 'boolean'],
            'lines.*.recovery_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.monthly_recovery' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $issuance = $this->assets->issue($data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()
            ->route('assets.show', $issuance)
            ->with('status', 'Assets issued successfully.');
    }

    public function show(GuardAssetIssuance $asset): View
    {
        $this->authorize('view', $asset);

        $asset->load(['assignedGuard.region', 'issuer', 'lines.recovery', 'region']);

        return view('hr.assets.show', [
            'issuance' => $asset,
            'canManage' => request()->user()->can('update', $asset),
            'canDelete' => request()->user()->can('delete', $asset),
            'canModify' => $this->assets->canModify($asset),
            'modificationBlockers' => $this->assets->modificationBlockers($asset),
        ]);
    }

    public function edit(GuardAssetIssuance $asset): View|RedirectResponse
    {
        $this->authorize('update', $asset);

        if (! $this->assets->canModify($asset)) {
            return redirect()
                ->route('assets.show', $asset)
                ->withErrors(['asset' => $this->assets->modificationBlockers($asset)[0] ?? 'This issuance can no longer be edited.']);
        }

        $user = request()->user();
        $regionId = $user->regionId();

        return view('hr.assets.edit', [
            'issuance' => $asset->load(['assignedGuard', 'lines.recovery']),
            'guards' => Guard::query()
                ->activeEmployment()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('full_name')
                ->get(['id', 'employment_id', 'full_name', 'region_id']),
            'categories' => AssetCategory::cases(),
            'issuanceTypes' => AssetIssuanceType::cases(),
        ]);
    }

    public function update(Request $request, GuardAssetIssuance $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $data = $request->validate([
            'guard_id' => ['required', 'exists:guards,id'],
            'issuance_type' => ['required', Rule::enum(AssetIssuanceType::class)],
            'issued_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer', 'exists:guard_asset_lines,id'],
            'lines.*.asset_category' => ['required', Rule::enum(AssetCategory::class)],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.size' => ['nullable', 'string', 'max:32'],
            'lines.*.serial_number' => ['nullable', 'string', 'max:64'],
            'lines.*.quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'lines.*.unit_value' => ['nullable', 'numeric', 'min:0'],
            'lines.*.recover_cost' => ['sometimes', 'boolean'],
            'lines.*.recovery_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.monthly_recovery' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $issuance = $this->assets->updateIssuance($asset, $data, $request->user());
        } catch (InvalidArgumentException $exception) {
            return back()->withInput()->withErrors(['lines' => $exception->getMessage()]);
        }

        return redirect()
            ->route('assets.show', $issuance)
            ->with('status', 'Asset issuance updated.');
    }

    public function destroy(GuardAssetIssuance $asset): RedirectResponse
    {
        $this->authorize('delete', $asset);

        try {
            $this->assets->deleteIssuance($asset, request()->user());
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('assets.show', $asset)
                ->withErrors(['asset' => $exception->getMessage()]);
        }

        return redirect()
            ->route('assets.index')
            ->with('status', 'Asset issuance deleted.');
    }

    public function returnItems(Request $request, GuardAssetIssuance $asset): RedirectResponse
    {
        $this->authorize('update', $asset);

        $data = $request->validate([
            'termination_return' => ['sometimes', 'boolean'],
            'lines' => ['required', 'array'],
            'lines.*.return_qty' => ['nullable', 'integer', 'min:0', 'max:100'],
            'lines.*.disposition' => ['nullable', Rule::in(['returned', 'lost', 'written_off'])],
            'lines.*.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->assets->recordReturns(
            $asset,
            $data['lines'],
            $request->user(),
            $request->boolean('termination_return'),
        );

        return redirect()
            ->route('assets.show', $asset)
            ->with('status', $request->boolean('termination_return')
                ? 'Termination return recorded.'
                : 'Return recorded.');
    }
}
