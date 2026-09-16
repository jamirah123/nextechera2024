<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Guard;
use App\Models\GuardSalaryAdvance;
use App\Services\Finance\GuardAdvanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class GuardAdvanceController extends Controller
{
    public function __construct(private GuardAdvanceService $advances) {}

    public function store(Request $request, Guard $guard): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'label' => ['required', 'string', 'max:120'],
            'original_amount' => ['required', 'numeric', 'min:0.01'],
            'monthly_installment' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $this->advances->create($guard, $data, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['advance' => $e->getMessage()]);
        }

        return back()->with('status', 'Salary advance recorded.');
    }

    public function writeOff(Guard $guard, GuardSalaryAdvance $advance): RedirectResponse
    {
        Gate::authorize('manageFinance');
        abort_unless((int) $advance->guard_id === (int) $guard->id, 404);

        try {
            $this->advances->writeOff($advance, request()->string('reason')->toString() ?: null);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['advance' => $e->getMessage()]);
        }

        return back()->with('status', 'Advance written off.');
    }
}
