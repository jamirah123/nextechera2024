<?php

namespace App\Http\Controllers\Finance;

use App\Enums\PayrollDeductionType;
use App\Http\Controllers\Controller;
use App\Models\PayrollDeduction;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Services\Finance\PayrollCalculationService;
use App\Services\Finance\PayrollPayslipExportService;
use App\Services\Finance\PayrollPayslipPdfService;
use App\Support\Access\SupervisorPayAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollPayslipController extends Controller
{
    public function __construct(
        private PayrollCalculationService $calculator,
        private PayrollPayslipExportService $exports,
        private PayrollPayslipPdfService $pdf,
    ) {}

    public function show(PayrollRun $payroll, PayrollPayslip $payslip): View
    {
        Gate::authorize('viewFinance');

        abort_unless((int) $payslip->payroll_run_id === (int) $payroll->id, 404);
        $this->denyHiddenSupervisorPay($payslip);

        $payslip->load(['deductions', 'shifts.site:id,name,code', 'assignedGuard:id,employment_id,nssf_number', 'assignedStaff']);

        return view('finance.payroll.payslip', [
            'run' => $payroll,
            'payslip' => $payslip,
            'canManage' => request()->user()->can('manageFinance'),
        ]);
    }

    public function storeDeduction(Request $request, PayrollRun $payroll, PayrollPayslip $payslip): RedirectResponse
    {
        Gate::authorize('manageFinance');
        abort_unless((int) $payslip->payroll_run_id === (int) $payroll->id, 404);

        $data = $request->validate([
            'type' => ['required', Rule::in(PayrollDeductionType::values())],
            'label' => ['nullable', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        if (in_array($data['type'], [PayrollDeductionType::Paye->value, PayrollDeductionType::Nssf->value], true)) {
            return back()->withErrors(['deduction' => 'Statutory deductions are applied automatically during calculation.']);
        }

        try {
            $this->calculator->addManualDeduction($payslip, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['deduction' => $e->getMessage()]);
        }

        return back()->with('status', 'Deduction added.');
    }

    public function destroyDeduction(PayrollRun $payroll, PayrollPayslip $payslip, PayrollDeduction $deduction): RedirectResponse
    {
        Gate::authorize('manageFinance');
        abort_unless((int) $payslip->payroll_run_id === (int) $payroll->id, 404);
        abort_unless((int) $deduction->payroll_payslip_id === (int) $payslip->id, 404);

        try {
            $this->calculator->removeDeduction($deduction);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['deduction' => $e->getMessage()]);
        }

        return back()->with('status', 'Deduction removed.');
    }

    public function export(PayrollRun $payroll, PayrollPayslip $payslip): StreamedResponse
    {
        Gate::authorize('viewFinance');
        abort_unless((int) $payslip->payroll_run_id === (int) $payroll->id, 404);
        $this->denyHiddenSupervisorPay($payslip);

        return $this->exports->downloadCsv($payroll, $payslip);
    }

    public function print(PayrollRun $payroll, PayrollPayslip $payslip): View|Response
    {
        Gate::authorize('viewFinance');
        abort_unless((int) $payslip->payroll_run_id === (int) $payroll->id, 404);
        $this->denyHiddenSupervisorPay($payslip);

        $payslip->load(['deductions', 'assignedGuard:id,employment_id,nssf_number', 'assignedStaff']);

        if (request()->query('format') === 'pdf') {
            return response($this->pdf->renderBinary($payroll, $payslip), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="payslip-'.$payslip->employment_id.'.pdf"',
            ]);
        }

        return view('finance.payroll.payslip-print', [
            'run' => $payroll,
            'payslip' => $payslip,
            'autoPrint' => request()->boolean('download'),
        ]);
    }

    private function denyHiddenSupervisorPay(PayrollPayslip $payslip): void
    {
        $user = request()->user();

        if (! $user || ! SupervisorPayAccess::hidesSupervisorPay($user)) {
            return;
        }

        $payslip->loadMissing([
            'assignedStaff.supervisorProfile:id,staff_id',
            'assignedGuard.supervisorProfile:id,guard_id',
        ]);

        abort_unless(SupervisorPayAccess::canViewPayslip($user, $payslip), 403);
    }
}
