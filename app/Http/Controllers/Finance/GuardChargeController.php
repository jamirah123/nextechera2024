<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMisconductCharge;
use App\Models\Guard;
use App\Services\Finance\EmployeeChargeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class GuardChargeController extends Controller
{
    public function __construct(private EmployeeChargeService $charges) {}

    public function store(Request $request, Guard $guard): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'charge_reason' => ['required', 'string', 'max:160'],
            'charge_incident_on' => ['nullable', 'date'],
            'charge_amount' => ['required', 'numeric', 'min:0.01'],
            'charge_installment' => ['nullable', 'numeric', 'min:0.01'],
            'charge_notes' => ['nullable', 'string'],
        ]);

        try {
            $this->charges->createForGuard($guard, [
                'reason' => $data['charge_reason'],
                'incident_on' => $data['charge_incident_on'] ?? null,
                'original_amount' => $data['charge_amount'],
                'monthly_installment' => $data['charge_installment'] ?? null,
                'notes' => $data['charge_notes'] ?? null,
            ], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['charge' => $e->getMessage()])->withInput();
        }

        return back()->with('status', 'Misconduct charge recorded.');
    }

    public function writeOff(Guard $guard, EmployeeMisconductCharge $charge): RedirectResponse
    {
        Gate::authorize('manageFinance');
        abort_unless((int) $charge->guard_id === (int) $guard->id, 404);

        $this->charges->writeOff($charge, request()->string('reason')->toString() ?: null);

        return back()->with('status', 'Misconduct charge written off.');
    }
}
