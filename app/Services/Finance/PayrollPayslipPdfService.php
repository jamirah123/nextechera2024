<?php

namespace App\Services\Finance;

use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\View;

class PayrollPayslipPdfService
{
    public function renderBinary(PayrollRun $run, PayrollPayslip $payslip): string
    {
        $html = View::make('finance.payroll.payslip-print', [
            'run' => $run,
            'payslip' => $payslip->loadMissing(['deductions', 'assignedStaff', 'assignedGuard']),
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
