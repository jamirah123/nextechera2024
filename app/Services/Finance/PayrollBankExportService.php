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
        $format = (string) config('psg.payroll.bank_export_format', 'generic');

        return match ($format) {
            'centenary' => $this->downloadCentenaryFile($run),
            'stanbic' => $this->downloadStanbicFile($run),
            default => $this->downloadGenericFile($run),
        };
    }

    public function downloadGenericFile(PayrollRun $run): StreamedResponse
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

        $rows = $this->payslipRows($run, fn (PayrollPayslip $payslip) => [
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

    public function downloadCentenaryFile(PayrollRun $run): StreamedResponse
    {
        $headers = [
            'Beneficiary Name',
            'Account Number',
            'Amount',
            'Narration',
            'Reference',
        ];

        $rows = $this->payslipRows($run, fn (PayrollPayslip $payslip) => [
            $payslip->full_name,
            $payslip->bank_account ?? '',
            number_format((float) $payslip->net_pay, 2, '.', ''),
            'Salary '.$run->periodLabel(),
            $run->reference.'-'.$payslip->employment_id,
        ]);

        return $this->exports->downloadCsv(
            'centenary-payroll-'.$run->reference.'.csv',
            $headers,
            $rows,
        );
    }

    public function downloadStanbicFile(PayrollRun $run): StreamedResponse
    {
        $headers = [
            'Employee Name',
            'Employee ID',
            'Bank Code',
            'Account Number',
            'Amount',
            'Value Date',
            'Payment Details',
        ];

        $valueDate = $run->period_end->format('d/m/Y');

        $rows = $this->payslipRows($run, fn (PayrollPayslip $payslip) => [
            $payslip->full_name,
            $payslip->employment_id,
            '',
            $payslip->bank_account ?? '',
            number_format((float) $payslip->net_pay, 2, '.', ''),
            $valueDate,
            'Salary '.$run->periodLabel(),
        ]);

        return $this->exports->downloadCsv(
            'stanbic-payroll-'.$run->reference.'.csv',
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
            'Pay type',
            'Normal Shifts',
            'Overtime Shifts',
            'Total Shifts',
            'Gross Pay',
            'Deductions',
            'Net Pay',
            'NSSF',
            'TIN',
            'Bank',
            'Account',
            'Email',
        ];
    }

    /**
     * @return list<list<string|int|float>>
     */
    public function payslipExportRows(PayrollRun $run): array
    {
        return $this->payslipRows($run, fn (PayrollPayslip $payslip) => [
            $payslip->employment_id,
            $payslip->full_name,
            $payslip->compensation_type?->shortLabel() ?? 'Shift pay',
            $payslip->normal_shifts,
            $payslip->overtime_shifts,
            $payslip->total_shifts,
            number_format((float) $payslip->gross_pay, 2, '.', ''),
            number_format((float) $payslip->total_deductions, 2, '.', ''),
            number_format((float) $payslip->net_pay, 2, '.', ''),
            $payslip->nssf_number ?? '',
            $payslip->tin_number ?? '',
            $payslip->bank_name ?? '',
            $payslip->bank_account ?? '',
            $payslip->payroll_email ?? '',
        ]);
    }

    /**
     * @param  callable(PayrollPayslip): list<string|int|float>  $mapper
     * @return list<list<string|int|float>>
     */
    private function payslipRows(PayrollRun $run, callable $mapper): array
    {
        return $run->payslips()
            ->orderBy('employment_id')
            ->get()
            ->map(fn (PayrollPayslip $payslip) => $mapper($payslip))
            ->all();
    }
}
