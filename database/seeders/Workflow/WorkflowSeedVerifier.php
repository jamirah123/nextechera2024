<?php

namespace Database\Seeders\Workflow;

use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Payment;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\User;
use App\Models\Shift;
use App\Models\ShiftReplacement;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkflowSeedVerifier
{
    /** @return array<string, int> */
    public function assertClean(): array
    {
        $errors = [];

        $duplicateIds = Guard::query()
            ->select('employment_id')
            ->groupBy('employment_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicateIds > 0) {
            $errors[] = $duplicateIds.' duplicate guard employment IDs';
        }

        $orphanGuards = Guard::query()->whereNull('region_id')->count();
        if ($orphanGuards > 0) {
            $errors[] = $orphanGuards.' guards have no region';
        }

        $crossRegion = Site::query()
            ->whereNotNull('supervisor_id')
            ->whereHas('supervisor', fn ($query) => $query->whereColumn('supervisors.region_id', '!=', 'sites.region_id'))
            ->count();
        if ($crossRegion > 0) {
            $errors[] = $crossRegion.' sites have a supervisor from another region';
        }

        $duplicateDuties = Shift::query()
            ->select('guard_id', 'shift_date', 'period')
            ->groupBy('guard_id', 'shift_date', 'period')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicateDuties > 0) {
            $errors[] = $duplicateDuties.' duplicate guard duties on the same date and period';
        }

        $payslipsWithoutSalary = PayrollPayslip::query()
            ->whereNotNull('guard_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('guard_salary_revisions')
                    ->whereColumn('guard_salary_revisions.guard_id', 'payroll_payslips.guard_id');
            })
            ->count();
        if ($payslipsWithoutSalary > 0) {
            $errors[] = $payslipsWithoutSalary.' payslips have no salary history';
        }

        Invoice::query()->withSum('payments', 'amount')->each(function (Invoice $invoice) use (&$errors): void {
            $paid = round((float) $invoice->payments_sum_amount, 2);
            $expected = round((float) $invoice->total - $paid, 2);
            if (abs($expected - (float) $invoice->balance) > 0.05) {
                $errors[] = $invoice->reference.' balance does not match total minus payments';
            }
        });

        if ($errors !== []) {
            throw new RuntimeException('Workflow seed failed verification: '.implode('; ', $errors));
        }

        return [
            'Users' => User::query()->count(),
            'Regions' => Region::query()->count(),
            'Supervisors' => Supervisor::query()->count(),
            'Clients' => DB::table('clients')->count(),
            'Sites' => Site::query()->count(),
            'Staff' => Staff::query()->count(),
            'Guards' => Guard::query()->count(),
            'Shifts' => Shift::query()->count(),
            'Deployments' => Deployment::query()->count(),
            'Replacements' => ShiftReplacement::query()->count(),
            'Transfers' => DB::table('deployment_transfers')->count(),
            'Leave records' => Leave::query()->count(),
            'Attendance records' => DB::table('attendances')->count(),
            'Payroll runs' => PayrollRun::query()->count(),
            'Payslips' => PayrollPayslip::query()->count(),
            'Billing profiles' => DB::table('billing_profiles')->count(),
            'Invoices' => Invoice::query()->count(),
            'Payments' => Payment::query()->count(),
            'Audit records' => AuditLog::query()->count(),
        ];
    }
}
