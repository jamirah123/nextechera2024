<?php

namespace App\Http\Controllers\Guards;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guards\StoreGuardRequest;
use App\Http\Requests\Guards\UpdateGuardRequest;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Region;
use App\Services\GuardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GuardController extends Controller
{
    public function __construct(private GuardService $guards)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Guard::class);

        $guards = Guard::query()
            ->with(['region:id,name,code', 'currentSite:id,name,code', 'currentSupervisor:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('employment_status'), fn ($q) => $q->where('employment_status', $request->string('employment_status')))
            ->when($request->filled('operational_status'), fn ($q) => $q->where('operational_status', $request->string('operational_status')))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('guards.index', [
            'guards' => $guards,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'operationalStatuses' => OperationalStatus::cases(),
            'filters' => $request->only(['q', 'employment_status', 'operational_status', 'region_id']),
            'canManage' => $request->user()->can('create', Guard::class),
            'canDelete' => $request->user()->can('deleteAny', Guard::class),
            'stats' => [
                'total' => Guard::query()->count(),
                'active' => Guard::query()->activeEmployment()->count(),
                'on_leave' => Guard::query()->where('operational_status', OperationalStatus::OnLeave)->count(),
                'absent' => Guard::query()->where('operational_status', OperationalStatus::Absent)->count(),
                'deserted' => Guard::query()->where('operational_status', OperationalStatus::Deserted)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Guard::class);

        return view('guards.create', [
            'regions' => Region::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'operationalStatuses' => OperationalStatus::cases(),
            'genders' => GuardGender::cases(),
            'nextEmploymentId' => $this->guards->nextEmploymentId(),
        ]);
    }

    public function store(StoreGuardRequest $request): RedirectResponse
    {
        $guard = $this->guards->createGuard($request->safe()->except(['reason']));

        return redirect()
            ->route('guards.show', $guard)
            ->with('status', 'Guard registered successfully.');
    }

    public function show(Guard $guard): View
    {
        $this->authorize('view', $guard);

        $guard->load([
            'region',
            'currentSite.client',
            'currentSupervisor',
            'statusHistories.changer',
            'creator',
            'updater',
        ]);

        return view('guards.show', [
            'guard' => $guard,
            'currentDeployment' => $guard->currentDeployment()->first(),
            'canManage' => request()->user()->can('update', $guard),
            'canDelete' => request()->user()->can('delete', $guard),
            'canDeploy' => request()->user()->can('create', Deployment::class),
        ]);
    }

    public function edit(Guard $guard): View
    {
        $this->authorize('update', $guard);

        return view('guards.edit', [
            'guard' => $guard,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'operationalStatuses' => OperationalStatus::cases(),
            'genders' => GuardGender::cases(),
        ]);
    }

    public function update(UpdateGuardRequest $request, Guard $guard): RedirectResponse
    {
        $this->guards->updateGuard(
            $guard,
            $request->safe()->except(['reason']),
            $request->input('reason'),
        );

        return redirect()
            ->route('guards.show', $guard)
            ->with('status', 'Guard profile updated successfully.');
    }

    public function destroy(Guard $guard): RedirectResponse
    {
        $this->authorize('delete', $guard);

        if ($guard->current_site_id) {
            return back()->withErrors([
                'guard' => 'Cannot archive a guard who is still linked to a current site. Clear the site assignment first.',
            ]);
        }

        $guard->delete();

        return redirect()
            ->route('guards.index')
            ->with('status', 'Guard archived successfully.');
    }
}
