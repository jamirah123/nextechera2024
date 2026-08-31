<?php

namespace App\Services\Finance;

use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollBankExportService
{
    public function __construct(private ReportExportService $exports)
    {
    }

    public function downloadBankFile(PayrollRun $run): StreamedResponse
    {
        $headers = [
            'Employee ID',
            'Employee Name',
            'Bank Name',
            'Account Number',
            'Net Pay',
            'Currency',
            'Payment Reference',
            'Narration',
        ];

        $rows = $run->payslips()
            ->orderBy('employment_id')
            ->get()
            ->map(fn (PayrollPayslip $payslip) => [
                $payslip->employment_id,
                $payslip->full_name,
                $payslip->bank_name ?? '',
                $payslip->bank_account ?? '',
                number_format((float) $payslip->net_pay, 2, '.', ''),
                $run->currency,
                $run->reference,
                'Salary '.$run->periodLabel(),
            ]);

        return $this->exports->downloadCsv(
            'psg-payroll-bank-'.$run->reference.'.csv',
            $headers,
            $rows,
        );
    }

    /**
     * @return list<string>
     */
    public function payslipExportHeaders(): array
    {
        return [
            'Employment ID',
            'Name',
            'Normal Shifts',
            'Overtime Shifts',
            'Total Shifts',
            'Gross Pay',
            'Deductions',
            'Net Pay',
            'Bank',
            'Account',
        ];
    }

    /**
     * @return list<list<string|int|float>>
     */
    public function payslipExportRows(PayrollRun $run): array
    {
        return $run->payslips()
            ->orderBy('employment_id')
            ->get()
            ->map(fn (PayrollPayslip $payslip) => [
                $payslip->employment_id,
                $payslip->full_name,
                $payslip->normal_shifts,
                $payslip->overtime_shifts,
                $payslip->total_shifts,
                number_format((float) $payslip->gross_pay, 2, '.', ''),
                number_format((float) $payslip->total_deductions, 2, '.', ''),
                number_format((float) $payslip->net_pay, 2, '.', ''),
                $payslip->bank_name ?? '',
                $payslip->bank_account ?? '',
            ])
            ->all();
    }
}
