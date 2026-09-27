<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\StorePositionRequest;
use App\Models\Position;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PositionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Position::class);

        return view('hr.positions.index', [
            'positions' => Position::query()->orderBy('name')->get(),
            'canManage' => request()->user()->can('manage', Position::class),
        ]);
    }

    public function store(StorePositionRequest $request): RedirectResponse
    {
        Position::query()->create([
            ...$request->safe()->except([
                'is_guard_position',
                'is_staff_position',
                'is_supervisor_position',
                'is_management_position',
                'eligible_for_deployment',
                'eligible_for_shifts',
                'eligible_for_overtime',
            ]),
            'is_guard_position' => $request->boolean('is_guard_position'),
            'is_staff_position' => $request->boolean('is_staff_position'),
            'is_supervisor_position' => $request->boolean('is_supervisor_position'),
            'is_management_position' => $request->boolean('is_management_position'),
            'eligible_for_deployment' => $request->boolean('eligible_for_deployment'),
            'eligible_for_shifts' => $request->boolean('eligible_for_shifts'),
            'eligible_for_overtime' => $request->boolean('eligible_for_overtime'),
            'is_active' => true,
        ]);

        return redirect()->route('positions.index')->with('status', 'Position saved.');
    }

    public function update(StorePositionRequest $request, Position $position): RedirectResponse
    {
        $this->authorize('update', $position);

        $position->update([
            'name' => $request->validated('name'),
            'code' => $request->validated('code'),
            'salary_type' => $request->validated('salary_type'),
            'is_guard_position' => $request->boolean('is_guard_position'),
            'is_staff_position' => $request->boolean('is_staff_position'),
            'is_supervisor_position' => $request->boolean('is_supervisor_position'),
            'is_management_position' => $request->boolean('is_management_position'),
            'eligible_for_deployment' => $request->boolean('eligible_for_deployment'),
            'eligible_for_shifts' => $request->boolean('eligible_for_shifts'),
            'eligible_for_overtime' => $request->boolean('eligible_for_overtime'),
        ]);

        return redirect()->route('positions.index')->with('status', 'Position updated.');
    }
}
