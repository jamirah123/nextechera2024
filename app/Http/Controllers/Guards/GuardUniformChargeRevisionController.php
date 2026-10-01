<?php

namespace App\Http\Controllers\Guards;

use App\Enums\UniformChargeStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Guards\StoreGuardUniformChargeRevisionRequest;
use App\Models\Guard;
use App\Services\UniformChargeExemptionService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class GuardUniformChargeRevisionController extends Controller
{
    public function __construct(private UniformChargeExemptionService $exemptions) {}

    public function store(StoreGuardUniformChargeRevisionRequest $request, Guard $guard): RedirectResponse
    {
        try {
            $this->exemptions->record(
                $guard,
                UniformChargeStatus::from($request->validated('status')),
                Carbon::parse($request->validated('effective_from')),
                $request->validated('reason'),
                $request->validated('notes'),
                $request->user(),
            );
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['effective_from' => $exception->getMessage()]);
        }

        return redirect()
            ->route('guards.show', $guard)
            ->with('status', 'Uniform charge change recorded. Earlier payroll stays as calculated.');
    }
}
