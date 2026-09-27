<?php

namespace App\Http\Controllers\Guards;

use App\Enums\SalaryChangeReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guards\StoreGuardSalaryRevisionRequest;
use App\Models\Guard;
use App\Services\GuardSalaryService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class GuardSalaryRevisionController extends Controller
{
    public function __construct(private GuardSalaryService $salaries) {}

    public function store(StoreGuardSalaryRevisionRequest $request, Guard $guard): RedirectResponse
    {
        try {
            $this->salaries->increment(
                $guard,
                (float) $request->validated('salary'),
                Carbon::parse($request->validated('effective_from')),
                SalaryChangeReason::from($request->validated('reason')),
                $request->user(),
                $request->validated('notes'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['effective_from' => $exception->getMessage()]);
        }

        return redirect()
            ->route('guards.show', $guard)
            ->with('status', 'Salary change recorded. Earlier salaries stay on file.');
    }
}
