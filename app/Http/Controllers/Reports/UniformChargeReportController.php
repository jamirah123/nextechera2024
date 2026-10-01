<?php

namespace App\Http\Controllers\Reports;

use App\Enums\UniformChargeStatus;
use App\Http\Controllers\Controller;
use App\Models\GuardUniformChargeRevision;
use App\Services\UniformChargeExemptionService;
use App\Support\Access\Access;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UniformChargeReportController extends Controller
{
    public function __construct(private UniformChargeExemptionService $exemptions) {}

    public function index(Request $request): View
    {
        abort_unless($request->user() && Access::userCan($request->user(), 'hr.uniform_exemptions_manage'), 403);

        $request->merge([
            'period_year' => $request->filled('period_year') ? $request->input('period_year') : null,
            'period_month' => $request->filled('period_month') ? $request->input('period_month') : null,
        ]);

        $validated = $request->validate([
            'scope' => ['nullable', 'in:all,active,expired'],
            'period_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'period_month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $today = now()->toDateString();
        $scope = $validated['scope'] ?? 'all';
        $periodYear = isset($validated['period_year']) ? (int) $validated['period_year'] : null;
        $periodMonth = isset($validated['period_month']) ? (int) $validated['period_month'] : null;
        $periodEnd = ($periodYear && $periodMonth)
            ? Carbon::create($periodYear, $periodMonth, 1)->endOfMonth()->toDateString()
            : null;

        $query = GuardUniformChargeRevision::query()
            ->with(['assignedGuard.region', 'assignedGuard.currentSite', 'approver'])
            ->orderByDesc('effective_from')
            ->orderByDesc('id');

        if ($scope === 'active') {
            $query->where('status', UniformChargeStatus::Exempt)
                ->whereDate('effective_from', '<=', $today)
                ->where(function ($inner) use ($today) {
                    $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
                });
        } elseif ($scope === 'expired') {
            $query->where('status', UniformChargeStatus::Exempt)
                ->whereNotNull('effective_to')
                ->whereDate('effective_to', '<', $today);
        }

        if ($periodEnd !== null) {
            $query->whereDate('effective_from', '<=', $periodEnd)
                ->where(function ($inner) use ($periodEnd) {
                    $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodEnd);
                });
        }

        $revisions = $query->paginate(25)->withQueryString();

        $guardIds = $revisions->getCollection()->pluck('guard_id')->unique()->values();
        $currentByGuard = GuardUniformChargeRevision::query()
            ->whereIn('guard_id', $guardIds)
            ->whereDate('effective_from', '<=', $today)
            ->where(function ($inner) use ($today) {
                $inner->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique('guard_id')
            ->keyBy('guard_id');

        return view('reports.uniform-exemptions', [
            'revisions' => $revisions,
            'currentByGuard' => $currentByGuard,
            'filters' => [
                'scope' => $scope,
                'period_year' => $periodYear,
                'period_month' => $periodMonth,
            ],
            'companyUniformCharge' => (float) config('psg.payroll.uniform_charge', 0),
        ]);
    }
}
