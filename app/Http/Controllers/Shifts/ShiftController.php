<?php

namespace App\Http\Controllers\Shifts;

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
use App\Services\Shifts\ShiftValidationService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ShiftController extends Controller
{
    public function __construct(
        private ShiftService $shifts,
        private ShiftValidationService $validator,
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
            'selectedGuardId' => $request->integer('guard_id') ?: null,
            'selectedSiteId' => $request->integer('site_id') ?: null,
            'selectedDate' => $request->input('date', now()->toDateString()),
            'canOverride' => $request->user()->can('override', Shift::class),
            'deployments' => Deployment::query()
                ->current()
                ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name'])
                ->get(['id', 'guard_id', 'site_id']),
        ];
    }
}
