<?php

namespace App\Services\Finance;

use App\Mail\PayslipMail;
use App\Models\PayrollRun;
use Illuminate\Support\Facades\Mail;

class PayrollPayslipNotificationService
{
    public function __construct(private PayrollPayslipPdfService $pdf)
    {
    }

    public function sendForRun(PayrollRun $run): int
    {
        if (! config('psg.payroll.send_payslip_email_on_approve', true)) {
            return 0;
        }

        $sent = 0;

        $run->loadMissing('payslips');

        foreach ($run->payslips as $payslip) {
            if (! filled($payslip->payroll_email)) {
                continue;
            }

            Mail::to($payslip->payroll_email)->send(new PayslipMail(
                $run,
                $payslip,
                $this->pdf->renderBinary($run, $payslip),
            ));

            $sent++;
        }

        return $sent;
    }
}
