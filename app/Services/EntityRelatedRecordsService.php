<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\GuardAssetIssuance;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\Leave;
use App\Models\Payment;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class EntityRelatedRecordsService
{
    /**
     * @return list<array{
     *     title: string,
     *     count: int,
     *     href: string|null,
     *     items: list<array{label: string, meta?: string|null, href: string|null, tone?: string|null}>
     * }>
     */
    public function for(Model $subject): array
    {
        return match (true) {
            $subject instanceof Guard => $this->forGuard($subject),
            $subject instanceof Client => $this->forClient($subject),
            $subject instanceof Site => $this->forSite($subject),
            $subject instanceof Invoice => $this->forInvoice($subject),
            default => [],
        };
    }

    /** @return list<array<string, mixed>> */
    private function forGuard(Guard $guard): array
    {
        $guard->loadMissing(['currentDeployment.site', 'currentSite', 'salaryAdvances']);

        $panels = [];

        if ($guard->currentDeployment) {
            $panels[] = [
                'title' => 'Current deployment',
                'count' => 1,
                'href' => route('deployments.show', $guard->currentDeployment),
                'items' => [[
                    'label' => $guard->currentDeployment->site?->name ?? 'Deployment',
                    'meta' => optional($guard->currentDeployment->start_date)?->format('d M Y'),
                    'href' => route('deployments.show', $guard->currentDeployment),
                    'tone' => 'emerald',
                ]],
            ];
        }

        $recentShifts = Shift::query()
            ->with('site:id,name,code')
            ->where('guard_id', $guard->id)
            ->latest('shift_date')
            ->limit(5)
            ->get();

        if ($recentShifts->isNotEmpty()) {
            $panels[] = [
                'title' => 'Recent shifts',
                'count' => $recentShifts->count(),
                'href' => route('shifts.index', ['q' => $guard->employment_id]),
                'items' => $recentShifts->map(fn (Shift $shift) => [
                    'label' => ($shift->site?->code ?? 'Shift').' · '.$shift->shift_date->format('d M Y'),
                    'meta' => $shift->status->label(),
                    'href' => route('shifts.show', $shift),
                    'tone' => $shift->status->tone(),
                ])->all(),
            ];
        }

        $recentLeaves = Leave::query()
            ->where('guard_id', $guard->id)
            ->latest('start_date')
            ->limit(4)
            ->get();

        if ($recentLeaves->isNotEmpty()) {
            $panels[] = [
                'title' => 'Leave records',
                'count' => $recentLeaves->count(),
                'href' => route('leaves.index', ['q' => $guard->employment_id]),
                'items' => $recentLeaves->map(fn (Leave $leave) => [
                    'label' => $leave->leave_type->label(),
                    'meta' => $leave->start_date->format('d M').' – '.$leave->end_date->format('d M Y'),
                    'href' => route('leaves.show', $leave),
                    'tone' => $leave->status->tone(),
                ])->all(),
            ];
        }

        if ($guard->salaryAdvances->isNotEmpty() && Gate::allows('viewFinance')) {
            $panels[] = [
                'title' => 'Salary advances',
                'count' => $guard->salaryAdvances->count(),
                'href' => route('guards.show', $guard).'#advances',
                'items' => $guard->salaryAdvances->take(4)->map(fn ($advance) => [
                    'label' => $advance->label,
                    'meta' => 'Balance '.\App\Support\Money::format($advance->balance_remaining),
                    'href' => route('guards.show', $guard).'#advances',
                    'tone' => $advance->is_active ? 'amber' : 'slate',
                ])->all(),
            ];
        }

        $recentAssets = GuardAssetIssuance::query()
            ->with('lines')
            ->where('guard_id', $guard->id)
            ->latest('issued_at')
            ->limit(3)
            ->get();

        if ($recentAssets->isNotEmpty()) {
            $panels[] = [
                'title' => 'Assets & uniforms',
                'count' => $recentAssets->sum(fn (GuardAssetIssuance $issuance) => $issuance->lines->count()),
                'href' => route('assets.index', ['q' => $guard->employment_id]),
                'items' => $recentAssets->flatMap(fn (GuardAssetIssuance $issuance) => $issuance->lines->take(2)->map(fn ($line) => [
                    'label' => $line->displayLabel(),
                    'meta' => $issuance->reference.' · '.$line->status->label(),
                    'href' => route('assets.show', $issuance),
                    'tone' => $line->status->tone(),
                ]))->take(4)->all(),
            ];
        }

        return $panels;
    }

    /** @return list<array<string, mixed>> */
    private function forClient(Client $client): array
    {
        $sites = $client->sites()->with('region:id,name')->latest()->limit(6)->get();
        $panels = [];

        if ($sites->isNotEmpty()) {
            $panels[] = [
                'title' => 'Sites',
                'count' => $sites->count(),
                'href' => route('sites.index', ['client_id' => $client->id]),
                'items' => $sites->map(fn (Site $site) => [
                    'label' => $site->name,
                    'meta' => $site->code.($site->region ? ' · '.$site->region->name : ''),
                    'href' => route('sites.show', $site),
                    'tone' => $site->status->tone(),
                ])->all(),
            ];
        }

        if (Gate::allows('viewFinance')) {
            $invoices = Invoice::query()
                ->where('client_id', $client->id)
                ->latest('id')
                ->limit(5)
                ->get();

            if ($invoices->isNotEmpty()) {
                $panels[] = [
                    'title' => 'Invoices',
                    'count' => $invoices->count(),
                    'href' => route('invoices.index', ['client_id' => $client->id]),
                    'items' => $invoices->map(fn (Invoice $invoice) => [
                        'label' => $invoice->reference,
                        'meta' => $invoice->status->label().' · '.\App\Support\Money::format($invoice->balance, $invoice->currency).' due',
                        'href' => route('invoices.show', $invoice),
                        'tone' => $invoice->status->tone(),
                    ])->all(),
                ];
            }
        }

        return $panels;
    }

    /** @return list<array<string, mixed>> */
    private function forSite(Site $site): array
    {
        $panels = [];

        $deployments = Deployment::query()
            ->with('assignedGuard:id,employment_id,full_name')
            ->where('site_id', $site->id)
            ->where('is_current', true)
            ->limit(6)
            ->get();

        if ($deployments->isNotEmpty()) {
            $panels[] = [
                'title' => 'Active deployments',
                'count' => $deployments->count(),
                'href' => route('deployments.index', ['site_id' => $site->id, 'current_only' => 1]),
                'items' => $deployments->map(fn (Deployment $deployment) => [
                    'label' => $deployment->assignedGuard?->full_name ?? 'Guard',
                    'meta' => $deployment->assignedGuard?->employment_id,
                    'href' => route('deployments.show', $deployment),
                    'tone' => 'emerald',
                ])->all(),
            ];
        }

        $recentShifts = Shift::query()
            ->with('assignedGuard:id,full_name,employment_id')
            ->where('site_id', $site->id)
            ->latest('shift_date')
            ->limit(5)
            ->get();

        if ($recentShifts->isNotEmpty()) {
            $panels[] = [
                'title' => 'Recent shifts',
                'count' => $recentShifts->count(),
                'href' => route('shifts.index', ['site_id' => $site->id]),
                'items' => $recentShifts->map(fn (Shift $shift) => [
                    'label' => $shift->assignedGuard?->full_name ?? 'Unassigned',
                    'meta' => $shift->shift_date->format('d M Y').' · '.$shift->status->label(),
                    'href' => route('shifts.show', $shift),
                    'tone' => $shift->status->tone(),
                ])->all(),
            ];
        }

        $incidents = Incident::query()
            ->where('site_id', $site->id)
            ->latest('occurred_at')
            ->limit(4)
            ->get();

        if ($incidents->isNotEmpty()) {
            $panels[] = [
                'title' => 'Occurrence book',
                'count' => $incidents->count(),
                'href' => route('incidents.index', ['site_id' => $site->id]),
                'items' => $incidents->map(fn (Incident $incident) => [
                    'label' => $incident->reference,
                    'meta' => $incident->incident_type->label().' · '.$incident->occurred_at->format('d M Y'),
                    'href' => route('incidents.show', $incident),
                    'tone' => $incident->severity->tone(),
                ])->all(),
            ];
        }

        if ($site->client) {
            $panels[] = [
                'title' => 'Client',
                'count' => 1,
                'href' => route('clients.show', $site->client),
                'items' => [[
                    'label' => $site->client->name,
                    'meta' => $site->client->contract_status->label(),
                    'href' => route('clients.show', $site->client),
                    'tone' => $site->client->contract_status->tone(),
                ]],
            ];
        }

        return $panels;
    }

    /** @return list<array<string, mixed>> */
    private function forInvoice(Invoice $invoice): array
    {
        $panels = [];

        if ($invoice->client) {
            $panels[] = [
                'title' => 'Client',
                'count' => 1,
                'href' => route('clients.show', $invoice->client),
                'items' => [[
                    'label' => $invoice->client->name,
                    'meta' => $invoice->client->contract_status->label(),
                    'href' => route('clients.show', $invoice->client),
                    'tone' => $invoice->client->contract_status->tone(),
                ]],
            ];
        }

        if ($invoice->site) {
            $panels[] = [
                'title' => 'Site',
                'count' => 1,
                'href' => route('sites.show', $invoice->site),
                'items' => [[
                    'label' => $invoice->site->name,
                    'meta' => $invoice->site->code,
                    'href' => route('sites.show', $invoice->site),
                    'tone' => $invoice->site->status->tone(),
                ]],
            ];
        }

        $payments = $invoice->payments()->latest('payment_date')->limit(5)->get();
        if ($payments->isNotEmpty()) {
            $panels[] = [
                'title' => 'Payments',
                'count' => $payments->count(),
                'href' => route('payments.index', ['invoice_id' => $invoice->id]),
                'items' => $payments->map(fn (Payment $payment) => [
                    'label' => $payment->reference,
                    'meta' => $payment->payment_date->format('d M Y').' · '.\App\Support\Money::format($payment->amount, $invoice->currency),
                    'href' => route('payments.show', $payment),
                    'tone' => 'emerald',
                ])->all(),
            ];
        }

        return $panels;
    }
}
