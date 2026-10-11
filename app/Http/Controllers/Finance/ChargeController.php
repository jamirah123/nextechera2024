<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\EmployeeMisconductCharge;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChargeController extends Controller
{
    public function __construct(private ReportExportService $exports) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'active';
        $personType = $request->string('person_type')->toString();

        $charges = EmployeeMisconductCharge::query()
            ->with([
                'assignedGuard:id,full_name,employment_id',
                'assignedStaff:id,full_name,employment_id',
            ])
            ->when($scope === 'active', fn ($q) => $q->active())
            ->when($personType === 'guard', fn ($q) => $q->whereNotNull('guard_id'))
            ->when($personType === 'staff', fn ($q) => $q->whereNotNull('staff_id'))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $like = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('reason', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhereHas('assignedGuard', fn ($g) => $g
                            ->where('full_name', 'like', $like)
                            ->orWhere('employment_id', 'like', $like))
                        ->orWhereHas('assignedStaff', fn ($s) => $s
                            ->where('full_name', 'like', $like)
                            ->orWhere('employment_id', 'like', $like));
                });
            })
            ->latest('id')
            ->paginate(table_per_page())
            ->withQueryString();

        $base = EmployeeMisconductCharge::query();

        return view('finance.charges.index', [
            'charges' => $charges,
            'scope' => $scope,
            'filters' => $request->only(['q', 'person_type', 'scope']),
            'exportQuery' => array_filter($request->only(['q', 'person_type', 'scope']), fn ($v) => filled($v)),
            'stats' => [
                'active' => (clone $base)->active()->count(),
                'balance' => (float) (clone $base)->active()->sum('balance_remaining'),
                'guards' => (clone $base)->active()->whereNotNull('guard_id')->count(),
                'staff' => (clone $base)->active()->whereNotNull('staff_id')->count(),
                'all' => (clone $base)->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'active';
        $personType = $request->string('person_type')->toString();

        $rows = EmployeeMisconductCharge::query()
            ->with(['assignedGuard:id,full_name,employment_id', 'assignedStaff:id,full_name,employment_id'])
            ->when($scope === 'active', fn ($q) => $q->active())
            ->when($personType === 'guard', fn ($q) => $q->whereNotNull('guard_id'))
            ->when($personType === 'staff', fn ($q) => $q->whereNotNull('staff_id'))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $like = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('reason', 'like', $like)
                        ->orWhereHas('assignedGuard', fn ($g) => $g->where('full_name', 'like', $like)->orWhere('employment_id', 'like', $like))
                        ->orWhereHas('assignedStaff', fn ($s) => $s->where('full_name', 'like', $like)->orWhere('employment_id', 'like', $like));
                });
            })
            ->latest('id')
            ->limit(5000)
            ->get();

        $headers = ['#', 'Person type', 'Person', 'Employment ID', 'Reason', 'Incident', 'Original', 'Balance', 'Installment', 'Status', 'Created'];
        $data = $rows->values()->map(fn (EmployeeMisconductCharge $charge, int $i) => [
            $i + 1,
            $charge->guard_id ? 'Guard' : 'Staff',
            $charge->assignedGuard?->full_name ?? $charge->assignedStaff?->full_name,
            $charge->assignedGuard?->employment_id ?? $charge->assignedStaff?->employment_id,
            $charge->reason,
            $charge->incident_on?->format('Y-m-d'),
            Money::format($charge->original_amount),
            Money::format($charge->balance_remaining),
            $charge->monthly_installment !== null ? Money::format($charge->monthly_installment) : '',
            $charge->is_active && (float) $charge->balance_remaining > 0 ? 'Active' : 'Closed',
            $charge->created_at?->format('Y-m-d'),
        ]);

        return $this->exports->downloadCsv('psg-misconduct-charges.csv', $headers, $data);
    }
}
