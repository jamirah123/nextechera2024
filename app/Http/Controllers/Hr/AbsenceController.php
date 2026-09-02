<?php

namespace App\Http\Controllers\Hr;

use App\Enums\AbsenceReason;
use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Guard;
use App\Models\Site;
use App\Services\AbsenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class AbsenceController extends Controller
{
    public function __construct(private AbsenceService $absences)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Absence::class);

        $user = $request->user();
        $regionId = $user->regionId();

        $absences = Absence::query()
            ->with(['assignedGuard:id,employment_id,full_name,region_id', 'site:id,name,code', 'reporter:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('assignedGuard', fn ($g) => $g->where('region_id', $regionId)))
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->string('reason')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('absence_date', $request->string('date')))
            ->latest('absence_date')
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = Absence::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('assignedGuard', fn ($g) => $g->where('region_id', $regionId)));

        return view('hr.absences.index', [
            'absences' => $absences,
            'reasons' => AbsenceReason::cases(),
            'filters' => $request->only(['q', 'reason', 'date']),
            'canManage' => $user->can('create', Absence::class),
            'stats' => [
                'today' => (clone $statsBase)->whereDate('absence_date', now()->toDateString())->count(),
                'month' => (clone $statsBase)->whereMonth('absence_date', now()->month)->whereYear('absence_date', now()->year)->count(),
                'replacement' => (clone $statsBase)->where('replacement_required', true)->count(),
                'covered' => (clone $statsBase)->whereNotNull('replacement_guard_id')->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Absence::class);

        $user = request()->user();
        $regionId = $user->regionId();

        return view('hr.absences.create', [
            'guards' => Guard::query()
                ->activeEmployment()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('full_name')
                ->get(['id', 'employment_id', 'full_name', 'current_site_id']),
            'sites' => Site::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
            'reasons' => AbsenceReason::cases(),
            'replacements' => Guard::query()
                ->activeEmployment()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('full_name')
                ->get(['id', 'employment_id', 'full_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Absence::class);

        $data = $request->validate([
            'guard_id' => ['required', 'exists:guards,id'],
            'absence_date' => ['required', 'date', 'before:today'],
            'reason' => ['required', Rule::in(AbsenceReason::values())],
            'site_id' => ['nullable', 'exists:sites,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'action_taken' => ['nullable', 'string', 'max:255'],
            'replacement_required' => ['sometimes', 'boolean'],
            'replacement_guard_id' => ['nullable', 'exists:guards,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data['replacement_required'] = $request->boolean('replacement_required');

        $user = $request->user();
        if ($user->mustStayInOwnRegion()) {
            $guard = Guard::query()->find($data['guard_id']);
            if (! $guard || ! $user->canAccessRegion($guard->region_id)) {
                return back()->withInput()->withErrors(['guard_id' => 'You can only record absences for guards in your region.']);
            }
        }

        try {
            $absence = $this->absences->record($data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['absence' => $e->getMessage()]);
        }

        return redirect()->route('absences.show', $absence)->with('status', 'Absence recorded.');
    }

    public function show(Absence $absence): View
    {
        $this->authorize('view', $absence);

        $absence->load(['assignedGuard', 'site', 'shift', 'replacementGuard', 'reporter']);

        return view('hr.absences.show', [
            'absence' => $absence,
            'canManage' => request()->user()->can('update', $absence),
        ]);
    }

    public function clear(Request $request, Absence $absence): RedirectResponse
    {
        $this->authorize('update', $absence);

        $this->absences->clear($absence, $request->input('notes'));

        return back()->with('status', 'Absence cleared and guard status restored.');
    }
}
