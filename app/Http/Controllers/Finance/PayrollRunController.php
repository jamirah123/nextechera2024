<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PayrollRunStatus;
use App\Http\Controllers\Controller;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\Site;
use App\Services\Finance\PayrollBankExportService;
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
    ) {
    }

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
            ->paginate(table_per_page())
            ->withQueryString();

        return view('finance.payroll.index', [
            'runs' => $runs,
            'statuses' => PayrollRunStatus::cases(),
            'filters' => $request->only(['q', 'status']),
            'scope' => $scope,
            'canSubmit' => PayrollAccess::canSubmit($request->user()),
            'canApprove' => PayrollAccess::canApprove($request->user()),
            'stats' => [
                'draft' => PayrollRun::query()->where('status', PayrollRunStatus::Draft)->count(),
                'calculated' => PayrollRun::query()->where('status', PayrollRunStatus::Calculated)->count(),
                'submitted' => PayrollRun::query()->where('status', PayrollRunStatus::Submitted)->count(),
                'approved' => PayrollRun::query()->where('status', PayrollRunStatus::Approved)->count(),
                'paid' => PayrollRun::query()->where('status', PayrollRunStatus::Paid)->count(),
            ],
        ]);
    }

    public function create(): View
    {
        Gate::authorize('manageFinance');

        return view('finance.payroll.create', [
            'regions' => Region::query()->orderBy('name')->get(['id', 'name', 'code']),
            'sites' => Site::query()->orderBy('name')->get(['id', 'name', 'code', 'region_id']),
            'defaultYear' => now()->year,
            'defaultMonth' => now()->month,
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

        $payslips = $payroll->payslips()
            ->orderBy('employment_id')
            ->paginate(table_per_page())
            ->withQueryString();

        return view('finance.payroll.show', [
            'run' => $payroll,
            'payslips' => $payslips,
            'canSubmit' => PayrollAccess::canSubmit(request()->user()),
            'canApprove' => PayrollAccess::canApprove(request()->user()),
        ]);
    }

    public function calculate(PayrollRun $payroll): RedirectResponse
    {
        Gate::authorize('manageFinance');

        try {
            $this->payroll->calculate($payroll);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        return back()->with('status', 'Payroll calculated. Review payslips and submit for approval when ready.');
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
            abort(403, 'You are not allowed to cancel or reject this payroll run.');
        }

        $previousStatus = $payroll->status;

        try {
            $this->payroll->cancel($payroll);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['payroll' => $e->getMessage()]);
        }

        $rejected = in_array($previousStatus, [PayrollRunStatus::Submitted, PayrollRunStatus::Approved, PayrollRunStatus::Paid], true);

        return redirect()
            ->route('payroll.index')
            ->with('status', $rejected ? 'Payroll run rejected.' : 'Payroll run deleted.');
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
}
