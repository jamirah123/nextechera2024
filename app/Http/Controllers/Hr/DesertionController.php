<?php

namespace App\Http\Controllers\Hr;

use App\Enums\DesertionHrStatus;
use App\Http\Controllers\Controller;
use App\Models\Desertion;
use App\Models\Guard;
use App\Models\Site;
use App\Services\DesertionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

class DesertionController extends Controller
{
    public function __construct(private DesertionService $desertions)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Desertion::class);

        $user = $request->user();
        $regionId = $user->regionId();

        $desertions = Desertion::query()
            ->with(['assignedGuard:id,employment_id,full_name,region_id', 'lastKnownSite:id,name,code', 'reporter:id,name'])
            ->search($request->string('q')->toString())
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('assignedGuard', fn ($g) => $g->where('region_id', $regionId)))
            ->when($request->filled('hr_status'), fn ($q) => $q->where('hr_status', $request->string('hr_status')))
            ->when($request->filled('date'), fn ($q) => $q->whereDate('date_reported', $request->string('date')))
            ->latest('date_reported')
            ->paginate(table_per_page())
            ->withQueryString();

        $statsBase = Desertion::query()
            ->when($user->mustStayInOwnRegion(), fn ($q) => $q->whereHas('assignedGuard', fn ($g) => $g->where('region_id', $regionId)));

        return view('hr.desertions.index', [
            'desertions' => $desertions,
            'statuses' => DesertionHrStatus::cases(),
            'filters' => $request->only(['q', 'hr_status', 'date']),
            'canManage' => $user->can('create', Desertion::class),
            'stats' => [
                'open' => (clone $statsBase)->whereIn('hr_status', [
                    DesertionHrStatus::Reported->value,
                    DesertionHrStatus::Investigating->value,
                    DesertionHrStatus::Confirmed->value,
                ])->count(),
                'returned' => (clone $statsBase)->where('hr_status', DesertionHrStatus::Returned)->count(),
                'closed' => (clone $statsBase)->where('hr_status', DesertionHrStatus::Closed)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Desertion::class);

        $user = request()->user();
        $regionId = $user->regionId();

        return view('hr.desertions.create', [
            'guards' => Guard::query()
                ->activeEmployment()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('full_name')
                ->get(['id', 'employment_id', 'full_name', 'current_site_id']),
            'sites' => Site::query()
                ->when($user->mustStayInOwnRegion(), fn ($q) => $q->where('region_id', $regionId))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Desertion::class);

        $data = $request->validate([
            'guard_id' => ['required', 'exists:guards,id'],
            'date_reported' => ['required', 'date'],
            'last_known_duty_date' => ['nullable', 'date'],
            'last_known_site_id' => ['nullable', 'exists:sites,id'],
            'circumstances' => ['nullable', 'string', 'max:5000'],
            'action_taken' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $user = $request->user();
        if ($user->mustStayInOwnRegion()) {
            $guard = Guard::query()->find($data['guard_id']);
            if (! $guard || ! $user->canAccessRegion($guard->region_id)) {
                return back()->withInput()->withErrors(['guard_id' => 'You can only report desertions for guards in your region.']);
            }
        }

        $desertion = $this->desertions->report($data);

        return redirect()->route('desertions.show', $desertion)->with('status', 'Desertion reported. Guard marked as deserted.');
    }

    public function show(Desertion $desertion): View
    {
        $this->authorize('view', $desertion);

        $desertion->load(['assignedGuard', 'lastKnownSite', 'reporter']);

        return view('hr.desertions.show', [
            'desertion' => $desertion,
            'statuses' => DesertionHrStatus::cases(),
            'canManage' => request()->user()->can('update', $desertion),
        ]);
    }

    public function updateStatus(Request $request, Desertion $desertion): RedirectResponse
    {
        $this->authorize('update', $desertion);

        $data = $request->validate([
            'hr_status' => ['required', Rule::in(DesertionHrStatus::values())],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->desertions->updateStatus(
                $desertion,
                DesertionHrStatus::from($data['hr_status']),
                $data['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['desertion' => $e->getMessage()]);
        }

        return back()->with('status', 'Desertion HR status updated.');
    }
}
