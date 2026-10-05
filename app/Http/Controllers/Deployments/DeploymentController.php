<?php

namespace App\Http\Controllers\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Http\Controllers\Concerns\ServesPdfDownload;
use App\Http\Controllers\Controller;
use App\Http\Requests\Deployments\StoreDeploymentRequest;
use App\Http\Requests\Deployments\TransferDeploymentRequest;
use App\Http\Requests\Deployments\UpdateDeploymentRequest;
use App\Models\Deployment;
use App\Models\DeploymentTransfer;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\Deployments\BulkDeploymentService;
use App\Services\DeploymentService;
use App\Services\Documents\LetterPdfService;
use App\Services\ManpowerService;
use App\Services\Shifts\BulkShiftAllocationService;
use App\Services\Shifts\ShiftLifecycleService;
use App\Support\Deployments\DeploymentShiftSchedule;
use App\Support\Historical\HistoricalDates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class DeploymentController extends Controller
{
    use ServesPdfDownload;

    public function __construct(
        private DeploymentService $deployments,
        private BulkDeploymentService $bulkDeployments,
        private BulkShiftAllocationService $bulkAllocation,
        private AbsenceService $absences,
        private LetterPdfService $letters,
        private ShiftLifecycleService $shiftLifecycle,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Deployment::class);

        $user = $request->user();
        $regionId = $user->regionId();
        $asOfDate = $request->filled('date')
            ? $request->date('date')->toDateString()
            : null;

        $deployments = Deployment::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code', 'region:id,name,code', 'supervisor:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('shift_type'), fn ($q) => $q->where('shift_type', $request->string('shift_type')))
            ->when(
                $asOfDate !== null,
                fn ($q) => $q->withDutyOnDate($asOfDate),
                fn ($q) => $q->when(
                    $request->boolean('current_only', true) && ! $request->filled('status'),
                    fn ($inner) => $inner->current(),
                ),
            )
            ->latest('start_date')
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = Deployment::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when(
                $asOfDate !== null,
                fn ($q) => $q->withDutyOnDate($asOfDate),
                fn ($q) => $q->current(),
            );

        $shiftTypeCounts = status_counts($statsBase, 'shift_type');

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
            'asOfDate' => $asOfDate,
            'filters' => $request->only(['q', 'status', 'region_id', 'site_id', 'shift_type', 'current_only', 'date']),
            'canManage' => $user->can('create', Deployment::class),
            'stats' => [
                'active' => array_sum($shiftTypeCounts),
                'day' => (int) ($shiftTypeCounts[DeploymentShiftType::Day->value] ?? 0),
                'night' => (int) ($shiftTypeCounts[DeploymentShiftType::Night->value] ?? 0),
                'transferred' => Deployment::query()
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                    ->where('status', DeploymentStatus::Transferred)
                    ->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Deployment::class);

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
        $dutyDate = HistoricalDates::parseDate(
            $request->filled('start_date') ? $request->string('start_date')->toString() : now()->toDateString()
        )->toDateString();
        $isHistorical = HistoricalDates::isPastCalendarDay($dutyDate);
        $shiftSchedule = DeploymentShiftSchedule::fromConfig();
        $baseQuery = $this->boardGuardQuery($request, $user, dutyDate: $dutyDate);

        $guards = (clone $baseQuery)
            ->paginate(table_per_page())
            ->withQueryString();

        $regions = Region::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $regionId))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        $regionCounts = (clone $this->boardGuardQuery($request, $user, applyRegionFilter: false, dutyDate: $dutyDate))
            ->reorder()
            ->selectRaw('region_id, COUNT(*) as total')
            ->groupBy('region_id')
            ->pluck('total', 'region_id');

        $sites = Site::query()
            ->where('status', SiteStatus::Active)
            ->with('region:id,name,code')
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'region_id', 'required_day_guards', 'required_night_guards']);

        $sitesByRegion = $sites->groupBy(fn (Site $site) => (int) $site->region_id);

        $activeDeployments = Deployment::query()
            ->current()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId));

        return view('deployments.board', [
            'guards' => $guards,
            'boardAvailability' => $this->boardShiftAvailability($guards->getCollection(), $dutyDate, $isHistorical),
            'sites' => $sites,
            'sitesByRegion' => $sitesByRegion,
            'regions' => $regions,
            'regionCounts' => $regionCounts,
            'shiftTypes' => DeploymentShiftType::cases(),
            'filters' => array_merge($request->only(['q', 'region_id']), ['start_date' => $dutyDate]),
            'dutyDate' => $dutyDate,
            'isHistorical' => $isHistorical,
            'boardManpower' => app(ManpowerService::class)->postingBoardCoverage($sites, $dutyDate),
            'shiftWindows' => $shiftSchedule->labels(),
            'stats' => [
                'awaiting' => (int) $regionCounts->sum(),
                'active' => (clone $activeDeployments)->count(),
                'day' => (clone $activeDeployments)->where('shift_type', DeploymentShiftType::Day)->count(),
                'night' => (clone $activeDeployments)->where('shift_type', DeploymentShiftType::Night)->count(),
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
            'start_date' => ['required', 'date'],
            'duty_date_to' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $selectedIds = collect($data['selected'])->map(fn ($id) => (int) $id)->unique()->values();

        $guardsById = Guard::query()
            ->whereIn('id', $selectedIds)
            ->get(['id', 'employment_id', 'full_name', 'first_name', 'last_name'])
            ->keyBy('id');

        $rowRules = [];
        $rowAttributes = [];
        $rowMessages = [];
        foreach ($selectedIds as $guardId) {
            $guard = $guardsById->get($guardId);
            $label = $guard
                ? trim(($guard->employment_id ? $guard->employment_id.' · ' : '').($guard->full_name ?: trim($guard->first_name.' '.$guard->last_name)))
                : 'Guard #'.$guardId;

            $rowRules["rows.{$guardId}.site_id"] = ['required', 'exists:sites,id'];
            $rowRules["rows.{$guardId}.shift_type"] = ['required', Rule::in(DeploymentShiftType::values())];
            $rowRules["rows.{$guardId}.duty_type"] = ['nullable', Rule::in([
                ShiftType::Normal->value,
                ShiftType::Overtime->value,
            ])];

            $rowAttributes["rows.{$guardId}.site_id"] = "site for {$label}";
            $rowAttributes["rows.{$guardId}.shift_type"] = "posting type for {$label}";
            $rowAttributes["rows.{$guardId}.duty_type"] = "duty type for {$label}";
            $rowMessages["rows.{$guardId}.site_id.required"] = "Choose a site for {$label} before deploying.";
            $rowMessages["rows.{$guardId}.shift_type.required"] = "Choose a posting type for {$label}.";
        }

        $data = array_merge($data, $request->validate($rowRules, $rowMessages, $rowAttributes));

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
                'duty_type' => $row['duty_type'] ?? ShiftType::Normal->value,
                'start_date' => $data['start_date'],
                'duty_date_to' => $data['duty_date_to'] ?? null,
            ];
        }

        if ($rows === []) {
            return back()->withErrors(['selected' => 'Select at least one guard with a valid site.']);
        }

        $sites = Site::query()->whereIn('id', collect($rows)->pluck('site_id')->unique())->get();
        $warnings = app(ManpowerService::class)->overstaffingWarnings(
            app(ManpowerService::class)->postingBoardCoverage($sites, $data['start_date']),
            $rows,
        );

        if ($warnings !== [] && ! $request->boolean('acknowledge_overstaffing')) {
            return back()
                ->withInput()
                ->with('overstaffing_warnings', $warnings);
        }

        if ($request->boolean('acknowledge_overstaffing')) {
            foreach ($rows as $index => $row) {
                $rows[$index]['allow_overstaffing'] = true;
            }
        }

        $result = $this->bulkDeployments->deployMany($rows);

        $this->deployments->syncDeployedGuardStatuses(
            $user->mustStayInOwnRegion() ? $user->regionId() : null,
        );

        $message = "Posted {$result['created']} guard(s). Shift recorded for the duty date(s).";
        $errors = $result['errors'];

        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']}.";
        }

        return redirect()
            ->route('deployments.board', $request->only(['q', 'region_id', 'start_date']))
            ->with('status', $message)
            ->with('deployment_errors', $errors !== [] ? array_slice($errors, 0, 12) : null);
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
            ->with('status', 'Guard posted. Shift recorded for the duty date(s).');
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

    public function edit(Deployment $deployment): View
    {
        $this->authorize('update', $deployment);

        $user = request()->user();
        $regionId = $user->regionId();

        $guards = Guard::query()
            ->activeEmployment()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->orderBy('full_name')
            ->get(['id', 'employment_id', 'full_name', 'region_id', 'operational_status']);

        if ($deployment->assignedGuard && ! $guards->contains('id', $deployment->guard_id)) {
            $guards->prepend($deployment->assignedGuard);
        }

        return view('deployments.edit', [
            'deployment' => $deployment->load(['assignedGuard', 'site', 'region']),
            'guards' => $guards,
            'sites' => Site::query()
                ->where('status', SiteStatus::Active)
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->with('region:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region_id']),
            'shiftTypes' => DeploymentShiftType::cases(),
        ]);
    }

    public function update(UpdateDeploymentRequest $request, Deployment $deployment): RedirectResponse
    {
        try {
            $this->deployments->correct($deployment, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['deployment' => $e->getMessage()]);
        }

        return redirect()
            ->route('deployments.show', $deployment)
            ->with('status', 'Deployment corrected successfully.');
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

    private function boardGuardQuery(
        Request $request,
        User $user,
        bool $applyRegionFilter = true,
        ?string $dutyDate = null,
    ): Builder {
        $regionId = $user->regionId();
        $dutyDate ??= HistoricalDates::parseDate(
            $request->filled('start_date') ? $request->string('start_date')->toString() : now()->toDateString()
        )->toDateString();

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
            ->selectableOnPostingBoard($dutyDate)
            ->when(
                $applyRegionFilter && $request->filled('region_id'),
                fn ($q) => $q->orderBy('full_name'),
                fn ($q) => $q->orderBy('full_name')->orderBy('region_id'),
            );
    }

    private function releaseBoardPoolGuards(User $user): void
    {
        $this->shiftLifecycle->sync();
        $this->absences->releaseEligibleAbsentGuards();
        $this->deployments->releaseGuardsAfterShiftWindow(
            $user->mustStayInOwnRegion() ? $user->regionId() : null,
        );
    }

    /**
     * Day and night state for the guards on this page.
     *
     * A past date uses recorded shifts only, so a closed standing post does not
     * mark a missed duty as already deployed. Today also treats an open posting
     * as occupying its shift window.
     *
     * @param  Collection<int, Guard>  $guards
     * @return array<int, array{day: array{deployed: bool, site: ?string}, night: array{deployed: bool, site: ?string}}>
     */
    private function boardShiftAvailability(Collection $guards, string $dutyDate, bool $historical): array
    {
        $ids = $guards->pluck('id');
        $state = [];

        foreach ($ids as $id) {
            $state[(int) $id] = [
                'day' => ['deployed' => false, 'site' => null],
                'night' => ['deployed' => false, 'site' => null],
            ];
        }

        if ($ids->isEmpty()) {
            return $state;
        }

        $shifts = Shift::query()
            ->with('site:id,name')
            ->blocking()
            ->whereIn('guard_id', $ids)
            ->forDate($dutyDate)
            ->get(['id', 'guard_id', 'site_id', 'period']);

        foreach ($shifts as $shift) {
            $period = $shift->period instanceof ShiftPeriod ? $shift->period->value : (string) $shift->period;
            if (! isset($state[$shift->guard_id][$period])) {
                continue;
            }

            $state[$shift->guard_id][$period] = [
                'deployed' => true,
                'site' => $shift->site?->name,
            ];
        }

        if ($historical) {
            return $state;
        }

        $deployments = Deployment::query()
            ->with('site:id,name')
            ->current()
            ->whereIn('guard_id', $ids)
            ->get(['id', 'guard_id', 'site_id', 'shift_type']);

        foreach ($deployments as $deployment) {
            $periods = $deployment->shift_type === DeploymentShiftType::Rotating
                ? ['day', 'night']
                : [$deployment->shift_type === DeploymentShiftType::Night ? 'night' : 'day'];

            foreach ($periods as $period) {
                if ($state[$deployment->guard_id][$period]['deployed']) {
                    continue;
                }

                $state[$deployment->guard_id][$period] = [
                    'deployed' => true,
                    'site' => $deployment->site?->name,
                ];
            }
        }

        return $state;
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

    public function downloadLetter(Deployment $deployment): Response
    {
        $this->authorize('view', $deployment);

        $reference = 'DEP-'.str_pad((string) $deployment->id, 5, '0', STR_PAD_LEFT);

        return $this->pdfDownload(
            $this->letters->deployment($deployment),
            'deployment-letter-'.$reference.'.pdf',
        );
    }

    public function downloadTransferLetter(DeploymentTransfer $transfer): Response
    {
        $deployment = $transfer->fromDeployment ?? $transfer->toDeployment;
        abort_unless($deployment !== null, 404);

        $this->authorize('view', $deployment);

        $reference = 'TRF-'.str_pad((string) $transfer->id, 5, '0', STR_PAD_LEFT);

        return $this->pdfDownload(
            $this->letters->transfer($transfer),
            'transfer-letter-'.$reference.'.pdf',
        );
    }
}
