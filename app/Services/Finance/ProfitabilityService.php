<?php

namespace App\Services\Finance;

use App\Enums\CompensationType;
use App\Enums\GuardClassification;
use App\Enums\InvoiceStatus;
use App\Enums\PayrollRunStatus;
use App\Enums\ShiftStatus;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayrollPayslip;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProfitabilityService
{
    /**
     * @return array{
     *     totals: array<string, float|int|string>,
     *     by_client: list<array<string, mixed>>,
     *     by_site: list<array<string, mixed>>,
     *     by_region: list<array<string, mixed>>
     * }
     */
    public function analyze(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: now()->startOfMonth()->toDateString();
        $to = $to ?: now()->endOfMonth()->toDateString();

        $invoiced = (float) Invoice::query()
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->whereBetween('issue_date', [$from, $to])
            ->sum('total');

        $collected = (float) Payment::query()
            ->collections()
            ->whereBetween('payment_date', [$from, $to])
            ->sum('amount');

        $outstanding = (float) Invoice::query()
            ->open()
            ->sum('balance');

        $overdue = (float) Invoice::query()
            ->where('status', InvoiceStatus::Overdue->value)
            ->sum('balance');

        $clients = Client::query()->orderBy('name')->get(['id', 'name']);
        $sites = Site::query()->with('client:id,name')->orderBy('name')->get(['id', 'name', 'code', 'client_id', 'region_id']);
        $regions = Region::query()->orderBy('name')->get(['id', 'name', 'code']);
        $issued = Invoice::query()->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value]);
        $revenueByClient = (clone $issued)
            ->whereBetween('issue_date', [$from, $to])
            ->selectRaw('client_id, SUM(total) as revenue')
            ->groupBy('client_id')
            ->pluck('revenue', 'client_id');
        $revenueBySite = (clone $issued)
            ->whereBetween('issue_date', [$from, $to])
            ->whereNotNull('site_id')
            ->selectRaw('site_id, SUM(total) as revenue')
            ->groupBy('site_id')
            ->pluck('revenue', 'site_id');
        $costs = $this->groupedPayrollCosts($from, $to, $sites, $clients->pluck('id'));

        $byClient = [];
        foreach ($clients as $client) {
            $revenue = round((float) ($revenueByClient[$client->id] ?? 0), 2);
            $costRow = $costs['clients'][$client->id] ?? ['cost' => 0.0, 'source' => 'estimated'];
            if ($revenue <= 0 && $costRow['cost'] <= 0) {
                continue;
            }

            $byClient[] = [
                'label' => $client->name,
                'code' => '',
                'revenue' => $revenue,
                'cost' => $costRow['cost'],
                'cost_source' => $costRow['source'],
                'profit' => round($revenue - $costRow['cost'], 2),
                'margin' => $revenue > 0 ? round((($revenue - $costRow['cost']) / $revenue) * 100, 1) : null,
            ];
        }

        $bySite = [];
        foreach ($sites as $site) {
            $siteRevenue = round((float) ($revenueBySite[$site->id] ?? 0), 2);
            $costRow = $costs['sites'][$site->id] ?? ['cost' => 0.0, 'source' => 'estimated'];

            if ($siteRevenue <= 0 && $costRow['cost'] <= 0) {
                continue;
            }

            $bySite[] = [
                'label' => $site->name,
                'code' => $site->code,
                'client' => $site->client?->name,
                'revenue' => $siteRevenue,
                'cost' => $costRow['cost'],
                'cost_source' => $costRow['source'],
                'profit' => round($siteRevenue - $costRow['cost'], 2),
                'margin' => $siteRevenue > 0 ? round((($siteRevenue - $costRow['cost']) / $siteRevenue) * 100, 1) : null,
            ];
        }

        $byRegion = [];
        foreach ($regions as $region) {
            $regionSiteIds = $sites->where('region_id', $region->id)->pluck('id');
            $revenue = round((float) $regionSiteIds->sum(fn ($id) => (float) ($revenueBySite[$id] ?? 0)), 2);
            $costRow = $costs['regions'][$region->id] ?? ['cost' => 0.0, 'source' => 'estimated'];
            if ($revenue <= 0 && $costRow['cost'] <= 0) {
                continue;
            }

            $byRegion[] = [
                'label' => $region->name,
                'code' => $region->code,
                'revenue' => $revenue,
                'cost' => $costRow['cost'],
                'cost_source' => $costRow['source'],
                'profit' => round($revenue - $costRow['cost'], 2),
                'margin' => $revenue > 0 ? round((($revenue - $costRow['cost']) / $revenue) * 100, 1) : null,
            ];
        }

        $payrollTotals = $this->aggregatePayrollCosts($from, $to, $byClient);

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'invoiced' => $invoiced,
                'collected' => $collected,
                'outstanding' => $outstanding,
                'overdue' => $overdue,
                'payroll_cost' => $payrollTotals['cost'],
                'payroll_cost_source' => $payrollTotals['source'],
            ],
            'by_client' => $byClient,
            'by_site' => $bySite,
            'by_region' => $byRegion,
        ];
    }

    /**
     * Payroll cost for every client, site, and region in a few grouped queries.
     *
     * @param  Collection<int, Site>  $sites
     * @param  Collection<int, int>  $clientIds
     * @return array{
     *     sites: array<int, array{cost: float, source: string}>,
     *     clients: array<int, array{cost: float, source: string}>,
     *     regions: array<int, array{cost: float, source: string}>
     * }
     */
    private function groupedPayrollCosts(string $from, string $to, Collection $sites, Collection $clientIds): array
    {
        $siteRuns = $this->paidRunsInPeriod($from, $to)
            ->whereNotNull('site_id')
            ->selectRaw('site_id, SUM(gross_total) as total')
            ->groupBy('site_id')
            ->pluck('total', 'site_id');

        $allocatedBySite = [];
        $allocatedByClient = [];
        $allocatedByRegion = [];
        $links = DB::table('payroll_payslips as payslips')
            ->join('payroll_runs as runs', 'runs.id', '=', 'payslips.payroll_run_id')
            ->join('payroll_payslip_shifts as links', 'links.payroll_payslip_id', '=', 'payslips.id')
            ->join('shifts', 'shifts.id', '=', 'links.shift_id')
            ->join('sites', 'sites.id', '=', 'shifts.site_id')
            ->where('runs.status', PayrollRunStatus::Paid->value)
            ->where(function ($period) use ($from, $to): void {
                $period->whereBetween('runs.period_start', [$from, $to])
                    ->orWhereBetween('runs.period_end', [$from, $to])
                    ->orWhere(function ($enclosing) use ($from, $to): void {
                        $enclosing->where('runs.period_start', '<=', $from)
                            ->where('runs.period_end', '>=', $to);
                    });
            })
            ->select('payslips.id as payslip_id', 'payslips.gross_pay', 'sites.id as site_id', 'sites.client_id', 'sites.region_id')
            ->distinct()
            ->get();

        $seenSite = [];
        $seenClient = [];
        $seenRegion = [];
        foreach ($links as $link) {
            $gross = (float) $link->gross_pay;
            $siteKey = $link->payslip_id.'-'.$link->site_id;
            if (! isset($seenSite[$siteKey])) {
                $seenSite[$siteKey] = true;
                $allocatedBySite[(int) $link->site_id] = ($allocatedBySite[(int) $link->site_id] ?? 0) + $gross;
            }
            $clientKey = $link->payslip_id.'-'.$link->client_id;
            if (! isset($seenClient[$clientKey])) {
                $seenClient[$clientKey] = true;
                $allocatedByClient[(int) $link->client_id] = ($allocatedByClient[(int) $link->client_id] ?? 0) + $gross;
            }
            $regionKey = $link->payslip_id.'-'.$link->region_id;
            if (! isset($seenRegion[$regionKey])) {
                $seenRegion[$regionKey] = true;
                $allocatedByRegion[(int) $link->region_id] = ($allocatedByRegion[(int) $link->region_id] ?? 0) + $gross;
            }
        }

        $profiles = BillingProfile::query()
            ->active()
            ->where('effective_from', '<=', $to)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $from))
            ->get();

        $clientWideCost = [];
        $siteSpecificCost = [];
        $clientProfileCost = [];
        $unscopedProfileCost = 0.0;
        foreach ($profiles as $profile) {
            $cost = $this->profileContractCost($profile);
            $clientId = (int) $profile->client_id;
            $clientProfileCost[$clientId] = ($clientProfileCost[$clientId] ?? 0) + $cost;
            if ($profile->site_id === null) {
                $clientWideCost[$clientId] = ($clientWideCost[$clientId] ?? 0) + $cost;
                $unscopedProfileCost += $cost;
            } else {
                $siteId = (int) $profile->site_id;
                $siteSpecificCost[$siteId] = ($siteSpecificCost[$siteId] ?? 0) + $cost;
            }
        }

        $shared = $this->companyStaffAndSalaryCost($from, $to);
        $salaryBySite = PayrollPayslip::query()
            ->whereNotNull('payroll_payslips.guard_id')
            ->whereNull('payroll_payslips.staff_id')
            ->where('payroll_payslips.compensation_type', CompensationType::Salary->value)
            ->whereHas('run', fn (Builder $query) => $this->applyPeriodOverlap(
                $query->where('status', PayrollRunStatus::Paid->value),
                $from,
                $to,
            ))
            ->join('guards', 'guards.id', '=', 'payroll_payslips.guard_id')
            ->whereNull('guards.deleted_at')
            ->whereNotNull('guards.current_site_id')
            ->selectRaw('guards.current_site_id as site_id, SUM(payroll_payslips.gross_pay) as total')
            ->groupBy('guards.current_site_id')
            ->pluck('total', 'site_id');
        $siteCosts = [];
        foreach ($sites as $site) {
            $runTotal = round((float) ($siteRuns[$site->id] ?? 0), 2);
            if ($runTotal > 0) {
                $siteCosts[(int) $site->id] = ['cost' => $runTotal, 'source' => 'actual'];

                continue;
            }

            $allocated = round((float) ($allocatedBySite[$site->id] ?? 0) + (float) ($salaryBySite[$site->id] ?? 0), 2);
            if ($allocated > 0) {
                $siteCosts[(int) $site->id] = ['cost' => $allocated, 'source' => 'actual'];

                continue;
            }

            $estimated = ($clientWideCost[(int) $site->client_id] ?? 0) + ($siteSpecificCost[(int) $site->id] ?? 0);
            $siteCosts[(int) $site->id] = ['cost' => round($estimated, 2), 'source' => 'estimated'];
        }

        $clientCosts = [];
        foreach ($clientIds->unique() as $clientId) {
            $clientId = (int) $clientId;
            $shiftCost = round((float) ($allocatedByClient[$clientId] ?? 0), 2);
            $actual = round($shiftCost + $shared, 2);
            if ($actual > 0) {
                $clientCosts[$clientId] = ['cost' => $actual, 'source' => 'actual'];

                continue;
            }

            $clientCosts[$clientId] = ['cost' => round((float) ($clientProfileCost[$clientId] ?? 0), 2), 'source' => 'estimated'];
        }

        $regionCosts = [];
        foreach ($sites->pluck('region_id')->unique()->filter() as $regionId) {
            $regionId = (int) $regionId;
            $regionSiteIds = $sites->where('region_id', $regionId)->pluck('id');
            $regionRun = (float) $this->paidRunsInPeriod($from, $to)
                ->where(function (Builder $query) use ($regionId, $regionSiteIds): void {
                    $query->where(function (Builder $scoped) use ($regionId): void {
                        $scoped->where('region_id', $regionId)->whereNull('site_id');
                    })->orWhereIn('site_id', $regionSiteIds);
                })
                ->sum('gross_total');
            if ($regionRun > 0) {
                $regionCosts[$regionId] = ['cost' => round($regionRun, 2), 'source' => 'actual'];

                continue;
            }

            $allocated = round($this->allocatedPayslipGross($from, $to, null, null, $regionId), 2);
            if ($allocated > 0) {
                $regionCosts[$regionId] = ['cost' => $allocated, 'source' => 'actual'];

                continue;
            }

            $estimated = $unscopedProfileCost;
            foreach ($regionSiteIds as $regionSiteId) {
                $estimated += ($siteSpecificCost[(int) $regionSiteId] ?? 0);
            }
            $regionCosts[$regionId] = ['cost' => round($estimated, 2), 'source' => 'estimated'];
        }

        return [
            'sites' => $siteCosts,
            'clients' => $clientCosts,
            'regions' => $regionCosts,
        ];
    }

    private function companyStaffAndSalaryCost(string $from, string $to): float
    {
        $staffBased = (float) PayrollPayslip::query()
            ->whereNotNull('staff_id')
            ->whereHas('run', fn (Builder $query) => $this->applyPeriodOverlap(
                $query->where('status', PayrollRunStatus::Paid->value),
                $from,
                $to,
            ))
            ->whereHas('assignedStaff')
            ->sum('gross_pay');

        $salaryGuards = (float) PayrollPayslip::query()
            ->whereNotNull('guard_id')
            ->whereNull('staff_id')
            ->where('compensation_type', CompensationType::Salary->value)
            ->whereHas('run', fn (Builder $query) => $this->applyPeriodOverlap(
                $query->where('status', PayrollRunStatus::Paid->value),
                $from,
                $to,
            ))
            ->sum('gross_pay');

        return round($staffBased + $salaryGuards, 2);
    }

    private function profileContractCost(BillingProfile $profile): float
    {
        if ($profile->contractedGuardTotal() <= 0) {
            return 0.0;
        }

        return ((int) $profile->contracted_armed_guards * (float) $profile->monthly_cost_per_armed_guard)
            + ((int) $profile->contracted_unarmed_guards * (float) $profile->monthly_cost_per_unarmed_guard);
    }

    /**
     * @return array<string, float|int>
     */
    public function dashboardTotals(): array
    {
        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        return [
            'invoiced_total' => (float) Invoice::query()
                ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
                ->sum('total'),
            'collected_total' => (float) Payment::query()->collections()->sum('amount'),
            'outstanding' => (float) Invoice::query()->open()->sum('balance'),
            'overdue_count' => Invoice::query()->where('status', InvoiceStatus::Overdue->value)->count(),
            'overdue_amount' => (float) Invoice::query()->where('status', InvoiceStatus::Overdue->value)->sum('balance'),
            'month_invoiced' => (float) Invoice::query()
                ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
                ->whereBetween('issue_date', [$monthStart, $monthEnd])
                ->sum('total'),
            'month_collected' => (float) Payment::query()
                ->collections()
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('amount'),
        ];
    }

    /**
     * @return array{cost: float, source: 'actual'|'estimated'|'mixed'}
     */
    private function payrollCost(?int $clientId, ?int $siteId, ?int $regionId, string $from, string $to): array
    {
        $actual = $this->actualPayrollCost($clientId, $siteId, $regionId, $from, $to);
        if ($actual > 0) {
            return ['cost' => $actual, 'source' => 'actual'];
        }

        $estimated = $this->estimatedPayrollCost($clientId, $siteId, $regionId, $from, $to);

        return ['cost' => $estimated, 'source' => 'estimated'];
    }

    private function actualPayrollCost(?int $clientId, ?int $siteId, ?int $regionId, string $from, string $to): float
    {
        if ($siteId) {
            $siteRunTotal = (float) $this->paidRunsInPeriod($from, $to)
                ->where('site_id', $siteId)
                ->sum('gross_total');

            if ($siteRunTotal > 0) {
                return round($siteRunTotal, 2);
            }
        }

        if ($regionId && ! $siteId) {
            $siteIds = Site::query()->where('region_id', $regionId)->pluck('id');

            $regionTotal = (float) $this->paidRunsInPeriod($from, $to)
                ->where(function (Builder $query) use ($regionId, $siteIds): void {
                    $query->where(function (Builder $scoped) use ($regionId): void {
                        $scoped->where('region_id', $regionId)->whereNull('site_id');
                    })->orWhereIn('site_id', $siteIds);
                })
                ->sum('gross_total');

            if ($regionTotal > 0) {
                return round($regionTotal, 2);
            }
        }

        $allocated = $this->allocatedPayslipGross($from, $to, $clientId, $siteId, $regionId);

        return $allocated > 0 ? round($allocated, 2) : 0.0;
    }

    private function allocatedPayslipGross(string $from, string $to, ?int $clientId, ?int $siteId, ?int $regionId): float
    {
        $shiftBased = (float) PayrollPayslip::query()
            ->whereHas('run', fn (Builder $query) => $this->applyPeriodOverlap(
                $query->where('status', PayrollRunStatus::Paid->value),
                $from,
                $to,
            ))
            ->whereHas('shifts.site', function (Builder $query) use ($clientId, $siteId, $regionId): void {
                if ($clientId) {
                    $query->where('client_id', $clientId);
                }
                if ($siteId) {
                    $query->where('sites.id', $siteId);
                }
                if ($regionId) {
                    $query->where('region_id', $regionId);
                }
            })
            ->sum('gross_pay');

        $staffBased = (float) PayrollPayslip::query()
            ->whereNotNull('staff_id')
            ->whereHas('run', fn (Builder $query) => $this->applyPeriodOverlap(
                $query->where('status', PayrollRunStatus::Paid->value),
                $from,
                $to,
            ))
            ->where(function (Builder $query) use ($siteId, $regionId): void {
                if ($siteId) {
                    $query->whereRaw('0 = 1');

                    return;
                }

                $query->whereHas('assignedStaff', function (Builder $staff) use ($regionId): void {
                    if ($regionId) {
                        $staff->where('region_id', $regionId);
                    }
                });

                if ($regionId) {
                    $query->orWhereHas('run', fn (Builder $run) => $run->where('region_id', $regionId));
                }
            })
            ->sum('gross_pay');

        $salaryGuards = (float) PayrollPayslip::query()
            ->whereNotNull('guard_id')
            ->whereNull('staff_id')
            ->where('compensation_type', CompensationType::Salary->value)
            ->whereHas('run', fn (Builder $query) => $this->applyPeriodOverlap(
                $query->where('status', PayrollRunStatus::Paid->value),
                $from,
                $to,
            ))
            ->where(function (Builder $query) use ($siteId, $regionId): void {
                if ($siteId) {
                    $query->whereHas('assignedGuard', fn (Builder $guard) => $guard->where('current_site_id', $siteId));
                }
                if ($regionId) {
                    $query->whereHas('assignedGuard', fn (Builder $guard) => $guard->where('region_id', $regionId));
                }
            })
            ->sum('gross_pay');

        return $shiftBased + $staffBased + $salaryGuards;
    }

    private function paidRunsInPeriod(string $from, string $to): Builder
    {
        return PayrollRun::query()->where(function (Builder $query) use ($from, $to): void {
            $this->applyPeriodOverlap($query, $from, $to);
        });
    }

    private function applyPeriodOverlap(Builder $query, string $from, string $to): Builder
    {
        return $query->where(function (Builder $period) use ($from, $to): void {
            $period->whereBetween('period_start', [$from, $to])
                ->orWhereBetween('period_end', [$from, $to])
                ->orWhere(function (Builder $enclosing) use ($from, $to): void {
                    $enclosing->where('period_start', '<=', $from)
                        ->where('period_end', '>=', $to);
                });
        });
    }

    /**
     * @param  list<array<string, mixed>>  $byClient
     * @return array{cost: float, source: 'actual'|'estimated'|'mixed'}
     */
    private function aggregatePayrollCosts(string $from, string $to, array $byClient): array
    {
        $paidTotal = (float) $this->paidRunsInPeriod($from, $to)->sum('gross_total');
        if ($paidTotal > 0) {
            return ['cost' => round($paidTotal, 2), 'source' => 'actual'];
        }

        $estimatedTotal = round(array_sum(array_column($byClient, 'cost')), 2);

        return ['cost' => $estimatedTotal, 'source' => 'estimated'];
    }

    private function estimatedPayrollCost(?int $clientId, ?int $siteId, ?int $regionId, string $from, string $to): float
    {
        $headcount = $this->estimatedHeadcountCost($clientId, $siteId, $regionId, $from, $to);
        if ($headcount > 0) {
            return $headcount;
        }

        $query = Shift::query()
            ->where('status', ShiftStatus::Completed->value)
            ->whereBetween('shift_date', [$from, $to])
            ->whereHas('site', function ($q) use ($clientId, $siteId, $regionId): void {
                if ($clientId) {
                    $q->where('client_id', $clientId);
                }
                if ($siteId) {
                    $q->where('id', $siteId);
                }
                if ($regionId) {
                    $q->where('region_id', $regionId);
                }
            });

        $shifts = $query->with('site:id,client_id')->get(['id', 'site_id', 'shift_date', 'guard_classification']);
        if ($shifts->isEmpty()) {
            return 0.0;
        }

        $cost = 0.0;
        foreach ($shifts as $shift) {
            $profile = BillingProfile::query()
                ->active()
                ->where('client_id', $shift->site?->client_id)
                ->where(function ($q) use ($shift): void {
                    $q->where('site_id', $shift->site_id)->orWhereNull('site_id');
                })
                ->whereDate('effective_from', '<=', $shift->shift_date)
                ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $shift->shift_date))
                ->orderByRaw('site_id is null')
                ->first();

            $classification = $shift->guard_classification instanceof GuardClassification
                ? $shift->guard_classification
                : (GuardClassification::tryFrom((string) $shift->guard_classification) ?? GuardClassification::Unarmed);
            $unit = $profile?->costRateFor($classification) ?? 0.0;
            $cost += $unit;
        }

        return round($cost, 2);
    }

    private function estimatedHeadcountCost(?int $clientId, ?int $siteId, ?int $regionId, string $from, string $to): float
    {
        if ($regionId && ! $siteId) {
            $siteId = null;
        }

        $profiles = BillingProfile::query()
            ->active()
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($siteId, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->when($regionId && ! $siteId, function ($q) use ($regionId): void {
                $siteIds = Site::query()->where('region_id', $regionId)->pluck('id');
                $q->where(function ($inner) use ($siteIds): void {
                    $inner->whereNull('site_id')->orWhereIn('site_id', $siteIds);
                });
            })
            ->whereDate('effective_from', '<=', $to)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
            ->get();

        $cost = 0.0;
        foreach ($profiles as $profile) {
            if ($profile->contractedGuardTotal() <= 0) {
                continue;
            }

            $cost += (int) $profile->contracted_armed_guards * (float) $profile->monthly_cost_per_armed_guard;
            $cost += (int) $profile->contracted_unarmed_guards * (float) $profile->monthly_cost_per_unarmed_guard;
        }

        return round($cost, 2);
    }
}
