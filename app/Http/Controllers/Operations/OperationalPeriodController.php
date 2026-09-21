<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\OperationalPeriod;
use App\Services\Operations\OperationalPeriodService;
use App\Support\Access\RolePermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OperationalPeriodController extends Controller
{
    public function __construct(
        private OperationalPeriodService $periods,
        private RolePermissionService $permissions,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->permissions->userCan($request->user(), 'operations.periods_manage')
            || $this->permissions->userCan($request->user(), 'operations.historical_correct'), 403);

        $this->periods->ensureRollingWindow();

        $periods = OperationalPeriod::query()
            ->with('closer:id,name')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('operations.periods.index', [
            'periods' => $periods,
            'canManage' => $this->permissions->userCan($request->user(), 'operations.periods_manage'),
        ]);
    }

    public function close(Request $request, OperationalPeriod $period): RedirectResponse
    {
        abort_unless($this->permissions->userCan($request->user(), 'operations.periods_manage'), 403);

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->periods->close($period, $request->user(), $data['notes'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('status', $period->label().' finalized. Ordinary edits to that month’s operational records are blocked.');
    }

    public function reopen(Request $request, OperationalPeriod $period): RedirectResponse
    {
        abort_unless($this->permissions->userCan($request->user(), 'operations.periods_manage'), 403);

        try {
            $this->periods->reopen($period, $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['period' => $e->getMessage()]);
        }

        return back()->with('status', $period->label().' re-opened for corrections.');
    }
}
