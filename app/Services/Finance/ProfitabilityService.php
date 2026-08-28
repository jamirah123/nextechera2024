<?php

namespace App\Services\Finance;

use App\Enums\GuardClassification;
use App\Enums\InvoiceStatus;
use App\Enums\ShiftStatus;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;

class ProfitabilityService
{
    /**
     * @return array{
     *     totals: array<string, float|int>,
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

            $cost = $this->estimatedPayrollCost($client->id, null, null, $from, $to);
            if ($revenue <= 0 && $cost <= 0) {
                continue;
            }

            $byClient[] = [
                'label' => $client->name,
                'code' => '',
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => round($revenue - $cost, 2),
                'margin' => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : null,
            ];
        }

        $bySite = [];
        $sites = Site::query()->with('client:id,name')->orderBy('name')->get(['id', 'name', 'code', 'client_id', 'region_id']);

        foreach ($sites as $site) {
            $revenue = (float) Invoice::query()
                ->where(function ($q) use ($site): void {
                    $q->where('site_id', $site->id)
                        ->orWhere(function ($inner) use ($site): void {
                            $inner->whereNull('site_id')->where('client_id', $site->client_id);
                        });
                })
                ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
                ->whereBetween('issue_date', [$from, $to])
                ->sum('total');

            // Prefer site-specific invoice revenue when present
            $siteRevenue = (float) Invoice::query()
                ->where('site_id', $site->id)
                ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
                ->whereBetween('issue_date', [$from, $to])
                ->sum('total');

            $revenue = $siteRevenue;
            $cost = $this->estimatedPayrollCost($site->client_id, $site->id, $site->region_id, $from, $to);

            if ($revenue <= 0 && $cost <= 0) {
                continue;
            }

            $bySite[] = [
                'label' => $site->name,
                'code' => $site->code,
                'client' => $site->client?->name,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => round($revenue - $cost, 2),
                'margin' => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : null,
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

            $cost = $this->estimatedPayrollCost(null, null, $region->id, $from, $to);
            if ($revenue <= 0 && $cost <= 0) {
                continue;
            }

            $byRegion[] = [
                'label' => $region->name,
                'code' => $region->code,
                'revenue' => $revenue,
                'cost' => $cost,
                'profit' => round($revenue - $cost, 2),
                'margin' => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 1) : null,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'totals' => [
                'invoiced' => $invoiced,
                'collected' => $collected,
                'outstanding' => $outstanding,
                'overdue' => $overdue,
                'estimated_payroll' => array_sum(array_column($byClient, 'cost')),
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
            'collected_total' => (float) Payment::query()->sum('amount'),
            'outstanding' => (float) Invoice::query()->open()->sum('balance'),
            'overdue_count' => Invoice::query()->where('status', InvoiceStatus::Overdue->value)->count(),
            'overdue_amount' => (float) Invoice::query()->where('status', InvoiceStatus::Overdue->value)->sum('balance'),
            'month_invoiced' => (float) Invoice::query()
                ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
                ->whereBetween('issue_date', [$monthStart, $monthEnd])
                ->sum('total'),
            'month_collected' => (float) Payment::query()
                ->whereBetween('payment_date', [$monthStart, $monthEnd])
                ->sum('amount'),
        ];
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
