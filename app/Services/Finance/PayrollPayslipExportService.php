<?php

namespace App\Services\Finance;

use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollPayslipExportService
{
    public function __construct(private ReportExportService $exports) {}

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
            : (function () use ($payslip) {
                $calc = $payslip->shiftEarningsBreakdown();
                $rows = [];
                if ($calc['monthly_gross'] !== null) {
                    $rows[] = ['Earnings', 'Gross salary', number_format($calc['monthly_gross'], 2, '.', '')];
                }
                $rows[] = ['Earnings', 'Divisor', (string) $calc['divisor']];
                $rows[] = ['Earnings', 'Average shift rate', number_format($calc['average_rate'], 2, '.', '')];
                $rows[] = ['Earnings', 'Normal shifts', (string) $calc['normal_shifts']];
                $rows[] = ['Earnings', 'OT shifts', (string) $calc['overtime_shifts']];
                $rows[] = ['Earnings', 'Total payable shifts', (string) $calc['payable_shifts']];
                if ($calc['overtime_uses_average_rate']) {
                    $rows[] = ['Earnings', 'Shift earnings', number_format($calc['shift_earnings'], 2, '.', '')];
                } else {
                    $rows[] = ['Earnings', 'Normal earnings', number_format($calc['normal_amount'], 2, '.', '')];
                    $rows[] = ['Earnings', 'OT earnings', number_format($calc['overtime_amount'], 2, '.', '')];
                    $rows[] = ['Earnings', 'Shift earnings', number_format($calc['shift_earnings'], 2, '.', '')];
                }

                return $rows;
            })();

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
