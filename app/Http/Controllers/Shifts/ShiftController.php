<?php

namespace App\Http\Controllers\Shifts;

use App\Enums\DeploymentShiftType;
use App\Enums\GuardClassification;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shifts\StoreRecurringShiftRequest;
use App\Http\Requests\Shifts\StoreShiftRequest;
use App\Http\Requests\Shifts\UpdateShiftRequest;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Services\ShiftService;
use App\Services\Shifts\BulkShiftAllocationService;
use App\Services\Shifts\ShiftLifecycleService;
use App\Services\Shifts\ShiftValidationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class ShiftController extends Controller
{
    public function __construct(
        private ShiftService $shifts,
        private ShiftValidationService $validator,
        private BulkShiftAllocationService $bulkAllocation,
        private ShiftLifecycleService $lifecycle,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Shift::class);

        $this->lifecycle->sync();

        $date = $request->filled('date')
            ? $request->string('date')->toString()
            : now()->toDateString();

        $shifts = Shift::query()
            ->with([
                'assignedGuard:id,employment_id,full_name',
                'site:id,name,code,required_day_guards,required_night_guards,required_guards',
                'region:id,name',
                'supervisor:id,name',
            ])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->string('period')))
            ->when($request->filled('shift_type'), fn ($q) => $q->where('shift_type', $request->string('shift_type')))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when(! $request->boolean('all_dates'), fn ($q) => $q->forDate($date))
            ->orderBy('starts_at')
            ->paginate(table_per_page())
            ->withQueryString();

        // Overstaff flags follow current site postings (same rules as deployment capacity),
        // not shift row counts — ending a deployment clears surplus even if a shift remains.
        $shiftSiteIds = Shift::query()
            ->forDate($date)
            ->blocking()
            ->distinct()
            ->pluck('site_id')
            ->all();

        $deploymentPeriodCounts = collect();
        $overstaffedSites = [];

        if ($shiftSiteIds !== []) {
            $rawDeploymentCounts = Deployment::query()
                ->current()
                ->whereIn('site_id', $shiftSiteIds)
                ->selectRaw('site_id, shift_type, count(*) as deployed')
                ->groupBy('site_id', 'shift_type')
                ->get()
                ->groupBy('site_id');

            $deploymentPeriodCounts = $rawDeploymentCounts->map(function ($rows) {
                $byType = $rows->mapWithKeys(function ($row) {
                    $type = $row->shift_type instanceof DeploymentShiftType
                        ? $row->shift_type->value
                        : (string) $row->getRawOriginal('shift_type');

                    return [$type => (int) $row->deployed];
                });

                $rotating = (int) ($byType[DeploymentShiftType::Rotating->value] ?? 0);

                return [
                    ShiftPeriod::Day->value => (int) ($byType[DeploymentShiftType::Day->value] ?? 0) + $rotating,
                    ShiftPeriod::Night->value => (int) ($byType[DeploymentShiftType::Night->value] ?? 0) + $rotating,
                ];
            });

            $sitesForCoverage = Site::query()
                ->whereIn('id', $shiftSiteIds)
                ->get(['id', 'name', 'required_day_guards', 'required_night_guards', 'required_guards'])
                ->keyBy('id');

            foreach ($shiftSiteIds as $siteId) {
                $site = $sitesForCoverage->get($siteId);
                if (! $site) {
                    continue;
                }

                $byPeriod = $deploymentPeriodCounts->get($siteId, [
                    ShiftPeriod::Day->value => 0,
                    ShiftPeriod::Night->value => 0,
                ]);

                foreach ([ShiftPeriod::Day->value, ShiftPeriod::Night->value] as $periodValue) {
                    $deployed = (int) ($byPeriod[$periodValue] ?? 0);
                    $required = $periodValue === ShiftPeriod::Night->value
                        ? (int) $site->required_night_guards
                        : (int) $site->required_day_guards;
                    if ($required <= 0) {
                        $required = (int) $site->required_guards;
                    }
                    if ($required > 0 && $deployed > $required) {
                        $overstaffedSites[] = [
                            'site' => $site->name,
                            'period' => ShiftPeriod::from($periodValue)->label(),
                            'deployed' => $deployed,
                            'required' => $required,
                        ];
                    }
                }
            }
        }

        $statsBase = Shift::query()->forDate($date);

        return view('shifts.index', [
            'shifts' => $shifts,
            'date' => $date,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'region_id']),
            'statuses' => ShiftStatus::cases(),
            'periods' => ShiftPeriod::cases(),
            'shiftTypes' => ShiftType::cases(),
            'filters' => $request->only(['q', 'date', 'status', 'period', 'shift_type', 'region_id', 'site_id', 'all_dates']),
            'canManage' => $request->user()->can('create', Shift::class),
            'deploymentPeriodCounts' => $deploymentPeriodCounts,
            'overstaffedSites' => $overstaffedSites,
            'stats' => [
                'scheduled' => (clone $statsBase)->where('status', ShiftStatus::Scheduled)->count(),
                'recorded' => (clone $statsBase)->where('status', ShiftStatus::Recorded)->count(),
                'confirmed' => (clone $statsBase)->where('status', ShiftStatus::Confirmed)->count(),
                'in_progress' => (clone $statsBase)->where('status', ShiftStatus::InProgress)->count(),
                'completed' => (clone $statsBase)->where('status', ShiftStatus::Completed)->count(),
                'missed' => (clone $statsBase)->where('status', ShiftStatus::Missed)->count(),
                'incomplete' => (clone $statsBase)->where('status', ShiftStatus::Incomplete)->count(),
                'cancelled' => (clone $statsBase)->where('status', ShiftStatus::Cancelled)->count(),
            ],
        ]);
    }

    public function calendar(Request $request): View
    {
        $this->authorize('viewAny', Shift::class);

        $start = Carbon::parse($request->input('week', now()->startOfWeek()->toDateString()))->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $shifts = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->whereBetween('shift_date', [$start->toDateString(), $end->toDateString()])
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (Shift $shift) => $shift->shift_date->toDateString());

        $days = collect(CarbonPeriod::create($start, $end))->map(fn (Carbon $day) => [
            'date' => $day->toDateString(),
            'label' => $day->format('D d M'),
            'is_today' => $day->isToday(),
            'shifts' => $shifts->get($day->toDateString(), collect()),
        ]);

        return view('shifts.calendar', [
            'days' => $days,
            'weekStart' => $start,
            'prevWeek' => $start->copy()->subWeek()->toDateString(),
            'nextWeek' => $start->copy()->addWeek()->toDateString(),
            'regions' => Region::query()->orderBy('name')->get(['id', 'name']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $request->only(['week', 'region_id', 'site_id']),
            'canManage' => $request->user()->can('create', Shift::class),
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Shift::class);

        return view('shifts.create', $this->formData($request));
    }

    public function allocate(Request $request): View
    {
        $this->authorize('create', Shift::class);

        $user = $request->user();
        $date = $request->filled('date')
            ? $request->string('date')->toString()
            : now()->toDateString();
        $regionId = $user->regionId();
        $showAll = $request->boolean('show_all');

        $deployments = Deployment::query()
            ->current()
            ->with([
                'assignedGuard:id,employment_id,full_name,operational_status,guard_classification',
                'site:id,name,code,region_id',
                'region:id,name,code',
            ])
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $like = '%'.$request->string('q')->toString().'%';
                $q->whereHas('assignedGuard', function ($guard) use ($like): void {
                    $guard->where('full_name', 'like', $like)
                        ->orWhere('employment_id', 'like', $like);
                });
            })
            ->when(! $showAll, function ($q) use ($date): void {
                $q->whereDoesntHave('assignedGuard.shifts', function ($shift) use ($date): void {
                    $shift->whereDate('shift_date', $date)
                        ->whereIn('status', ShiftStatus::blockingAllocationValues());
                });
            })
            ->orderBy('site_id')
            ->orderBy('guard_id')
            ->paginate(table_per_page())
            ->withQueryString();

        $guardIds = $deployments->getCollection()->pluck('guard_id')->all();
        $existingShifts = Shift::query()
            ->whereIn('guard_id', $guardIds)
            ->whereDate('shift_date', $date)
            ->whereIn('status', ShiftStatus::blockingAllocationValues())
            ->get(['id', 'guard_id', 'period', 'status', 'reference'])
            ->groupBy('guard_id');

        $deployments->getCollection()->transform(function (Deployment $deployment) use ($existingShifts) {
            $deployment->setAttribute('existing_shifts', $existingShifts->get($deployment->guard_id, collect()));

            return $deployment;
        });

        return view('shifts.allocate', [
            'deployments' => $deployments,
            'date' => $date,
            'regions' => Region::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'sites' => Site::query()
                ->active()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->when($request->filled('region_id') && ! $user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $request->integer('region_id')))
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region_id']),
            'periods' => ShiftPeriod::cases(),
            'shiftTypes' => ShiftType::cases(),
            'classifications' => GuardClassification::cases(),
            'filters' => $request->only(['q', 'date', 'region_id', 'site_id', 'show_all']),
            'showAll' => $showAll,
            'stats' => [
                'deployed' => Deployment::query()
                    ->current()
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                    ->count(),
                'needs_allocation' => Deployment::query()
                    ->current()
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                    ->whereDoesntHave('assignedGuard.shifts', function ($shift) use ($date): void {
                        $shift->whereDate('shift_date', $date)
                            ->whereIn('status', ShiftStatus::blockingAllocationValues());
                    })
                    ->count(),
                'scheduled_today' => Shift::query()
                    ->forDate($date)
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                    ->whereIn('status', ShiftStatus::blockingAllocationValues())
                    ->count(),
            ],
        ]);
    }

    public function allocateStore(Request $request): RedirectResponse
    {
        $this->authorize('create', Shift::class);

        $data = $request->validate([
            'shift_date' => ['required', 'date'],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer', 'exists:deployments,id'],
            'rows' => ['required', 'array'],
        ]);

        $selectedIds = collect($data['selected'])->map(fn ($id) => (int) $id)->unique()->values();

        $rowRules = [];
        foreach ($selectedIds as $deploymentId) {
            $rowRules["rows.{$deploymentId}.period"] = ['required', Rule::in(ShiftPeriod::values())];
            $rowRules["rows.{$deploymentId}.shift_type"] = ['required', Rule::in(ShiftType::values())];
            $rowRules["rows.{$deploymentId}.guard_classification"] = ['required', Rule::in(GuardClassification::values())];
        }

        $data = array_merge($data, $request->validate($rowRules));

        $user = $request->user();
        $rows = [];

        foreach ($data['selected'] as $deploymentId) {
            $row = $data['rows'][$deploymentId] ?? null;
            if (! $row) {
                continue;
            }

            $deployment = Deployment::query()->current()->find($deploymentId);
            if (! $deployment || ! $user->canAccessRegion($deployment->region_id)) {
                continue;
            }

            $rows[] = [
                'deployment_id' => (int) $deploymentId,
                'period' => $row['period'],
                'shift_type' => $row['shift_type'],
                'guard_classification' => $row['guard_classification'],
            ];
        }

        if ($rows === []) {
            return back()->withErrors(['selected' => 'Select at least one valid deployed guard to allocate.']);
        }

        $result = $this->bulkAllocation->allocate($data['shift_date'], $rows);

        $message = "Allocated {$result['created']} shift(s).";
        if ($result['skipped'] > 0) {
            $message .= " Skipped {$result['skipped']}.";
        }

        return back()
            ->with('status', $message)
            ->with('allocation_errors', array_slice($result['errors'], 0, 12));
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        try {
            $shift = $this->shifts->create($request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['shift' => $e->getMessage()]);
        }

        return redirect()
            ->route('shifts.show', $shift)
            ->with('status', 'Shift recorded successfully.');
    }

    public function show(Shift $shift): View
    {
        $this->authorize('view', $shift);

        $this->lifecycle->sync();
        $shift->refresh();

        $shift->load([
            'assignedGuard.region',
            'site.client',
            'region',
            'supervisor',
            'deployment',
            'creator',
            'updater',
            'approver',
            'overrideBy',
            'replacedShift.assignedGuard',
            'replacements.assignedGuard',
            'replacementRecord',
        ]);

        return view('shifts.show', [
            'shift' => $shift,
            'canManage' => request()->user()->can('update', $shift),
            'canOverride' => request()->user()->can('override', Shift::class),
        ]);
    }

    public function edit(Shift $shift): View
    {
        $this->authorize('update', $shift);

        return view('shifts.edit', array_merge($this->formData(request(), $shift), [
            'shift' => $shift,
        ]));
    }

    public function update(UpdateShiftRequest $request, Shift $shift): RedirectResponse
    {
        try {
            $this->shifts->update($shift, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['shift' => $e->getMessage()]);
        }

        return redirect()
            ->route('shifts.show', $shift)
            ->with('status', 'Shift updated successfully.');
    }

    public function updateStatus(Request $request, Shift $shift): RedirectResponse
    {
        $this->authorize('manageStatus', $shift);

        $request->validate([
            'status' => ['required', Rule::in(ShiftStatus::manuallySettableValues())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->shifts->updateStatus($shift, ShiftStatus::from($request->string('status')->toString()), $request->input('notes'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['shift' => $e->getMessage()]);
        }

        return back()->with('status', 'Shift status updated.');
    }

    public function recurringCreate(Request $request): View
    {
        $this->authorize('create', Shift::class);

        return view('shifts.recurring', $this->formData($request));
    }

    public function recurringStore(StoreRecurringShiftRequest $request): RedirectResponse
    {
        try {
            $result = $this->shifts->createRecurring($request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['shift' => $e->getMessage()]);
        }

        return redirect()
            ->route('shifts.index')
            ->with('status', "Recurring pattern saved. Created {$result['created']} shift(s), skipped {$result['skipped']}.");
    }

    public function validatePreview(Request $request)
    {
        $this->authorize('create', Shift::class);

        $data = $request->validate([
            'guard_id' => ['required', 'exists:guards,id'],
            'site_id' => ['required', 'exists:sites,id'],
            'shift_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'ignore_shift_id' => ['nullable', 'exists:shifts,id'],
        ]);

        [$startsAt, $endsAt] = $this->shifts->resolveWindow(
            $data['shift_date'],
            $data['start_time'],
            $data['end_time'],
        );

        $result = $this->validator->validate([
            'guard_id' => (int) $data['guard_id'],
            'site_id' => (int) $data['site_id'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'ignore_shift_id' => isset($data['ignore_shift_id']) ? (int) $data['ignore_shift_id'] : null,
        ]);

        return response()->json([
            'ok' => $result->isClean(),
            'critical' => $result->criticalMessages(),
            'warnings' => $result->warningMessages(),
            'issues' => $result->all(),
        ]);
    }

    /** @return array<string, mixed> */
    private function formData(Request $request, ?Shift $shift = null): array
    {
        $user = $request->user();
        $regionId = $user?->regionId();

        // Create/allocate: prefer currently deployed guards.
        // Edit/correct: allow any active guard so wrong-guard records can be fixed.
        $guardsQuery = Guard::query()
            ->activeEmployment()
            ->when($user?->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
            ->with(['currentSite:id,name,code', 'currentSupervisor:id,name'])
            ->orderBy('full_name');

        if (! $shift) {
            $guardsQuery->whereHas('deployments', fn ($q) => $q->current());
        }

        $guards = $guardsQuery->get([
            'id', 'employment_id', 'full_name', 'current_site_id', 'region_id', 'operational_status',
        ]);

        if ($shift?->assignedGuard && ! $guards->contains('id', $shift->guard_id)) {
            $guards->prepend($shift->assignedGuard);
        }

        return [
            'guards' => $guards,
            'sites' => Site::query()
                ->active()
                ->when($user?->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->with('region:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'region_id', 'supervisor_id']),
            'periods' => ShiftPeriod::cases(),
            'shiftTypes' => ShiftType::cases(),
            'selectedGuardId' => $request->integer('guard_id') ?: null,
            'selectedSiteId' => $request->integer('site_id') ?: null,
            'selectedDate' => $request->filled('date')
                ? $request->string('date')->toString()
                : now()->toDateString(),
            'defaultDayStart' => config('psg.shift_defaults.day.start', '06:00'),
            'defaultDayEnd' => config('psg.shift_defaults.day.end', '18:00'),
            'defaultNightStart' => config('psg.shift_defaults.night.start', '18:00'),
            'defaultNightEnd' => config('psg.shift_defaults.night.end', '06:00'),
            'canOverride' => $request->user()->can('override', Shift::class),
            'deployments' => Deployment::query()
                ->current()
                ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name'])
                ->get(['id', 'guard_id', 'site_id']),
        ];
    }
}
