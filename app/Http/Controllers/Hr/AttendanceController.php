<?php

namespace App\Http\Controllers\Hr;

use App\Enums\AttendanceEventType;
use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Guard;
use App\Models\Site;
use App\Services\AttendanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(private AttendanceService $attendances) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Attendance::class);

        $attendances = Attendance::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code', 'recorder:id,name'])
            ->search($request->string('q')->toString())
            ->when($request->filled('event_type'), fn ($q) => $q->where('event_type', $request->string('event_type')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('occurred_at', $request->string('date')))
            ->latest('occurred_at')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('hr.attendances.index', [
            'attendances' => $attendances,
            'eventTypes' => AttendanceEventType::cases(),
            'filters' => $request->only(['q', 'event_type', 'date']),
            'canManage' => $request->user()->can('create', Attendance::class),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Attendance::class);

        return view('hr.attendances.create', [
            'guards' => Guard::query()->activeEmployment()->orderBy('full_name')->get(['id', 'employment_id', 'full_name', 'current_site_id']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'eventTypes' => AttendanceEventType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Attendance::class);

        $data = $request->validate([
            'guard_id' => ['required', 'exists:guards,id'],
            'event_type' => ['required', Rule::in(AttendanceEventType::values())],
            'occurred_at' => ['nullable', 'date'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $attendance = $this->attendances->record($data);

        return redirect()->route('attendances.index')->with('status', 'Attendance recorded successfully.');
    }
}
