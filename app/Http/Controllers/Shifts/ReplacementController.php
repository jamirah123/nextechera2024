<?php

namespace App\Http\Controllers\Shifts;

use App\Enums\ReplacementReason;
use App\Enums\ShiftStatus;
use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use App\Models\Site;
use App\Services\ReplacementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class ReplacementController extends Controller
{
    public function __construct(private ReplacementService $replacements) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', ShiftReplacement::class);

        $replacements = ShiftReplacement::query()
            ->with([
                'originalGuard:id,employment_id,full_name',
                'replacementGuard:id,employment_id,full_name',
                'originalShift:id,reference,shift_date,starts_at,ends_at,status',
                'replacementShift:id,reference,status,shift_type',
                'site:id,name,code',
                'authorizer:id,name',
            ])
            ->search($request->string('q')->toString())
            ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->string('reason')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('replaced_at', $request->string('date')))
            ->when($request->filled('site_id'), fn ($q) => $q->where('site_id', $request->integer('site_id')))
            ->latest('replaced_at')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('replacements.index', [
            'replacements' => $replacements,
            'reasons' => ReplacementReason::cases(),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code']),
            'filters' => $request->only(['q', 'reason', 'date', 'site_id']),
            'canManage' => $request->user()->can('create', ShiftReplacement::class),
            'stats' => [
                'today' => ShiftReplacement::query()->whereDate('replaced_at', now()->toDateString())->count(),
                'week' => ShiftReplacement::query()
                    ->whereBetween('replaced_at', [now()->copy()->startOfWeek(), now()->copy()->endOfWeek()])
                    ->count(),
                'month' => ShiftReplacement::query()
                    ->whereMonth('replaced_at', now()->month)
                    ->whereYear('replaced_at', now()->year)
                    ->count(),
                'total' => ShiftReplacement::query()->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', ShiftReplacement::class);

        $selectedShift = null;
        if ($request->filled('shift_id')) {
            $selectedShift = Shift::query()
                ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
                ->find($request->integer('shift_id'));
        }

        $replaceableShifts = Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->whereIn('status', [
                ShiftStatus::Scheduled->value,
                ShiftStatus::Confirmed->value,
                ShiftStatus::InProgress->value,
                ShiftStatus::Missed->value,
            ])
            ->whereDoesntHave('replacementRecord')
            ->when($request->filled('date'), fn ($q) => $q->where('shift_date', $request->string('date')->toString()))
            ->orderByDesc('shift_date')
            ->orderBy('starts_at')
            ->limit(100)
            ->get();

        if ($selectedShift && ! $replaceableShifts->contains('id', $selectedShift->id) && $this->replacements->isReplaceable($selectedShift)) {
            $replaceableShifts = $replaceableShifts->prepend($selectedShift);
        }

        return view('replacements.create', [
            'shifts' => $replaceableShifts,
            'selectedShift' => $selectedShift,
            'guards' => Guard::query()->activeEmployment()->orderBy('full_name')->get(['id', 'employment_id', 'full_name']),
            'reasons' => ReplacementReason::cases(),
            'canOverride' => $request->user()->can('override', Shift::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ShiftReplacement::class);

        $data = $request->validate([
            'original_shift_id' => ['required', 'exists:shifts,id'],
            'replacement_guard_id' => ['required', 'exists:guards,id'],
            'reason' => ['required', Rule::in(ReplacementReason::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
            'acknowledge_warnings' => ['sometimes', 'boolean'],
            'override_critical' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:255', 'required_if:override_critical,1'],
        ]);

        $data['acknowledge_warnings'] = $request->boolean('acknowledge_warnings');
        $data['override_critical'] = $request->boolean('override_critical');

        try {
            $replacement = $this->replacements->record($data);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['replacement' => $e->getMessage()]);
        }

        return redirect()
            ->route('replacements.show', $replacement)
            ->with('status', 'Replacement recorded. A replacement shift was created for monthly reporting.');
    }

    public function show(ShiftReplacement $replacement): View
    {
        $this->authorize('view', $replacement);

        $replacement->load([
            'originalShift.assignedGuard',
            'replacementShift.assignedGuard',
            'originalGuard',
            'replacementGuard',
            'site',
            'authorizer',
        ]);

        return view('replacements.show', [
            'replacement' => $replacement,
        ]);
    }
}
