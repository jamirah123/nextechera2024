<?php

namespace App\Http\Controllers\Organization;

use App\Enums\SupervisorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\DeploySupervisorRequest;
use App\Http\Requests\Organization\StoreSupervisorRequest;
use App\Http\Requests\Organization\UpdateSupervisorRequest;
use App\Models\Deployment;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Enums\DeploymentShiftType;
use App\Enums\SiteStatus;
use App\Services\DeploymentService;
use App\Services\OrganizationService;
use App\Services\SupervisorGuardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupervisorController extends Controller
{
    public function __construct(
        private OrganizationService $organization,
        private DeploymentService $deployments,
        private SupervisorGuardService $supervisorGuards,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Supervisor::class);

        $supervisors = Supervisor::query()
            ->with(['region'])
            ->withCount('sites')
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->latest()
            ->paginate(table_per_page())
            ->withQueryString();

        return view('organization.supervisors.index', [
            'supervisors' => $supervisors,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => SupervisorStatus::cases(),
            'filters' => $request->only(['q', 'status', 'region_id']),
            'canManage' => $request->user()->can('create', Supervisor::class),
            'canDelete' => $request->user()->can('deleteAny', Supervisor::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Supervisor::class);

        return view('organization.supervisors.create', [
            'regions' => Region::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => SupervisorStatus::cases(),
            'nextCode' => $this->organization->nextSupervisorCode(),
        ]);
    }

    public function store(StoreSupervisorRequest $request): RedirectResponse
    {
        $supervisor = DB::transaction(function () use ($request) {
            $data = $request->safe()->except(['reason']);
            $data['supervisor_code'] = $this->organization->nextSupervisorCode();
            $data['assignment_date'] = $data['assignment_date'] ?? now()->toDateString();

            $supervisor = Supervisor::query()->create($data);

            $this->organization->recordSupervisorAssignment(
                $supervisor,
                null,
                (int) $supervisor->region_id,
                'initial_assignment',
                $request->input('reason'),
                'Supervisor registered and assigned to region.',
            );

            return $supervisor;
        });

        $this->supervisorGuards->ensureGuardProfile($supervisor);

        return redirect()
            ->route('supervisors.show', $supervisor)
            ->with('status', 'Supervisor registered successfully.');
    }

    public function show(Supervisor $supervisor): View
    {
        $this->authorize('view', $supervisor);

        $supervisor->load([
            'region',
            'guardProfile.currentDeployment.site',
            'guardProfile.region',
            'sites.client',
            'assignmentHistories.previousRegion',
            'assignmentHistories.newRegion',
            'assignmentHistories.changer',
            'creator',
            'updater',
        ]);

        $currentCover = $supervisor->guardProfile?->currentDeployment;

        return view('organization.supervisors.show', [
            'supervisor' => $supervisor,
            'currentCover' => $currentCover,
            'canManage' => request()->user()->can('update', $supervisor),
            'canDelete' => request()->user()->can('delete', $supervisor),
            'canDeployCover' => request()->user()->can('create', Deployment::class) && ! $currentCover,
        ]);
    }

    public function deployForm(Supervisor $supervisor): View|RedirectResponse
    {
        $this->authorize('view', $supervisor);
        $this->authorize('create', Deployment::class);

        $supervisor->load('guardProfile.currentDeployment');

        if ($supervisor->guardProfile?->currentDeployment) {
            return redirect()
                ->route('supervisors.show', $supervisor)
                ->withErrors(['deployment' => 'This supervisor is already covering a site. End that deployment before assigning another.']);
        }

        $user = request()->user();
        $regionId = $user->regionId();

        return view('organization.supervisors.deploy', [
            'supervisor' => $supervisor,
            'sites' => Site::query()
                ->where('status', SiteStatus::Active)
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->when(! $user->mustStayInOwnRegion() && $supervisor->region_id, fn ($q) => $q->where('region_id', $supervisor->region_id))
                ->with('region:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region_id']),
            'shiftTypes' => DeploymentShiftType::cases(),
        ]);
    }

    public function deploy(DeploySupervisorRequest $request, Supervisor $supervisor): RedirectResponse
    {
        $this->authorize('view', $supervisor);

        try {
            $deployment = $this->deployments->deploySupervisor($supervisor, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['deployment' => $e->getMessage()]);
        }

        return redirect()
            ->route('supervisors.show', $supervisor)
            ->with('status', 'Supervisor deployed for cover. A shift was scheduled for the monthly report.');
    }

    public function edit(Supervisor $supervisor): View
    {
        $this->authorize('update', $supervisor);

        return view('organization.supervisors.edit', [
            'supervisor' => $supervisor,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'statuses' => SupervisorStatus::cases(),
        ]);
    }

    public function update(UpdateSupervisorRequest $request, Supervisor $supervisor): RedirectResponse
    {
        DB::transaction(function () use ($request, $supervisor): void {
            $previousRegionId = (int) $supervisor->region_id;
            $data = $request->safe()->except(['reason']);

            $supervisor->update($data);

            if ($previousRegionId !== (int) $supervisor->region_id) {
                $this->organization->recordSupervisorAssignment(
                    $supervisor,
                    $previousRegionId,
                    (int) $supervisor->region_id,
                    'region_transfer',
                    $request->input('reason') ?: 'Region reassignment',
                    'Supervisor transferred to a different region.',
                );
            }
        });

        return redirect()
            ->route('supervisors.show', $supervisor)
            ->with('status', 'Supervisor updated successfully.');
    }

    public function destroy(Supervisor $supervisor): RedirectResponse
    {
        $this->authorize('delete', $supervisor);

        if ($supervisor->sites()->exists()) {
            return back()->withErrors([
                'supervisor' => 'Cannot archive a supervisor who still has assigned sites. Reassign sites first.',
            ]);
        }

        $supervisor->delete();

        return redirect()
            ->route('supervisors.index')
            ->with('status', 'Supervisor archived successfully.');
    }
}
