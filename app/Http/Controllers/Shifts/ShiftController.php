<?php

namespace App\Http\Controllers\Shifts;

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
use App\Services\Shifts\BulkShiftCompletionService;
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
        private BulkShiftCompletionService $bulkCompletion,
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Shift::class);

        $date = $request->input('date', now()->toDateString());

        $shifts = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code', 'region:id,name', 'supervisor:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('period'), fn ($q) => $q->where('period', $request->string('period')))
            ->when($request->filled('shift_type'), fn ($q) => $q->where('shift_type', $request->string('shift_type')))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->when(! $request->boolean('all_dates'), fn ($q) => $q->forDate($date))
            ->orderBy('starts_at')
            ->paginate(15)
            ->withQueryString();

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
            'canManageStatus' => $request->user()->can('create', Shift::class),
            'stats' => [
                'scheduled' => (clone $statsBase)->where('status', ShiftStatus::Scheduled)->count(),
                'confirmed' => (clone $statsBase)->where('status', ShiftStatus::Confirmed)->count(),
                'in_progress' => (clone $statsBase)->where('status', ShiftStatus::InProgress)->count(),
                'completed' => (clone $statsBase)->where('status', ShiftStatus::Completed)->count(),
                'missed' => (clone $statsBase)->where('status', ShiftStatus::Missed)->count(),
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
        $date = $request->input('date', now()->toDateString());
        $regionId = $user->regionId();

        $deployments = Deployment::query()
            ->current()
            ->with([
                'assignedGuard:id,employment_id,full_name,operational_status',
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
            ->when($request->boolean('unscheduled_only'), function ($q) use ($date): void {
                $q->whereDoesntHave('assignedGuard.shifts', function ($shift) use ($date): void {
                    $shift->whereDate('shift_date', $date)
                        ->whereNotIn('status', [ShiftStatus::Cancelled->value, ShiftStatus::Replaced->value]);
                });
            })
            ->orderBy('site_id')
            ->orderBy('guard_id')
            ->paginate(20)
            ->withQueryString();

        $guardIds = $deployments->getCollection()->pluck('guard_id')->all();
        $existingShifts = Shift::query()
            ->whereIn('guard_id', $guardIds)
            ->whereDate('shift_date', $date)
            ->whereNotIn('status', [ShiftStatus::Cancelled->value, ShiftStatus::Replaced->value])
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
            'filters' => $request->only(['q', 'date', 'region_id', 'site_id', 'unscheduled_only']),
            'stats' => [
                'deployed' => Deployment::query()
                    ->current()
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                    ->count(),
                'scheduled_today' => Shift::query()
                    ->forDate($date)
                    ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                    ->whereNotIn('status', [ShiftStatus::Cancelled->value, ShiftStatus::Replaced->value])
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
                'guard_classification' => GuardClassification::Unarmed->value,
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
            ->with('status', 'Shift scheduled successfully.');
    }

    public function show(Shift $shift): View
    {
        $this->authorize('view', $shift);

        $shift->load([
            'assignedGuard.region',
            'site.client',
            'region',
            'supervisor',
            'deployment',
            'creator',
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

        return view('shifts.edit', array_merge($this->formData(request()), [
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
            'status' => ['required', 'in:'.implode(',', ShiftStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->shifts->updateStatus($shift, ShiftStatus::from($request->string('status')->toString()), $request->input('notes'));
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['shift' => $e->getMessage()]);
        }

        return back()->with('status', 'Shift status updated.');
    }

    public function bulkComplete(Request $request): RedirectResponse
    {
        $this->authorize('create', Shift::class);

        $data = $request->validate([
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer', 'exists:shifts,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'date' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $shiftIds = [];

        $shifts = Shift::query()
            ->whereIn('id', $data['selected'])
            ->get(['id', 'region_id', 'shift_date']);

        foreach ($shifts as $shift) {
            if (! $user->canAccessRegion($shift->region_id)) {
                continue;
            }

            if (! empty($data['date']) && $shift->shift_date?->toDateString() !== $data['date']) {
                continue;
            }

            $shiftIds[] = $shift->id;
        }

        if ($shiftIds === []) {
            return back()->withErrors(['selected' => 'Select at least one valid shift to complete.']);
        }

        $result = $this->bulkCompletion->complete(
            $shiftIds,
            $data['notes'] ?? 'Bulk completed from today\'s shifts',
        );

        $message = "Completed {$result['completed']} shift(s)";
        if ($result['skipped'] > 0) {
            $message .= ", skipped {$result['skipped']}";
        }
        $message .= '.';

        return back()
            ->with('status', $message)
            ->with('completion_errors', $result['errors']);
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
    private function formData(Request $request): array
    {
        $deployedGuards = Guard::query()
            ->activeEmployment()
            ->whereHas('deployments', fn ($q) => $q->current())
            ->with(['currentSite:id,name,code', 'currentSupervisor:id,name'])
            ->orderBy('full_name')
            ->get(['id', 'employment_id', 'full_name', 'current_site_id', 'region_id', 'operational_status']);

        return [
            'guards' => $deployedGuards,
            'sites' => Site::query()->active()->with('region:id,name')->orderBy('name')->get(['id', 'name', 'code', 'region_id', 'supervisor_id']),
            'periods' => ShiftPeriod::cases(),
            'shiftTypes' => ShiftType::cases(),
            'guardClassifications' => GuardClassification::cases(),
            'selectedGuardId' => $request->integer('guard_id') ?: null,
            'selectedSiteId' => $request->integer('site_id') ?: null,
            'selectedDate' => $request->input('date', now()->toDateString()),
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
