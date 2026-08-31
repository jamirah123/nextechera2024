<?php

namespace App\Http\Controllers\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Models\Region;
use App\Models\Staff;
use App\Services\StaffService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function __construct(private StaffService $staff)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        $staffMembers = Staff::query()
            ->with(['region:id,name,code'])
            ->search($request->string('q')->toString())
            ->when($request->filled('employment_status'), fn ($q) => $q->where('employment_status', $request->string('employment_status')))
            ->when($request->filled('region_id'), fn ($q) => $q->where('region_id', $request->integer('region_id')))
            ->latest()
            ->paginate(table_per_page())
            ->withQueryString();

        return view('staff.index', [
            'staffMembers' => $staffMembers,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'filters' => $request->only(['q', 'employment_status', 'region_id']),
            'canManage' => $request->user()->can('create', Staff::class),
            'canDelete' => $request->user()->can('deleteAny', Staff::class),
            'stats' => [
                'total' => Staff::query()->count(),
                'active' => Staff::query()->activeEmployment()->count(),
            ],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Staff::class);

        return view('staff.create', [
            'regions' => Region::query()->active()->orderBy('name')->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'genders' => GuardGender::cases(),
            'nextEmploymentId' => $this->staff->nextEmploymentId(),
        ]);
    }

    public function store(StoreStaffRequest $request): RedirectResponse
    {
        $member = $this->staff->createStaff($request->validated());

        return redirect()
            ->route('staff.show', $member)
            ->with('status', 'Staff member registered successfully.');
    }

    public function show(Staff $staff): View
    {
        $this->authorize('view', $staff);

        $staff->load(['region', 'salaryAdvances', 'creator', 'updater']);

        return view('staff.show', [
            'staff' => $staff,
            'canManage' => request()->user()->can('update', $staff),
            'canManageFinance' => request()->user()->can('manageFinance'),
            'canDelete' => request()->user()->can('delete', $staff),
        ]);
    }

    public function edit(Staff $staff): View
    {
        $this->authorize('update', $staff);

        return view('staff.edit', [
            'staff' => $staff,
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'employmentStatuses' => EmploymentStatus::cases(),
            'genders' => GuardGender::cases(),
        ]);
    }

    public function update(UpdateStaffRequest $request, Staff $staff): RedirectResponse
    {
        $this->staff->updateStaff($staff, $request->validated());

        return redirect()
            ->route('staff.show', $staff)
            ->with('status', 'Staff profile updated successfully.');
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $staff->delete();

        return redirect()
            ->route('staff.index')
            ->with('status', 'Staff member archived successfully.');
    }
}
