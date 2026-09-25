<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\BillingMode;
use App\Enums\GlJournalSource;
use App\Enums\GuardClassification;
use App\Enums\InvoiceStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\BillingProfile;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Shift;
use App\Models\Site;
use App\Services\AuditService;
use App\Services\Finance\Ledger\LedgerPostingService;
use App\Services\ProactiveAlertService;
use App\Support\Money;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceService
{
    public function __construct(
        private AuditService $audit,
        private LedgerPostingService $ledger,
    ) {}

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

            $profiles = $this->profilesFor($data['client_id'], $data['site_id'] ?? null, $periodStart, $periodEnd);
            $cashNoTax = $profiles->contains(fn (BillingProfile $profile) => $profile->cash_no_tax);
            $taxAmount = (float) ($data['tax_amount'] ?? 0);
            if ($cashNoTax && ! array_key_exists('tax_amount', $data)) {
                $taxAmount = 0.0;
            }

            $notes = $data['notes'] ?? null;
            if ($cashNoTax && blank($notes)) {
                $notes = 'Settled on a cash basis — no VAT charged.';
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
                'tax_amount' => $taxAmount,
                'notes' => $notes,
            ]);

            $lines = $data['lines'] ?? [];
            if (($data['auto_generate'] ?? false) === true) {
                $lines = array_merge($lines, $this->suggestLinesFromProfiles(
                    $profiles,
                    $periodStart,
                    $periodEnd,
                ));

                if ($lines === []) {
                    throw new InvalidArgumentException(
                        'No billable lines found for this period. Check the client billing profile, contracted posts/rates, or completed shifts.'
                    );
                }
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

    public function issue(Invoice $invoice, ?string $issueDate = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $issueDate) {
            if (! $invoice->isEditable()) {
                throw new InvalidArgumentException('Only draft invoices can be issued.');
            }

            $this->recalculate($invoice);
            $invoice->refresh();

            if ($invoice->lines()->count() === 0) {
                throw new InvalidArgumentException('Cannot issue an invoice with no line items.');
            }

            if ((float) $invoice->total <= 0) {
                throw new InvalidArgumentException('Cannot issue an invoice with zero total.');
            }

            $issuedOn = Carbon::parse($issueDate ?? now()->toDateString())->startOfDay();
            $dueOn = $invoice->due_date?->copy()->startOfDay();

            // Due date must never precede issue date (common when a past-period draft is issued later).
            if ($dueOn === null || $dueOn->lt($issuedOn)) {
                $dueOn = $issuedOn->copy()->addDays((int) config('psg.invoice_due_days', 14));
            }

            $invoice->update([
                'status' => InvoiceStatus::Issued,
                'issue_date' => $issuedOn->toDateString(),
                'due_date' => $dueOn->toDateString(),
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

            $fresh = $invoice->fresh(['client', 'site', 'lines', 'approver']);
            $this->ledger->postInvoice($fresh, auth()->user());

            return $fresh;
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

            $this->ledger->reverseForDocument(
                GlJournalSource::Invoice,
                $invoice,
                auth()->user(),
                'Invoice cancelled'
            );

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
        return $this->suggestLinesFromProfiles(
            $this->profilesFor($clientId, $siteId, $periodStart, $periodEnd),
            $periodStart,
            $periodEnd,
        );
    }

    /**
     * @param  Collection<int, BillingProfile>  $profiles
     * @return list<array{description: string, quantity: float, unit_price: float, site_id: int|null}>
     */
    public function suggestLinesFromProfiles(Collection $profiles, string $periodStart, string $periodEnd): array
    {
        $lines = [];

        foreach ($profiles as $profile) {
            $mode = $profile->billing_mode ?? BillingMode::Monthly;

            if ($mode->usesMonthlyRates()) {
                $lines = array_merge($lines, $this->monthlyLinesForProfile($profile, $periodStart, $periodEnd));
            }

            if ($mode->usesShiftRates()) {
                $lines = array_merge(
                    $lines,
                    $this->shiftLinesForProfile(
                        $profile,
                        $periodStart,
                        $periodEnd,
                        extrasOnly: $mode === BillingMode::Hybrid,
                    ),
                );
            }
        }

        return $lines;
    }

    /**
     * Resolve billing profiles without double-counting client-wide + site rates.
     *
     * Site invoice: site profile if present, otherwise client-wide.
     * Client-wide invoice: client-wide profile if present, otherwise one profile per site.
     *
     * @return Collection<int, BillingProfile>
     */
    private function profilesFor(int $clientId, ?int $siteId, string $periodStart, string $periodEnd): Collection
    {
        $base = BillingProfile::query()
            ->active()
            ->where('client_id', $clientId)
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart))
            ->with('site:id,name,code,client_id');

        if ($siteId) {
            $siteProfile = (clone $base)
                ->where('site_id', $siteId)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            if ($siteProfile) {
                return collect([$siteProfile]);
            }

            $clientWide = (clone $base)
                ->whereNull('site_id')
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            return $clientWide ? collect([$clientWide]) : collect();
        }

        $clientWide = (clone $base)
            ->whereNull('site_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($clientWide) {
            return collect([$clientWide]);
        }

        return (clone $base)
            ->whereNotNull('site_id')
            ->orderBy('site_id')
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique('site_id')
            ->values();
    }

    /**
     * @return list<array{description: string, quantity: float, unit_price: float, site_id: int|null}>
     */
    private function monthlyLinesForProfile(BillingProfile $profile, string $periodStart, string $periodEnd): array
    {
        $lines = [];
        $siteName = $profile->site?->name ?? 'all sites';
        $periodLabel = $this->periodLabel($periodStart, $periodEnd);

        $armedRate = $profile->monthlyArmedRate();
        $unarmedRate = $profile->monthlyUnarmedRate();

        foreach ([
            [
                'label' => 'Day armed',
                'count' => (int) $profile->contracted_day_armed_guards,
                'rate' => $armedRate,
            ],
            [
                'label' => 'Day unarmed',
                'count' => (int) $profile->contracted_day_unarmed_guards,
                'rate' => $unarmedRate,
            ],
            [
                'label' => 'Night armed',
                'count' => (int) $profile->contracted_night_armed_guards,
                'rate' => $armedRate,
            ],
            [
                'label' => 'Night unarmed',
                'count' => (int) $profile->contracted_night_unarmed_guards,
                'rate' => $unarmedRate,
            ],
        ] as $row) {
            if ($row['count'] > 0 && $row['rate'] > 0) {
                $lines[] = [
                    'description' => $row['label'].' security posts — '.$siteName
                        .' ('.$row['count'].' posts, '.$periodLabel.')',
                    'quantity' => (float) $row['count'],
                    'unit_price' => $row['rate'],
                    'site_id' => $profile->site_id,
                ];
            }
        }

        return $lines;
    }

    /**
     * @return list<array{description: string, quantity: float, unit_price: float, site_id: int|null}>
     */
    private function shiftLinesForProfile(
        BillingProfile $profile,
        string $periodStart,
        string $periodEnd,
        bool $extrasOnly = false,
    ): array {
        $siteIds = $this->siteIdsForProfile($profile);

        if ($siteIds === []) {
            return [];
        }

        $query = Shift::query()
            ->whereIn('site_id', $siteIds)
            ->where('status', ShiftStatus::Completed)
            ->whereDate('shift_date', '>=', $periodStart)
            ->whereDate('shift_date', '<=', $periodEnd);

        if ($extrasOnly) {
            $query->whereIn('shift_type', [
                ShiftType::Overtime->value,
                ShiftType::SpecialDuty->value,
            ]);
        } else {
            $query->whereIn('shift_type', array_map(
                static fn (ShiftType $type) => $type->value,
                array_filter(ShiftType::cases(), static fn (ShiftType $type) => $type->countsAsWorked()),
            ));
        }

        $shifts = $query->get(['id', 'site_id', 'period', 'guard_classification', 'shift_type']);

        if ($shifts->isEmpty()) {
            return [];
        }

        $grouped = $shifts->groupBy(function (Shift $shift) {
            $classification = ($shift->guard_classification ?? GuardClassification::Unarmed)->value;
            $period = ($shift->period ?? ShiftPeriod::Day)->value;

            return $classification.'|'.$period.'|'.($shift->site_id ?? 0);
        });

        $lines = [];
        $siteName = $profile->site?->name ?? 'client sites';
        $periodLabel = $this->periodLabel($periodStart, $periodEnd);

        foreach ($grouped as $key => $bucket) {
            [$classificationValue, $periodValue, $bucketSiteId] = explode('|', $key);
            $classification = GuardClassification::from($classificationValue);
            $period = ShiftPeriod::from($periodValue);
            $rate = $profile->shiftBillRateFor($classification, $period);

            if ($rate <= 0) {
                continue;
            }

            $qty = (float) $bucket->count();
            $prefix = $extrasOnly ? 'Extra duty — ' : '';
            $lines[] = [
                'description' => $prefix.$period->label().' '.$classification->label()
                    .' shifts — '.$siteName.' ('.$periodLabel.')',
                'quantity' => $qty,
                'unit_price' => $rate,
                'site_id' => $profile->site_id ?? ((int) $bucketSiteId ?: null),
            ];
        }

        return $lines;
    }

    private function periodLabel(string $periodStart, string $periodEnd): string
    {
        return Carbon::parse($periodStart)->format('d M Y').' – '.Carbon::parse($periodEnd)->format('d M Y');
    }

    /** @return list<int> */
    private function siteIdsForProfile(BillingProfile $profile): array
    {
        if ($profile->site_id) {
            return [(int) $profile->site_id];
        }

        return Site::query()
            ->where('client_id', $profile->client_id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function nextReference(string $periodStart): string
    {
        $docPrefix = strtoupper((string) config('psg.prefixes.invoice', 'INV'));
        $prefix = $docPrefix.'-'.Carbon::parse($periodStart)->format('Ym').'-';
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
