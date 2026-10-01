<?php

namespace Database\Seeders\Workflow;

use App\Enums\CompensationType;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\GuardSalaryRevision;
use App\Models\Site;
use App\Models\Staff;
use App\Services\ManpowerService;
use Illuminate\Support\Facades\DB;

/**
 * Checks the scale dataset for broken relationships and rule violations.
 */
class ScaleDatasetValidator
{
    /** @return list<string> */
    public function errors(): array
    {
        $errors = [];

        $duplicateGuards = Guard::query()
            ->select('employment_id')
            ->groupBy('employment_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicateGuards > 0) {
            $errors[] = $duplicateGuards.' duplicate guard employment IDs';
        }

        $duplicateStaff = Staff::query()
            ->select('employment_id')
            ->groupBy('employment_id')
            ->havingRaw('COUNT(*) > 1')
            ->count();
        if ($duplicateStaff > 0) {
            $errors[] = $duplicateStaff.' duplicate staff employment IDs';
        }

        $shiftsBeforeHire = DB::table('shifts')
            ->join('guards', 'guards.id', '=', 'shifts.guard_id')
            ->whereColumn('shifts.shift_date', '<', 'guards.date_employed')
            ->count();
        if ($shiftsBeforeHire > 0) {
            $errors[] = $shiftsBeforeHire.' shifts fall before the guard employment date';
        }

        $shiftsAfterExit = DB::table('shifts')
            ->join('guards', 'guards.id', '=', 'shifts.guard_id')
            ->whereNotNull('guards.employment_end_date')
            ->whereColumn('shifts.shift_date', '>', 'guards.employment_end_date')
            ->count();
        if ($shiftsAfterExit > 0) {
            $errors[] = $shiftsAfterExit.' shifts fall after the guard employment end date';
        }

        $deploymentsBeforeHire = DB::table('deployments')
            ->join('guards', 'guards.id', '=', 'deployments.guard_id')
            ->whereColumn('deployments.start_date', '<', 'guards.date_employed')
            ->count();
        if ($deploymentsBeforeHire > 0) {
            $errors[] = $deploymentsBeforeHire.' deployments start before the guard employment date';
        }

        $blocking = array_map(
            fn (ShiftStatus $status) => $status->value,
            array_filter(ShiftStatus::cases(), fn (ShiftStatus $status) => $status->blocksCalendarSlot()),
        );
        $conflicts = DB::table('shifts')
            ->select('guard_id')
            ->whereIn('status', $blocking)
            ->groupBy('guard_id', 'shift_date', 'period')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        if ($conflicts > 0) {
            $errors[] = $conflicts.' conflicting shifts share a guard, date, and period';
        }

        $doublePosted = DB::table('deployments')
            ->select('guard_id')
            ->where('is_current', true)
            ->where('is_temporary', false)
            ->where('status', 'active')
            ->where('notes', 'like', 'Scale posting%')
            ->groupBy('guard_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->count();
        if ($doublePosted > 0) {
            $errors[] = $doublePosted.' scale guards have two current permanent deployments';
        }

        $orphanPayslips = DB::table('payroll_payslips')
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_payslips.payroll_run_id')
            ->where('payroll_runs.status', '!=', PayrollRunStatus::Cancelled->value)
            ->where('payroll_payslips.compensation_type', CompensationType::Shift->value)
            ->where('payroll_payslips.total_shifts', 0)
            ->where('payroll_payslips.gross_pay', '>', 0)
            ->count();
        if ($orphanPayslips > 0) {
            $errors[] = $orphanPayslips.' shift payslips have pay without payable shifts';
        }

        $invoicesWithoutProfile = DB::table('invoices')
            ->whereNotNull('invoices.site_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('billing_profiles')
                    ->whereColumn('billing_profiles.site_id', 'invoices.site_id')
                    ->whereColumn('billing_profiles.effective_from', '<=', 'invoices.period_start')
                    ->where(function ($window): void {
                        $window->whereNull('billing_profiles.effective_to')
                            ->orWhereColumn('billing_profiles.effective_to', '>=', 'invoices.period_start');
                    });
            })
            ->count();
        if ($invoicesWithoutProfile > 0) {
            $errors[] = $invoicesWithoutProfile.' invoices have no billing profile covering the period';
        }

        $crossRegionSites = Site::query()
            ->whereNotNull('supervisor_id')
            ->whereHas('supervisor', fn ($query) => $query->whereColumn('supervisors.region_id', '!=', 'sites.region_id'))
            ->count();
        if ($crossRegionSites > 0) {
            $errors[] = $crossRegionSites.' sites have a supervisor from another region';
        }

        $brokenSiteLinks = DB::table('sites')
            ->leftJoin('clients', 'clients.id', '=', 'sites.client_id')
            ->leftJoin('regions', 'regions.id', '=', 'sites.region_id')
            ->where(function ($query): void {
                $query->whereNull('clients.id')->orWhereNull('regions.id');
            })
            ->count();
        if ($brokenSiteLinks > 0) {
            $errors[] = $brokenSiteLinks.' sites have a missing client or region';
        }

        $crossRegionGuards = DB::table('deployments')
            ->join('guards', 'guards.id', '=', 'deployments.guard_id')
            ->where('deployments.notes', 'like', 'Scale posting%')
            ->whereColumn('guards.region_id', '!=', 'deployments.region_id')
            ->count();
        if ($crossRegionGuards > 0) {
            $errors[] = $crossRegionGuards.' scale deployments put a guard in another region';
        }

        $badSalaryWindows = GuardSalaryRevision::query()
            ->whereNotNull('effective_to')
            ->whereColumn('effective_to', '<', 'effective_from')
            ->count();
        if ($badSalaryWindows > 0) {
            $errors[] = $badSalaryWindows.' salary revisions end before they start';
        }

        $orphanNotifications = DB::table('notification_states')
            ->leftJoin('users', 'users.id', '=', 'notification_states.user_id')
            ->whereNull('users.id')
            ->count();
        if ($orphanNotifications > 0) {
            $errors[] = $orphanNotifications.' notifications point at a missing user';
        }

        $showcase = Site::query()->where('code', 'SCL-001')->first();
        if ($showcase === null) {
            $errors[] = 'Showcase site SCL-001 is missing';
        } else {
            $board = app(ManpowerService::class)->postingBoardCoverage(collect([$showcase]), '2026-09-30');
            $day = $board[(string) $showcase->id]['day'] ?? null;
            if (
                $day === null
                || (int) $day['required'] !== 4
                || (int) $day['normal'] !== 3
                || (int) $day['ot'] !== 1
                || (int) $day['operational'] !== 4
                || (int) $day['deficit'] !== 1
            ) {
                $errors[] = 'SCL-001 day manpower is not Required 4, Normal 3, OT 1, Coverage 4, Deficit 1';
            }
        }

        $guardCount = Guard::query()->count();
        if ($guardCount < 1000) {
            $errors[] = 'Guard count is '.$guardCount.', expected at least 1000';
        }

        return $errors;
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return [
            'Guards' => Guard::query()->count(),
            'Staff' => Staff::query()->count(),
            'Supervisors' => DB::table('supervisors')->count(),
            'Clients' => DB::table('clients')->count(),
            'Sites' => Site::query()->count(),
            'Scale sites' => Site::query()->where('code', 'like', 'SCL-%')->count(),
            'Deployments' => Deployment::query()->count(),
            'Shifts' => DB::table('shifts')->count(),
            'Leave' => DB::table('leaves')->count(),
            'Payroll runs' => DB::table('payroll_runs')->where('status', '!=', PayrollRunStatus::Cancelled->value)->count(),
            'Payslips' => DB::table('payroll_payslips')->count(),
            'Billing profiles' => DB::table('billing_profiles')->count(),
            'Invoices' => DB::table('invoices')->count(),
            'Payments' => DB::table('payments')->count(),
            'Notifications' => DB::table('notification_states')->count(),
            'Audit records' => DB::table('audit_logs')->count(),
        ];
    }
}
