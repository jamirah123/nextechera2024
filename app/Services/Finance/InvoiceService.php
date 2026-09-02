<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\GuardClassification;
use App\Enums\InvoiceStatus;
use App\Models\BillingProfile;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Services\AuditService;
use App\Services\ProactiveAlertService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    public function __construct(private AuditService $audit)
    {
    }

    /**
     * @param  array{
     *     client_id: int,
     *     site_id?: int|null,
     *     period_start: string,
     *     period_end: string,
     *     due_date?: string|null,
     *     tax_amount?: float|int|string,
     *     notes?: string|null,
     *     lines?: list<array{description: string, quantity: float|int|string, unit_price: float|int|string, site_id?: int|null}>,
     *     auto_generate?: bool
     * }  $data
     */
    public function createDraft(array $data): Invoice
    {
        return DB::transaction(function () use ($data) {
            $periodStart = Carbon::parse($data['period_start'])->toDateString();
            $periodEnd = Carbon::parse($data['period_end'])->toDateString();

            if ($periodEnd < $periodStart) {
                throw new InvalidArgumentException('Period end must be on or after period start.');
            }

            $invoice = Invoice::query()->create([
                'reference' => $this->nextReference($periodStart),
                'client_id' => $data['client_id'],
                'site_id' => $data['site_id'] ?? null,
                'status' => InvoiceStatus::Draft,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'due_date' => $data['due_date'] ?? Carbon::parse($periodEnd)->addDays((int) config('psg.invoice_due_days', 14))->toDateString(),
                'currency' => Money::currency(),
                'tax_amount' => $data['tax_amount'] ?? 0,
                'notes' => $data['notes'] ?? null,
            ]);

            $lines = $data['lines'] ?? [];
            if (($data['auto_generate'] ?? false) === true) {
                $lines = array_merge($lines, $this->suggestLines(
                    (int) $data['client_id'],
                    $data['site_id'] ?? null,
                    $periodStart,
                    $periodEnd,
                ));
            }

            foreach ($lines as $index => $line) {
                $this->addLine($invoice, $line, $index);
            }

            $this->recalculate($invoice);

            $this->audit->log(
                action: 'finance.invoice_created',
                summary: 'Draft invoice '.$invoice->reference.' created.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $invoice,
            );

            return $invoice->fresh(['client', 'site', 'lines']);
        });
    }

    /**
     * @param  array{description: string, quantity: float|int|string, unit_price: float|int|string, site_id?: int|null}  $line
     */
    public function addLine(Invoice $invoice, array $line, ?int $sortOrder = null): InvoiceLine
    {
        if (! $invoice->isEditable()) {
            throw new InvalidArgumentException('Only draft invoices can be edited.');
        }

        $qty = (float) $line['quantity'];
        $price = (float) $line['unit_price'];

        return InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'site_id' => $line['site_id'] ?? $invoice->site_id,
            'description' => $line['description'],
            'quantity' => $qty,
            'unit_price' => $price,
            'line_total' => round($qty * $price, 2),
            'sort_order' => $sortOrder ?? ((int) $invoice->lines()->max('sort_order') + 1),
        ]);
    }

    /**
     * @param  array{
     *     client_id?: int,
     *     site_id?: int|null,
     *     period_start?: string,
     *     period_end?: string,
     *     due_date?: string|null,
     *     tax_amount?: float|int|string,
     *     notes?: string|null,
     *     lines?: list<array{description: string, quantity: float|int|string, unit_price: float|int|string, site_id?: int|null}>
     * }  $data
     */
    public function updateDraft(Invoice $invoice, array $data): Invoice
    {
        return DB::transaction(function () use ($invoice, $data) {
            if (! $invoice->isEditable()) {
                throw new InvalidArgumentException('Only draft invoices can be edited.');
            }

            $invoice->update([
                'client_id' => $data['client_id'] ?? $invoice->client_id,
                'site_id' => array_key_exists('site_id', $data) ? $data['site_id'] : $invoice->site_id,
                'period_start' => $data['period_start'] ?? $invoice->period_start->toDateString(),
                'period_end' => $data['period_end'] ?? $invoice->period_end->toDateString(),
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $invoice->due_date?->toDateString(),
                'tax_amount' => $data['tax_amount'] ?? $invoice->tax_amount,
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
            ]);

            if (array_key_exists('lines', $data)) {
                $invoice->lines()->delete();
                foreach ($data['lines'] as $index => $line) {
                    $this->addLine($invoice->fresh(), $line, $index);
                }
            }

            $this->recalculate($invoice->fresh());

            $this->audit->log(
                action: 'finance.invoice_updated',
                summary: 'Draft invoice '.$invoice->reference.' updated.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $invoice,
            );

            return $invoice->fresh(['client', 'site', 'lines']);
        });
    }

    public function issue(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            if (! $invoice->isEditable()) {
                throw new InvalidArgumentException('Only draft invoices can be issued.');
            }

            $this->recalculate($invoice);
            $invoice->refresh();

            if ((float) $invoice->total <= 0) {
                throw new InvalidArgumentException('Cannot issue an invoice with zero total.');
            }

            $invoice->update([
                'status' => InvoiceStatus::Issued,
                'issue_date' => now()->toDateString(),
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $this->audit->log(
                action: 'finance.invoice_issued',
                summary: 'Invoice '.$invoice->reference.' issued.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $invoice,
            );

            return $invoice->fresh(['client', 'site', 'lines', 'approver']);
        });
    }

    public function cancel(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice) {
            if ($invoice->status === InvoiceStatus::Paid) {
                throw new InvalidArgumentException('Paid invoices cannot be cancelled.');
            }

            if ((float) $invoice->amount_paid > 0) {
                throw new InvalidArgumentException('Invoices with recorded payments cannot be cancelled.');
            }

            $invoice->update(['status' => InvoiceStatus::Cancelled]);

            $this->audit->log(
                action: 'finance.invoice_cancelled',
                summary: 'Invoice '.$invoice->reference.' cancelled.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Warning,
                subject: $invoice,
            );

            return $invoice->fresh();
        });
    }

    public function recalculate(Invoice $invoice): Invoice
    {
        $subtotal = (float) $invoice->lines()->sum('line_total');
        $tax = (float) $invoice->tax_amount;
        $total = round($subtotal + $tax, 2);
        $paid = (float) $invoice->amount_paid;
        $balance = round(max(0, $total - $paid), 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'total' => $total,
            'balance' => $balance,
        ]);

        return $invoice->fresh();
    }

    public function refreshPaymentState(Invoice $invoice): Invoice
    {
        $invoice->refresh();
        $paid = (float) $invoice->payments()->sum('amount');
        $total = (float) $invoice->total;
        $balance = round(max(0, $total - $paid), 2);

        $status = $invoice->status;
        if ($status !== InvoiceStatus::Cancelled && $status !== InvoiceStatus::Draft) {
            if ($paid <= 0) {
                $status = ($invoice->due_date && $invoice->due_date->lt(now()->startOfDay()))
                    ? InvoiceStatus::Overdue
                    : InvoiceStatus::Issued;
            } elseif ($balance <= 0) {
                $status = InvoiceStatus::Paid;
            } else {
                $status = InvoiceStatus::PartiallyPaid;
            }
        }

        $invoice->update([
            'amount_paid' => $paid,
            'balance' => $balance,
            'status' => $status,
        ]);

        return $invoice->fresh();
    }

    public function markOverdueInvoices(): int
    {
        $invoices = Invoice::query()
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where('balance', '>', 0)
            ->get();

        $alerts = app(ProactiveAlertService::class);
        $count = 0;

        foreach ($invoices as $invoice) {
            $invoice->update(['status' => InvoiceStatus::Overdue->value]);
            $alerts->alertInvoiceOverdue($invoice);
            $count++;
        }

        return $count;
    }

    /**
     * @return list<array{description: string, quantity: float, unit_price: float, site_id: int|null}>
     */
    public function suggestLines(int $clientId, ?int $siteId, string $periodStart, string $periodEnd): array
    {
        $lines = [];

        $profiles = BillingProfile::query()
            ->active()
            ->where('client_id', $clientId)
            ->when($siteId, fn ($q) => $q->where(fn ($inner) => $inner->whereNull('site_id')->orWhere('site_id', $siteId)))
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart))
            ->with('site:id,name,code')
            ->get();

        foreach ($profiles as $profile) {
            $siteName = $profile->site?->name ?? 'all sites';

            if ((float) $profile->monthly_site_fee > 0) {
                $label = $profile->site
                    ? 'Monthly site fee — '.$profile->site->name
                    : 'Monthly client service fee';
                $lines[] = [
                    'description' => $label.' ('.$periodStart.' to '.$periodEnd.')',
                    'quantity' => 1,
                    'unit_price' => (float) $profile->monthly_site_fee,
                    'site_id' => $profile->site_id,
                ];
            }

            foreach (GuardClassification::cases() as $classification) {
                $count = $classification === GuardClassification::Armed
                    ? (int) $profile->contracted_armed_guards
                    : (int) $profile->contracted_unarmed_guards;
                $monthlyRate = $classification === GuardClassification::Armed
                    ? (float) $profile->monthly_rate_per_armed_guard
                    : (float) $profile->monthly_rate_per_unarmed_guard;

                if ($count > 0 && $monthlyRate > 0) {
                    $lines[] = [
                        'description' => 'Monthly '.$classification->label().' guard coverage — '.$siteName.' ('.$count.' guards, '.$periodStart.' to '.$periodEnd.')',
                        'quantity' => $count,
                        'unit_price' => $monthlyRate,
                        'site_id' => $profile->site_id,
                    ];
                }
            }
        }

        return $lines;
    }

    public function nextReference(string $periodStart): string
    {
        $prefix = 'INV-'.Carbon::parse($periodStart)->format('Ym').'-';
        $latest = Invoice::withTrashed()
            ->where('reference', 'like', $prefix.'%')
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
