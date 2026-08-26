<?php

namespace App\Http\Controllers\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deployments\StoreDeploymentRequest;
use App\Http\Requests\Deployments\TransferDeploymentRequest;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Site;
use App\Services\DeploymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class DeploymentController extends Controller
{
    public function __construct(private DeploymentService $deployments)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Deployment::class);

        $deployments = Deployment::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code', 'region:id,name,code', 'supervisor:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('shift_type'), fn ($q) => $q->where('shift_type', $request->string('shift_type')))
            ->when($request->boolean('current_only', true) && ! $request->filled('status'), fn ($q) => $q->current())
            ->latest('start_date')
            ->paginate(12)
            ->withQueryString();

        return view('deployments.index', [
            'deployments' => $deployments,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'region_id']),
            'statuses' => DeploymentStatus::cases(),
            'shiftTypes' => DeploymentShiftType::cases(),
            'filters' => $request->only(['q', 'status', 'region_id', 'site_id', 'shift_type', 'current_only']),
            'canManage' => $request->user()->can('create', Deployment::class),
            'stats' => [
                'active' => Deployment::query()->current()->count(),
                'day' => Deployment::query()->current()->where('shift_type', DeploymentShiftType::Day)->count(),
                'night' => Deployment::query()->current()->where('shift_type', DeploymentShiftType::Night)->count(),
                'transferred' => Deployment::query()->where('status', DeploymentStatus::Transferred)->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Deployment::class);

        return view('deployments.create', [
            'guards' => Guard::query()
                ->activeEmployment()
                ->whereNotIn('operational_status', [
                    OperationalStatus::Deserted,
                    OperationalStatus::Suspended,
                    OperationalStatus::OnLeave,
                    OperationalStatus::Absent,
                    OperationalStatus::SickUnavailable,
                ])
                ->whereDoesntHave('deployments', fn ($q) => $q->current())
                ->orderBy('full_name')
                ->get(['id', 'employment_id', 'full_name', 'region_id']),
            'sites' => Site::query()
                ->where('status', SiteStatus::Active)
                ->with('region:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region_id', 'supervisor_id']),
            'shiftTypes' => DeploymentShiftType::cases(),
            'selectedGuardId' => $request->integer('guard_id') ?: null,
            'selectedSiteId' => $request->integer('site_id') ?: null,
        ]);
    }

    public function store(StoreDeploymentRequest $request): RedirectResponse
    {
        try {
            $deployment = $this->deployments->deploy($request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['deployment' => $e->getMessage()]);
        }

        return redirect()
            ->route('deployments.show', $deployment)
            ->with('status', 'Guard deployed successfully.');
    }

    public function show(Deployment $deployment): View
    {
        $this->authorize('view', $deployment);

        $deployment->load([
            'assignedGuard.region',
            'site.client',
            'region',
            'supervisor',
            'outgoingTransfers.toSite',
            'outgoingTransfers.transferrer',
            'incomingTransfers.fromSite',
            'incomingTransfers.transferrer',
            'creator',
        ]);

        $transfers = $deployment->outgoingTransfers
            ->concat($deployment->incomingTransfers)
            ->sortByDesc('effective_at')
            ->values();

        return view('deployments.show', [
            'deployment' => $deployment,
            'transfers' => $transfers,
            'canManage' => request()->user()->can('update', $deployment),
            'canTransfer' => request()->user()->can('transfer', $deployment) && $deployment->isActive(),
            'canEnd' => request()->user()->can('end', $deployment) && $deployment->isActive(),
        ]);
    }

    public function transferForm(Deployment $deployment): View
    {
        $this->authorize('transfer', $deployment);

        abort_unless($deployment->isActive(), 404);

        return view('deployments.transfer', [
            'deployment' => $deployment->load(['assignedGuard', 'site']),
            'sites' => Site::query()
                ->where('status', SiteStatus::Active)
                ->where('id', '!=', $deployment->site_id)
                ->with('region:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region_id']),
            'shiftTypes' => DeploymentShiftType::cases(),
        ]);
    }

    public function transfer(TransferDeploymentRequest $request, Deployment $deployment): RedirectResponse
    {
        try {
            $newDeployment = $this->deployments->transfer($deployment, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['deployment' => $e->getMessage()]);
        }

        return redirect()
            ->route('deployments.show', $newDeployment)
            ->with('status', 'Guard transferred successfully. Previous deployment preserved in history.');
    }

    public function end(Request $request, Deployment $deployment): RedirectResponse
    {
        $this->authorize('end', $deployment);

        try {
            $this->deployments->end(
                $deployment,
                $request->input('end_date'),
                $request->input('notes'),
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['deployment' => $e->getMessage()]);
        }

        return redirect()
            ->route('deployments.show', $deployment)
            ->with('status', 'Deployment ended successfully.');
    }
}
