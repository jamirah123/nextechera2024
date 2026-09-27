<?php

namespace App\Http\Controllers\Hr;

use App\Enums\StaffSalaryChangeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreStaffSalaryRevisionRequest;
use App\Models\Staff;
use App\Services\StaffSalaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class StaffSalaryRevisionController extends Controller
{
    public function __construct(private StaffSalaryService $salaries) {}

    public function store(StoreStaffSalaryRevisionRequest $request, Staff $staff): RedirectResponse
    {
        try {
            $this->salaries->change(
                $staff,
                (float) $request->validated('salary'),
                Carbon::parse($request->validated('effective_from')),
                StaffSalaryChangeType::from($request->validated('change_type')),
                (string) $request->validated('reason'),
                $request->user(),
                $request->validated('job_title'),
                $request->validated('grade'),
                $request->validated('notes'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['effective_from' => $exception->getMessage()]);
        }

        return redirect()
            ->route('staff.show', $staff)
            ->with('status', 'Salary change recorded. Earlier salaries stay on file.');
    }
}
