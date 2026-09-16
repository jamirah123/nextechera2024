<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Deployment;
use App\Models\GlAccount;
use App\Models\GlJournal;
use App\Models\Guard;
use App\Models\GuardSalaryAdvance;
use App\Models\Incident;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Staff;
use App\Models\Supervisor;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\Access\Access;
use App\Support\Money;
use Illuminate\Support\Collection;

class GlobalSearchService
{
    /**
     * @return list<array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    public function search(string $query, ?User $user = null, int $limitPerType = 4): array
    {
        $term = trim($query);

        if (mb_strlen($term) < 2) {
            return [];
        }

        $user ??= auth()->user();
        $results = collect();

        if ($this->can($user, 'guards.view') || $this->can($user, 'organization.view')) {
            $results = $results->merge($this->guards($term, $limitPerType));
        }

        if ($this->can($user, 'organization.view') || $this->can($user, 'operations.deployments_manage') || $this->can($user, 'operations.deploy_board')) {
            $results = $results->merge($this->deployments($term, $limitPerType));
        }

        if ($this->can($user, 'organization.view') || $this->can($user, 'operations.shifts_manage')) {
            $results = $results->merge($this->shifts($term, $limitPerType));
        }

        if ($this->can($user, 'organization.view')) {
            $results = $results
                ->merge($this->regions($term, $limitPerType))
                ->merge($this->supervisors($term, $limitPerType))
                ->merge($this->clients($term, $limitPerType))
                ->merge($this->sites($term, $limitPerType));
        }

        if ($this->can($user, 'staff.view')) {
            $results = $results->merge($this->staff($term, $limitPerType));
        }

        if ($this->can($user, 'organization.view') || $this->can($user, 'operations.incidents_manage')) {
            $results = $results->merge($this->incidents($term, $limitPerType));
        }

        if ($this->can($user, 'organization.view') || $this->can($user, 'operations.work_orders_manage')) {
            $results = $results->merge($this->workOrders($term, $limitPerType));
        }

        if ($this->can($user, 'finance.view')) {
            $results = $results
                ->merge($this->invoices($term, $limitPerType))
                ->merge($this->payments($term, $limitPerType))
                ->merge($this->payrollRuns($term, $limitPerType))
                ->merge($this->advances($term, $limitPerType))
                ->merge($this->journals($term, $limitPerType))
                ->merge($this->glAccounts($term, $limitPerType));
        }

        return $results->take(28)->values()->all();
    }

    private function can(?User $user, string $permission): bool
    {
        return $user !== null && Access::userCan($user, $permission);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function shifts(string $term, int $limit): Collection
    {
        return Shift::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->search($term)
            ->latest('starts_at')
            ->limit($limit)
            ->get()
            ->map(fn (Shift $shift) => [
                'type' => 'shift',
                'label' => 'Shift',
                'title' => $shift->assignedGuard?->full_name ?? $shift->reference,
                'subtitle' => $shift->reference.' · '.($shift->site?->name ?? 'No site').' · '.$shift->timeLabel(),
                'url' => route('shifts.show', $shift),
                'badge' => $shift->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function deployments(string $term, int $limit): Collection
    {
        return Deployment::query()
            ->with(['assignedGuard:id,employment_id,full_name', 'site:id,name,code'])
            ->current()
            ->search($term)
            ->latest('start_date')
            ->limit($limit)
            ->get()
            ->map(fn (Deployment $deployment) => [
                'type' => 'deployment',
                'label' => 'Deployment',
                'title' => $deployment->assignedGuard?->full_name ?? 'Deployment',
                'subtitle' => ($deployment->assignedGuard?->employment_id ?? '').' · '.($deployment->site?->name ?? 'No site'),
                'url' => route('deployments.show', $deployment),
                'badge' => $deployment->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function guards(string $term, int $limit): Collection
    {
        return Guard::query()
            ->with('region:id,name')
            ->search($term)
            ->orderBy('full_name')
            ->limit($limit)
            ->get()
            ->map(fn (Guard $guard) => [
                'type' => 'guard',
                'label' => 'Guard',
                'title' => $guard->full_name,
                'subtitle' => $guard->employment_id.' · '.($guard->region?->name ?? 'No region').' · '.$guard->operational_status->label(),
                'url' => route('guards.show', $guard),
                'badge' => $guard->employment_status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function regions(string $term, int $limit): Collection
    {
        return Region::query()
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Region $region) => [
                'type' => 'region',
                'label' => 'Region',
                'title' => $region->name,
                'subtitle' => $region->code.($region->manager_name ? ' · '.$region->manager_name : ''),
                'url' => route('regions.show', $region),
                'badge' => $region->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function supervisors(string $term, int $limit): Collection
    {
        return Supervisor::query()
            ->with('region:id,name')
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Supervisor $supervisor) => [
                'type' => 'supervisor',
                'label' => 'Supervisor',
                'title' => $supervisor->name,
                'subtitle' => $supervisor->supervisor_code.' · '.($supervisor->region?->name ?? 'No region'),
                'url' => route('supervisors.show', $supervisor),
                'badge' => $supervisor->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function clients(string $term, int $limit): Collection
    {
        return Client::query()
            ->search($term)
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Client $client) => [
                'type' => 'client',
                'label' => 'Client',
                'title' => $client->name,
                'subtitle' => $client->contact_person ?: ($client->phone ?: 'Client'),
                'url' => route('clients.show', $client),
                'badge' => $client->contract_status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function sites(string $term, int $limit): Collection
    {
        return Site::query()
            ->with(['client:id,name', 'region:id,name'])
            ->where(function ($q) use ($term): void {
                $like = '%'.$term.'%';
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like)
                    ->orWhere('physical_location', 'like', $like)
                    ->orWhere('site_contact_person', 'like', $like)
                    ->orWhereHas('client', fn ($client) => $client->where('name', 'like', $like)->orWhere('code', 'like', $like));
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Site $site) => [
                'type' => 'site',
                'label' => 'Site',
                'title' => $site->name,
                'subtitle' => $site->code.' · '.($site->client?->name ?? 'No client').' · '.($site->region?->name ?? 'No region'),
                'url' => route('sites.show', $site),
                'badge' => $site->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function staff(string $term, int $limit): Collection
    {
        return Staff::query()
            ->search($term)
            ->orderBy('full_name')
            ->limit($limit)
            ->get()
            ->map(fn (Staff $member) => [
                'type' => 'staff',
                'label' => 'Staff',
                'title' => $member->full_name,
                'subtitle' => $member->employment_id.($member->department ? ' · '.$member->department : ''),
                'url' => route('staff.show', $member),
                'badge' => $member->employment_status?->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function incidents(string $term, int $limit): Collection
    {
        return Incident::query()
            ->with(['site:id,name,code', 'assignedGuard:id,full_name,employment_id'])
            ->search($term)
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (Incident $incident) => [
                'type' => 'incident',
                'label' => 'Incident',
                'title' => $incident->title ?: $incident->reference,
                'subtitle' => $incident->reference.' · '.($incident->site?->name ?? 'No site'),
                'url' => route('incidents.show', $incident),
                'badge' => $incident->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function workOrders(string $term, int $limit): Collection
    {
        return WorkOrder::query()
            ->search($term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (WorkOrder $order) => [
                'type' => 'work_order',
                'label' => 'Work order',
                'title' => $order->title ?: $order->reference,
                'subtitle' => $order->reference.($order->due_at ? ' · Due '.$order->due_at->format('d M Y') : ''),
                'url' => route('work-orders.show', $order),
                'badge' => $order->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function invoices(string $term, int $limit): Collection
    {
        return Invoice::query()
            ->with(['client:id,name'])
            ->search($term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $invoice) => [
                'type' => 'invoice',
                'label' => 'Invoice',
                'title' => $invoice->reference,
                'subtitle' => ($invoice->client?->name ?? 'No client').' · '.$invoice->period_start->format('d M Y').' – '.$invoice->period_end->format('d M Y'),
                'url' => route('invoices.show', $invoice),
                'badge' => $invoice->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function payments(string $term, int $limit): Collection
    {
        return Payment::query()
            ->with(['invoice:id,reference', 'client:id,name'])
            ->search($term)
            ->latest('payment_date')
            ->limit($limit)
            ->get()
            ->map(fn (Payment $payment) => [
                'type' => 'payment',
                'label' => 'Payment',
                'title' => $payment->reference,
                'subtitle' => ($payment->client?->name ?? $payment->invoice?->reference ?? 'Payment')
                    .' · '.Money::format($payment->amount),
                'url' => route('payments.show', $payment),
                'badge' => $payment->method?->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function payrollRuns(string $term, int $limit): Collection
    {
        return PayrollRun::query()
            ->search($term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (PayrollRun $run) => [
                'type' => 'payroll',
                'label' => 'Payroll',
                'title' => $run->reference,
                'subtitle' => 'Period '.$run->periodLabel().' · '.(int) $run->guard_count.' payslips',
                'url' => route('payroll.show', $run),
                'badge' => $run->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function advances(string $term, int $limit): Collection
    {
        $like = '%'.$term.'%';

        return GuardSalaryAdvance::query()
            ->with(['assignedGuard:id,full_name,employment_id', 'assignedStaff:id,full_name,employment_id'])
            ->where(function ($q) use ($like): void {
                $q->where('label', 'like', $like)
                    ->orWhere('notes', 'like', $like)
                    ->orWhereHas('assignedGuard', fn ($g) => $g
                        ->where('full_name', 'like', $like)
                        ->orWhere('employment_id', 'like', $like))
                    ->orWhereHas('assignedStaff', fn ($s) => $s
                        ->where('full_name', 'like', $like)
                        ->orWhere('employment_id', 'like', $like));
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (GuardSalaryAdvance $advance) {
                $person = $advance->assignedGuard?->full_name ?? $advance->assignedStaff?->full_name ?? 'Advance';
                $code = $advance->assignedGuard?->employment_id ?? $advance->assignedStaff?->employment_id ?? '';
                $url = $advance->guard_id
                    ? route('guards.show', $advance->guard_id)
                    : ($advance->staff_id ? route('staff.show', $advance->staff_id) : route('advances.index'));

                return [
                    'type' => 'advance',
                    'label' => 'Advance',
                    'title' => $advance->label,
                    'subtitle' => trim($code.' · '.$person).' · Bal '.Money::format($advance->balance_remaining),
                    'url' => $url,
                    'badge' => $advance->is_active && (float) $advance->balance_remaining > 0 ? 'Active' : 'Closed',
                ];
            });
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function journals(string $term, int $limit): Collection
    {
        return GlJournal::query()
            ->search($term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (GlJournal $journal) => [
                'type' => 'journal',
                'label' => 'Journal',
                'title' => $journal->reference,
                'subtitle' => $journal->description,
                'url' => route('ledger.journals.show', $journal),
                'badge' => $journal->status->label(),
            ]);
    }

    /**
     * @return Collection<int, array{type: string, label: string, title: string, subtitle: string, url: string, badge: string|null}>
     */
    private function glAccounts(string $term, int $limit): Collection
    {
        return GlAccount::query()
            ->search($term)
            ->orderBy('code')
            ->limit($limit)
            ->get()
            ->map(fn (GlAccount $account) => [
                'type' => 'gl_account',
                'label' => 'GL account',
                'title' => $account->label(),
                'subtitle' => $account->type->label().($account->system_role ? ' · '.$account->system_role : ''),
                'url' => route('ledger.accounts.index', ['q' => $account->code]),
                'badge' => $account->is_active ? 'Active' : 'Inactive',
            ]);
    }
}
