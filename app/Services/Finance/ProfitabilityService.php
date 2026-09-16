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

        $byClient = [];
        $clients = Client::query()->orderBy('name')->get(['id', 'name']);

        foreach ($clients as $client) {
            $revenue = (float) Invoice::query()
                ->where('client_id', $client->id)
                ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
                ->whereBetween('issue_date', [$from, $to])
                ->sum('total');

            $costRow = $this->payrollCost($client->id, null, null, $from, $to);
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
        $sites = Site::query()->with('client:id,name')->orderBy('name')->get(['id', 'name', 'code', 'client_id', 'region_id']);

        foreach ($sites as $site) {
            $siteRevenue = (float) Invoice::query()
                ->where('site_id', $site->id)
                ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
                ->whereBetween('issue_date', [$from, $to])
                ->sum('total');

            $costRow = $this->payrollCost($site->client_id, $site->id, $site->region_id, $from, $to);

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
        foreach (Region::query()->orderBy('name')->get(['id', 'name', 'code']) as $region) {
            $siteIds = Site::query()->where('region_id', $region->id)->pluck('id');
            $revenue = (float) Invoice::query()
                ->whereIn('site_id', $siteIds)
                ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
                ->whereBetween('issue_date', [$from, $to])
                ->sum('total');

            $costRow = $this->payrollCost(null, null, $region->id, $from, $to);
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
