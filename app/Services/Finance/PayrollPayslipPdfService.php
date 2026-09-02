<?php

namespace App\Services\Finance;

use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Support\Documents\DompdfRenderer;

class PayrollPayslipPdfService
{
    public function __construct(private DompdfRenderer $pdf)
    {
    }

    public function renderBinary(PayrollRun $run, PayrollPayslip $payslip): string
    {
        return $this->pdf->renderView('finance.payroll.payslip-pdf', [
            'run' => $run,
            'payslip' => $payslip->loadMissing(['deductions', 'assignedStaff', 'assignedGuard']),
        ]);
    }
}
