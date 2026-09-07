<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\GuardSalaryAdvance;
use App\Services\ReportExportService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdvanceController extends Controller
{
    public function __construct(private ReportExportService $exports)
    {
    }

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'active';
        $personType = $request->string('person_type')->toString();

        $advances = GuardSalaryAdvance::query()
            ->with([
                'assignedGuard:id,full_name,employment_id',
                'assignedStaff:id,full_name,employment_id',
                'creator:id,name',
            ])
            ->when($scope === 'active', fn ($q) => $q->active())
            ->when($personType === 'guard', fn ($q) => $q->whereNotNull('guard_id'))
            ->when($personType === 'staff', fn ($q) => $q->whereNotNull('staff_id'))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $like = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('label', 'like', $like)
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

        $base = GuardSalaryAdvance::query();

        return view('finance.advances.index', [
            'advances' => $advances,
            'scope' => $scope,
            'filters' => $request->only(['q', 'person_type', 'scope']),
            'canManage' => $request->user()->can('manageFinance'),
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

        $rows = GuardSalaryAdvance::query()
            ->with(['assignedGuard:id,full_name,employment_id', 'assignedStaff:id,full_name,employment_id'])
            ->when($scope === 'active', fn ($q) => $q->active())
            ->when($personType === 'guard', fn ($q) => $q->whereNotNull('guard_id'))
            ->when($personType === 'staff', fn ($q) => $q->whereNotNull('staff_id'))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $like = '%'.$request->string('q')->toString().'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('label', 'like', $like)
                        ->orWhereHas('assignedGuard', fn ($g) => $g->where('full_name', 'like', $like)->orWhere('employment_id', 'like', $like))
                        ->orWhereHas('assignedStaff', fn ($s) => $s->where('full_name', 'like', $like)->orWhere('employment_id', 'like', $like));
                });
            })
            ->latest('id')
            ->limit(5000)
            ->get();

        $headers = ['#', 'Person type', 'Person', 'Employment ID', 'Label', 'Original', 'Balance', 'Installment', 'Status', 'Created'];
        $data = $rows->values()->map(fn (GuardSalaryAdvance $advance, int $i) => [
            $i + 1,
            $advance->guard_id ? 'Guard' : 'Staff',
            $advance->assignedGuard?->full_name ?? $advance->assignedStaff?->full_name,
            $advance->assignedGuard?->employment_id ?? $advance->assignedStaff?->employment_id,
            $advance->label,
            Money::format($advance->original_amount),
            Money::format($advance->balance_remaining),
            $advance->monthly_installment !== null ? Money::format($advance->monthly_installment) : '',
            $advance->is_active && (float) $advance->balance_remaining > 0 ? 'Active' : 'Closed',
            $advance->created_at?->format('Y-m-d'),
        ]);

        return $this->exports->downloadCsv('psg-salary-advances.csv', $headers, $data);
    }
}
