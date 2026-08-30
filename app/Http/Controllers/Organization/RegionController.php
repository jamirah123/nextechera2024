<?php

namespace App\Http\Controllers\Organization;

use App\Enums\RegionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreRegionRequest;
use App\Http\Requests\Organization\UpdateRegionRequest;
use App\Models\Region;
use App\Services\ManpowerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RegionController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Region::class);

        $regions = Region::query()
            ->withCount(['sites', 'supervisors'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate(table_per_page())
            ->withQueryString();

        return view('organization.regions.index', [
            'regions' => $regions,
            'statuses' => RegionStatus::cases(),
            'filters' => $request->only(['q', 'status']),
            'canManage' => $request->user()->can('create', Region::class),
            'canDelete' => $request->user()->can('deleteAny', Region::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Region::class);

        return view('organization.regions.create', [
            'statuses' => RegionStatus::cases(),
        ]);
    }

    public function store(StoreRegionRequest $request): RedirectResponse
    {
        $region = Region::query()->create($request->validated());

        return redirect()
            ->route('regions.show', $region)
            ->with('status', 'Region created successfully.');
    }

    public function show(Region $region, ManpowerService $manpower): View
    {
        $this->authorize('view', $region);

        $region->load([
            'supervisors' => fn ($q) => $q->withCount('sites')->latest(),
            'sites' => fn ($q) => $q->with(['client', 'supervisor'])->latest(),
            'creator',
            'updater',
        ]);

        return view('organization.regions.show', [
            'region' => $region,
            'manpower' => $manpower->forRegion($region),
            'canManage' => request()->user()->can('update', $region),
            'canDelete' => request()->user()->can('delete', $region),
        ]);
    }

    public function edit(Region $region): View
    {
        $this->authorize('update', $region);

        return view('organization.regions.edit', [
            'region' => $region,
            'statuses' => RegionStatus::cases(),
        ]);
    }

    public function update(UpdateRegionRequest $request, Region $region): RedirectResponse
    {
        $region->update($request->validated());

        return redirect()
            ->route('regions.show', $region)
            ->with('status', 'Region updated successfully.');
    }

    public function destroy(Region $region): RedirectResponse
    {
        $this->authorize('delete', $region);

        if ($region->sites()->exists() || $region->supervisors()->exists()) {
            return back()->withErrors([
                'region' => 'Cannot archive a region that still has supervisors or sites. Reassign them first.',
            ]);
        }

        $region->delete();

        return redirect()
            ->route('regions.index')
            ->with('status', 'Region archived successfully.');
    }
}
