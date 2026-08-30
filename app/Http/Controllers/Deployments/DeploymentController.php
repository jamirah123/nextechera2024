<?php

namespace App\Http\Controllers\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\SiteStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deployments\StoreDeploymentRequest;
use App\Http\Requests\Deployments\TransferDeploymentRequest;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Site;
use App\Services\AbsenceService;
use App\Services\DeploymentService;
use App\Services\Deployments\BulkDeploymentService;
use App\Services\Shifts\BulkShiftAllocationService;
use App\Support\Deployments\DeploymentShiftSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class DeploymentController extends Controller
{
    public function __construct(
        private DeploymentService $deployments,
        private BulkDeploymentService $bulkDeployments,
        private BulkShiftAllocationService $bulkAllocation,
        private AbsenceService $absences,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Deployment::class);

        $user = $request->user();
        $regionId = $user->regionId();

        $deployments = Deployment::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code', 'region:id,name,code', 'supervisor:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('shift_type'), fn ($q) => $q->where('shift_type', $request->string('shift_type')))
            ->when($request->boolean('current_only', true) && ! $request->filled('status'), fn ($q) => $q->current())
            ->latest('start_date')
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = Deployment::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId));

        return view('deployments.index', [
            'deployments' => $deployments,
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'sites' => Site::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region_id']),
            'statuses' => DeploymentStatus::cases(),
            'shiftTypes' => DeploymentShiftType::cases(),
            'filters' => $request->only(['q', 'status', 'region_id', 'site_id', 'shift_type', 'current_only']),
            'canManage' => $user->can('create', Deployment::class),
            'stats' => [
                'active' => (clone $statsBase)->current()->count(),
                'day' => (clone $statsBase)->current()->where('shift_type', DeploymentShiftType::Day)->count(),
                'night' => (clone $statsBase)->current()->where('shift_type', DeploymentShiftType::Night)->count(),
                'transferred' => (clone $statsBase)->where('status', DeploymentStatus::Transferred)->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Deployment::class);

        $this->releaseBoardPoolGuards($request->user());

        $user = $request->user();
        $regionId = $user->regionId();

        return view('deployments.create', [
            'guards' => Guard::query()
                ->activeEmployment()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->availableForDeployment()
                ->orderBy('full_name')
                ->get(['id', 'employment_id', 'full_name', 'region_id']),
            'sites' => Site::query()
                ->where('status', SiteStatus::Active)
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->with('region:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region_id', 'supervisor_id']),
            'shiftTypes' => DeploymentShiftType::cases(),
            'selectedGuardId' => $request->integer('guard_id') ?: null,
            'selectedSiteId' => $request->integer('site_id') ?: null,
        ]);
    }

    public function board(Request $request): View
    {
        $this->authorize('board', Deployment::class);

        $this->releaseBoardPoolGuards($request->user());

        $user = $request->user();
        $regionId = $user->regionId();
        $shiftSchedule = \App\Support\Deployments\DeploymentShiftSchedule::fromConfig();
        $baseQuery = $this->boardGuardQuery($request, $user);

        $guards = (clone $baseQuery)
            ->paginate(table_per_page())
            ->withQueryString();

        $regions = Region::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $regionId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $regionCounts = (clone $this->boardGuardQuery($request, $user, applyRegionFilter: false))
            ->reorder()
            ->selectRaw('region_id, COUNT(*) as total')
            ->groupBy('region_id')
            ->pluck('total', 'region_id');

        $sites = Site::query()
            ->where('status', SiteStatus::Active)
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'region_id']);

        return view('deployments.board', [
            'guards' => $guards,
            'sites' => $sites,
            'sitesByRegion' => $sites->groupBy('region_id'),
            'regions' => $regions,
            'regionCounts' => $regionCounts,
            'shiftTypes' => DeploymentShiftType::cases(),
            'filters' => $request->only(['q', 'region_id']),
            'shiftWindows' => $shiftSchedule->labels(),
            'stats' => [
                'awaiting' => (clone $this->boardGuardQuery($request, $user, applyRegionFilter: false))->count(),
                'active' => Deployment::query()
                    ->current()
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                    ->count(),
            ],
        ]);
    }

    public function boardStore(Request $request): RedirectResponse
    {
        $this->authorize('board', Deployment::class);

        $this->releaseBoardPoolGuards($request->user());

        $data = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer', 'exists:guards,id'],
            'rows' => ['required', 'array'],
            'start_date' => ['nullable', 'date'],
            'allocate_shifts' => ['sometimes', 'boolean'],
            'shift_date' => ['nullable', 'date', 'required_if:allocate_shifts,1,true'],
        ]);

        $selectedIds = collect($data['selected'])->map(fn ($id) => (int) $id)->unique()->values();

        $rowRules = [];
        foreach ($selectedIds as $guardId) {
            $rowRules["rows.{$guardId}.site_id"] = ['required', 'exists:sites,id'];
            $rowRules["rows.{$guardId}.shift_type"] = ['required', Rule::in(DeploymentShiftType::values())];
        }

        $data = array_merge($data, $request->validate($rowRules));

        $user = $request->user();
        $rows = [];

        foreach ($data['selected'] as $guardId) {
            $row = $data['rows'][$guardId] ?? null;
            if (! $row) {
                continue;
            }

            $guard = Guard::query()->find($guardId);
            $site = Site::query()->find($row['site_id'] ?? null);

            if (! $guard || ! $site) {
                continue;
            }

            if (! $user->canAccessRegion($guard->region_id) || ! $user->canAccessRegion($site->region_id)) {
                continue;
            }

            $rows[] = [
                'guard_id' => (int) $guardId,
                'site_id' => (int) $site->id,
                'shift_type' => $row['shift_type'],
                'start_date' => $data['start_date'] ?? now()->toDateString(),
            ];
        }

        if ($rows === []) {
            return back()->withErrors(['selected' => 'Select at least one guard with a valid site.']);
        }

        $result = $this->bulkDeployments->deployMany($rows);

        $this->deployments->syncDeployedGuardStatuses(
            $user->mustStayInOwnRegion() ? $user->regionId() : null,
        );

        $message = "Deployed {$result['created']} guard(s).";
        $errors = $result['errors'];

        if ($request->boolean('allocate_shifts') && $result['deployed_guard_ids'] !== []) {
            $shiftDate = $data['shift_date'] ?? $data['start_date'] ?? now()->toDateString();
            $deployments = Deployment::query()
                ->current()
                ->whereIn('guard_id', $result['deployed_guard_ids'])
                ->get();

            $allocRows = $deployments->map(fn (Deployment $deployment) => [
                'deployment_id' => $deployment->id,
                'period' => $this->periodForDeployment($deployment)->value,
            ])->all();

            $allocResult = $this->bulkAllocation->allocate($shiftDate, $allocRows);
            $message .= " Allocated {$allocResult['created']} shift(s).";

            if ($allocResult['skipped'] > 0) {
                $message .= " Skipped {$allocResult['skipped']} allocation(s).";
            }

            $errors = array_merge($errors, $allocResult['errors']);
        }

        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']}.";
        }

        return back()
            ->with('status', $message)
            ->with('deployment_errors', array_slice($errors, 0, 12));
    }

    public function store(StoreDeploymentRequest $request): RedirectResponse
    {
        $this->releaseBoardPoolGuards($request->user());

        try {
            $deployment = $this->deployments->deploy($request->validated());
            $this->deployments->syncDeployedGuardStatuses();
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
                ->when(request()->user()->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', request()->user()->regionId()))
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

    private function boardGuardQuery(Request $request, \App\Models\User $user, bool $applyRegionFilter = true): \Illuminate\Database\Eloquent\Builder
    {
        $regionId = $user->regionId();

        return Guard::query()
            ->activeEmployment()
            ->with(['region:id,name,code'])
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when(
                $applyRegionFilter && $request->filled('region_id') && ! $user->mustStayInOwnRegion(),
                fn ($q) => $q->where('region_id', $request->integer('region_id')),
            )
            ->when($request->filled('q'), function ($q) use ($request): void {
                $like = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('full_name', 'like', $like)
                        ->orWhere('employment_id', 'like', $like);
                });
            })
            ->availableForDeployment()
            ->orderBy('region_id')
            ->orderBy('full_name');
    }

    private function releaseBoardPoolGuards(\App\Models\User $user): void
    {
        $this->absences->releaseEligibleAbsentGuards();
        $this->deployments->releaseGuardsAfterShiftWindow(
            $user->mustStayInOwnRegion() ? $user->regionId() : null,
        );
    }

    private function periodForDeployment(Deployment $deployment): ShiftPeriod
    {
        return match ($deployment->shift_type) {
            DeploymentShiftType::Night => ShiftPeriod::Night,
            DeploymentShiftType::Day => ShiftPeriod::Day,
            DeploymentShiftType::Rotating => DeploymentShiftSchedule::isOnShift(DeploymentShiftType::Night)
                ? ShiftPeriod::Night
                : ShiftPeriod::Day,
        };
    }
}
