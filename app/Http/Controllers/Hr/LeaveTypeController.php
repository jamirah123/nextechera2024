<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\LeaveTypeConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LeaveTypeController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', LeaveTypeConfig::class);

        return view('hr.leaves.types', [
            'types' => LeaveTypeConfig::query()->orderBy('name')->get(),
            'canManage' => request()->user()->can('create', LeaveTypeConfig::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', LeaveTypeConfig::class);

        $data = $this->validated($request);
        $data['code'] = Str::slug((string) $data['code'], '_');

        LeaveTypeConfig::query()->create($data);

        return back()->with('status', 'Leave type saved.');
    }

    public function update(Request $request, LeaveTypeConfig $leaveTypeConfig): RedirectResponse
    {
        $this->authorize('update', $leaveTypeConfig);

        $leaveTypeConfig->update($this->validated($request, $leaveTypeConfig));

        return back()->with('status', 'Leave type updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?LeaveTypeConfig $current = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:leave_types,code'.($current ? ','.$current->id : '')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'pay_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_days_per_year' => ['nullable', 'numeric', 'min:0'],
            'eligibility' => ['required', 'in:all,guards,staff'],
        ]);

        $data['is_paid'] = (float) $data['pay_percent'] > 0;
        $data['requires_document'] = $request->boolean('requires_document');
        $data['requires_approval'] = $request->boolean('requires_approval');
        $data['count_weekends'] = $request->boolean('count_weekends');
        $data['count_public_holidays'] = $request->boolean('count_public_holidays');
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
