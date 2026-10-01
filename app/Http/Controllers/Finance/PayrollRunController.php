<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Jobs\CalculatePayrollRunJob;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\Site;
use App\Services\Finance\PayrollBankExportService;
use App\Support\Access\SupervisorPayAccess;
use App\Services\Finance\PayrollRunService;
use App\Services\ReportExportService;
use App\Support\Finance\PayrollAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollRunController extends Controller
{
    public function __construct(
        private PayrollRunService $payroll,
        private PayrollBankExportService $exports,
        private ReportExportService $reportExports,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewFinance');

        $scope = $request->string('scope')->toString() === 'all' ? 'all' : 'current';

        $runs = PayrollRun::query()
            ->with(['region:id,name', 'site:id,name,code'])
            ->search($request->string('q')->toString())
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($scope === 'current', fn ($q) => $q->whereNot('status', PayrollRunStatus::Cancelled->value))
            ->latest('period_year')
            ->latest('period_month')
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('finance.payroll.index', [
            'runs' => $runs,
            'statuses' => PayrollRunStatus::cases(),
            'filters' => $request->only(['q', 'status']),
            'scope' => $scope,
            'canSubmit' => PayrollAccess::canSubmit($request->user()),
            'canApprove' => PayrollAccess::canApprove($request->user()),
            'stats' => $this->runStats(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageFinance');

        $lastClosed = PayrollRunService::lastClosedPeriod();

        return view('finance.payroll.create', [
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'region_id']),
            'defaultYear' => (int) old('period_year', $lastClosed->year),
            'defaultMonth' => (int) old('period_month', $lastClosed->month),
            'lastClosedLabel' => $lastClosed->format('F Y'),
            'existingRuns' => PayrollRun::query()
                ->whereNot('status', PayrollRunStatus::Cancelled->value)
                ->with(['region:id,name', 'site:id,name,code'])
                ->orderByDesc('period_year')
                ->orderByDesc('period_month')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manageFinance');

        $data = $request->validate([
            'period_year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'period_month' => ['required', 'integer', 'min:1', 'max:12'],
            'region_id' => ['nullable', 'exists:regions,id'],
            'site_id' => ['nullable', 'exists:sites,id'],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $run = $this->payroll->createDraft($data, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['payroll' => $e->getMessage()]);
        }

        return redirect()
            ->route('payroll.show', $run)
            ->with('status', 'Payroll run created. Calculate to pull completed shifts.');
    }

    public function show(PayrollRun $payroll): View
    {
        Gate::authorize('viewFinance');

        $payroll->load([
            'region:id,name',
            'site:id,name,code',
            'creator:id,name',
            'submitter:id,name',
            'approver:id,name',
            'payer:id,name',
            'payment:id,payroll_run_id,reference',
        ]);

        $user = request()->user();
        $paySummary = null;

        if (SupervisorPayAccess::hidesSupervisorPay($user)) {
            $visible = $payroll->payslips()->visibleTo($user);
            $totals = (clone $visible)
                ->selectRaw('count(*) as payslip_count, coalesce(sum(gross_pay), 0) as gross_total, coalesce(sum(total_deductions), 0) as deductions_total, coalesce(sum(net_pay), 0) as net_total')
                ->first();
            $paySummary = [
                'count' => (int) $totals->payslip_count,
                'gross' => (float) $totals->gross_total,
                'deductions' => (float) $totals->deductions_total,
                'net' => (float) $totals->net_total,
            ];
        }

        $payslips = $payroll->payslips()
            ->visibleTo($user)
            ->with([
                'assignedStaff.supervisorProfile:id,staff_id',
                'assignedGuard.supervisorProfile:id,guard_id',
            ])
            ->orderBy('employment_id')
            ->paginate(25)
            ->withQueryString();

        return view('finance.payroll.show', [
            'run' => $payroll,
            'payslips' => $payslips,
            'paySummary' => $paySummary,
            'canSubmit' => PayrollAccess::canSubmit($user),
            'canApprove' => PayrollAccess::canApprove($user),
        ]);
    }

    public function calculate(PayrollRun $payroll): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            if (config('queue.default') === 'sync') {
                $this->payroll->calculate($payroll);
            } else {
                CalculatePayrollRunJob::dispatch($payroll->id);
            }
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        $message = config('queue.default') === 'sync'
            ? 'Payroll calculated. Review payslips and submit for approval when ready.'
            : 'Payroll calculation queued. Refresh this page in a moment.';

        return back()->with('status', $message);
    }

    public function submit(PayrollRun $payroll): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->payroll->submit($payroll, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return back()->with('status', 'Payroll submitted for Managing Director approval.');
    }

    public function approve(PayrollRun $payroll): RedirectResponse
    {
        Gate::authorize('approvePayroll');

        try {
            $this->payroll->approve($payroll, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return back()->with('status', 'Payroll run approved.');
    }

    public function reject(Request $request, PayrollRun $payroll): RedirectResponse
    {
        Gate::authorize('approvePayroll');

        if (! PayrollAccess::canReject($request->user(), $payroll)) {
            abort(403, 'You cannot return this payroll run to finance.');
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        try {
            $this->payroll->reject($payroll, $request->user(), $data['reason']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return back()->with('status', 'Payroll returned to finance for revision.');
    }

    public function pay(PayrollRun $payroll): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->payroll->markPaid($payroll, request()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return back()->with('status', 'Payroll run marked as paid.');
    }

    public function cancel(PayrollRun $payroll): RedirectResponse
    {
        $user = request()->user();

        if (! PayrollAccess::canCancel($user, $payroll)) {
            abort(403, 'You are not allowed to delete this payroll run.');
        }

        try {
            $this->payroll->cancel($payroll);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return redirect()
            ->route('payroll.index')
            ->with('status', 'Payroll run deleted.');
    }

    public function exportBank(PayrollRun $payroll): StreamedResponse
    {
        Gate::authorize('viewFinance');

        if (! in_array($payroll->status, [PayrollRunStatus::Approved, PayrollRunStatus::Paid], true)) {
            abort(403, 'Bank file is available after payroll approval.');
        }

        return $this->exports->downloadBankFile($payroll);
    }

    public function exportPayslips(PayrollRun $payroll): StreamedResponse
    {
        Gate::authorize('viewFinance');

        return $this->reportExports->downloadCsv(
            'psg-payroll-payslips-'.$payroll->reference.'.csv',
            $this->exports->payslipExportHeaders(),
            $this->exports->payslipExportRows($payroll),
        );
    }

    /** @return array{draft: int, calculated: int, submitted: int, approved: int, paid: int} */
    private function runStats(): array
    {
        $counts = [];

        foreach (PayrollRun::query()->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->get() as $row) {
            $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
            $counts[$status] = (int) $row->aggregate;
        }

        return [
            'draft' => $counts[PayrollRunStatus::Draft->value] ?? 0,
            'calculated' => $counts[PayrollRunStatus::Calculated->value] ?? 0,
            'submitted' => $counts[PayrollRunStatus::Submitted->value] ?? 0,
            'approved' => $counts[PayrollRunStatus::Approved->value] ?? 0,
            'paid' => $counts[PayrollRunStatus::Paid->value] ?? 0,
        ];
    }
}
