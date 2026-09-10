<?php

namespace App\Services\Finance;

use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollPayslipExportService
{
    public function __construct(private ReportExportService $exports)
    {
    }

    public function downloadCsv(PayrollRun $run, PayrollPayslip $payslip): StreamedResponse
    {
        $payslip->load('deductions');

        $headers = ['Section', 'Label', 'Amount'];
        $rows = $payslip->isFixedSalary()
            ? (function () use ($payslip) {
                $overtimePay = round((float) $payslip->overtime_shifts * (float) $payslip->overtime_shift_rate, 2);
                $salaryPortion = round((float) $payslip->gross_pay - $overtimePay, 2);
                $rows = [
                    ['Earnings', 'Monthly salary', number_format((float) $payslip->base_shift_rate, 2, '.', '')],
                    ['Earnings', 'Period salary', number_format($salaryPortion, 2, '.', '')],
                ];
                if ($payslip->overtime_shifts > 0) {
                    $rows[] = ['Earnings', 'Overtime shifts ('.$payslip->overtime_shifts.')', number_format($overtimePay, 2, '.', '')];
                }
                $rows[] = ['Earnings', 'Gross pay', number_format((float) $payslip->gross_pay, 2, '.', '')];

                return $rows;
            })()
            : [
                ['Earnings', 'Normal shifts ('.$payslip->normal_shifts.')', number_format($payslip->normal_shifts * $payslip->base_shift_rate, 2, '.', '')],
                ['Earnings', 'Overtime shifts ('.$payslip->overtime_shifts.')', number_format($payslip->overtime_shifts * $payslip->overtime_shift_rate, 2, '.', '')],
                ['Earnings', 'Gross pay', number_format((float) $payslip->gross_pay, 2, '.', '')],
            ];

        foreach ($payslip->deductions as $deduction) {
            $rows[] = ['Deduction', $deduction->label, number_format((float) $deduction->amount, 2, '.', '')];
        }

        $rows[] = ['Summary', 'Net pay', number_format((float) $payslip->net_pay, 2, '.', '')];
        $rows[] = ['Bank', 'Bank name', $payslip->bank_name ?? ''];
        $rows[] = ['Bank', 'Account', $payslip->bank_account ?? ''];

        return $this->exports->downloadCsv(
            'psg-payslip-'.$payslip->employment_id.'-'.$run->reference.'.csv',
            $headers,
            $rows,
        );
    }
}
