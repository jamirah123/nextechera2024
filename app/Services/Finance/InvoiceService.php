<?php

namespace App\Services\Finance;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\BillingMode;
use App\Enums\GlJournalSource;
use App\Enums\GuardClassification;
use App\Enums\InvoiceStatus;
use App\Enums\VatTreatment;
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
     *     period_start?: string|null,
     *     period_end?: string|null,
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
            if (filled($data['period_start'] ?? null) && filled($data['period_end'] ?? null)) {
                $periodStart = Carbon::parse($data['period_start'])->toDateString();
                $periodEnd = Carbon::parse($data['period_end'])->toDateString();

                if ($periodEnd < $periodStart) {
                    throw new InvalidArgumentException('Period end must be on or after period start.');
                }
            } else {
                $profile = BillingProfile::query()->find($data['billing_profile_id'] ?? 0);
                if (! $profile) {
                    throw new InvalidArgumentException('Select an approved billing profile.');
                }

                [$periodStart, $periodEnd] = $this->periodFromProfile($profile);
            }

            $anchor = $this->resolveAnchor($data, $periodStart, $periodEnd);
            $followProfile = ! (filled($data['period_start'] ?? null) && filled($data['period_end'] ?? null));

            return $this->generateFromAnchor($anchor, $periodStart, $periodEnd, $data['due_date'] ?? null, $followProfile);
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
                'due_date' => array_key_exists('due_date', $data) ? $data['due_date'] : $invoice->due_date?->toDateString(),
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $invoice->notes,
            ]);

            if (array_key_exists('lines', $data)) {
                $invoice->lines()->delete();
                foreach ($data['lines'] as $index => $line) {
                    $this->addLine($invoice->fresh(), $line, $index);
                }
            }

            $fresh = $this->recalculate($invoice->fresh());
            $this->rememberCalculation($fresh);

            $this->audit->log(
                action: 'finance.invoice_updated',
                summary: 'Draft invoice '.$invoice->reference.' updated.',
                category: AuditCategory::Finance,
                severity: AuditSeverity::Notice,
                subject: $invoice,
            );

            return $fresh->fresh(['client', 'site', 'lines', 'billingProfile']);
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

            $this->assertReadyToIssue($invoice);

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

            $invoice->refresh();
            $snapshot = $this->snapshotPayload($invoice, frozen: true);
            $invoice->update([
                'issued_snapshot' => $snapshot,
                'calculation_snapshot' => $invoice->calculation_snapshot ?: $snapshot,
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
            DB::table('invoice_billed_shifts')->where('invoice_id', $invoice->id)->delete();

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
        $subtotal = round((float) $invoice->lines()->sum('line_total'), 2);
        $tax = $this->taxFor($invoice, $subtotal);
        $total = round($subtotal + $tax, 2);
        $paid = (float) $invoice->amount_paid;
        $balance = round(max(0, $total - $paid), 2);

        $invoice->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
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
            ->whereBetween('shift_date', [$periodStart, $periodEnd]);

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

    private function resolveAnchor(array $data, string $periodStart, string $periodEnd): BillingProfile
    {
        if (filled($data['billing_profile_id'] ?? null)) {
            $profile = BillingProfile::query()->lockForUpdate()->find($data['billing_profile_id']);

            if ($profile === null) {
                throw new InvalidArgumentException('Select an approved billing profile.');
            }

            return $profile;
        }

        $clientId = (int) ($data['client_id'] ?? 0);
        $siteId = filled($data['site_id'] ?? null) ? (int) $data['site_id'] : null;
        $base = BillingProfile::query()
            ->approved()
            ->where('client_id', $clientId)
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart))
            ->lockForUpdate();

        if ($siteId) {
            $siteProfile = (clone $base)->where('site_id', $siteId)->orderByDesc('effective_from')->orderByDesc('id')->first();

            if ($siteProfile) {
                return $siteProfile;
            }

            $clientWide = (clone $base)->whereNull('site_id')->orderByDesc('effective_from')->orderByDesc('id')->first();

            if ($clientWide) {
                return $clientWide;
            }
        } else {
            $clientWide = (clone $base)->whereNull('site_id')->orderByDesc('effective_from')->orderByDesc('id')->first();

            if ($clientWide) {
                return $clientWide;
            }
        }

        throw new InvalidArgumentException('No approved billing profile covers this client for the selected period.');
    }

    private function generateFromAnchor(BillingProfile $anchor, string $periodStart, string $periodEnd, ?string $dueDate = null, bool $followProfile = false): Invoice
    {
        $anchor->loadMissing('site');

        if (! $anchor->isApproved()) {
            throw new InvalidArgumentException('This billing profile is not approved. Save it as active before generating an invoice.');
        }

        if (! $this->rangeOverlapsProfile($anchor, $periodStart, $periodEnd)) {
            throw new InvalidArgumentException('This billing profile does not apply to the selected period.');
        }

        $slices = $followProfile
            ? [['profile' => $anchor, 'from' => $periodStart, 'to' => $periodEnd]]
            : $this->coverageSlices($anchor, $periodStart, $periodEnd);
        $treatments = collect($slices)->map(fn (array $slice) => $slice['profile']->vatTreatment()->value)->unique();

        if ($treatments->count() > 1) {
            throw new InvalidArgumentException('This period crosses a VAT treatment change. Generate a separate invoice for each treatment.');
        }

        $this->assertNoOverlappingInvoice($anchor, $periodStart, $periodEnd);

        $built = $this->linesForSlices($slices);
        $lines = $built['lines'];
        $shiftIds = $built['shift_ids'];

        if ($lines === []) {
            throw new InvalidArgumentException('No billable lines found for this period. Check the contracted posts, rates, or completed shifts on the billing profile.');
        }

        $this->assertShiftsAvailable($shiftIds);

        $treatment = $anchor->vatTreatment();
        foreach ($slices as $slice) {
            if ($slice['profile']->vatTreatment() !== $treatment) {
                $treatment = $slice['profile']->vatTreatment();
            }
        }

        $vatRate = $treatment->chargesVat() ? round((float) config('psg.vat_rate', 18), 2) : 0.0;
        $cashTerms = collect($slices)->contains(fn (array $slice) => (bool) $slice['profile']->cash_no_tax);

        $invoice = Invoice::query()->create([
            'reference' => $this->nextReference($periodStart),
            'client_id' => $anchor->client_id,
            'site_id' => $anchor->site_id,
            'billing_profile_id' => $anchor->id,
            'status' => InvoiceStatus::Draft,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'due_date' => filled($dueDate)
                ? Carbon::parse($dueDate)->toDateString()
                : Carbon::parse($periodEnd)->addDays((int) config('psg.invoice_due_days', 14))->toDateString(),
            'currency' => Money::currency(),
            'vat_treatment' => $treatment->value,
            'vat_rate' => $vatRate,
            'tax_amount' => 0,
            'notes' => $this->draftNotes($treatment, $cashTerms),
        ]);

        foreach ($lines as $index => $line) {
            $this->addLine($invoice, $line, $index);
        }

        if ($shiftIds !== []) {
            DB::table('invoice_billed_shifts')->insert(array_map(fn (int $shiftId) => [
                'invoice_id' => $invoice->id,
                'shift_id' => $shiftId,
            ], $shiftIds));
        }

        $fresh = $this->recalculate($invoice->fresh());
        $this->rememberCalculation($fresh, $slices);

        $this->audit->log(
            action: 'finance.invoice_created',
            summary: 'Draft invoice '.$fresh->reference.' created from billing profile #'.$anchor->id.'.',
            category: AuditCategory::Finance,
            severity: AuditSeverity::Notice,
            subject: $fresh,
        );

        return $fresh->fresh(['client', 'site', 'lines', 'billingProfile']);
    }

    /**
     * @return list<array{profile: BillingProfile, from: string, to: string}>
     */
    private function coverageSlices(BillingProfile $anchor, string $periodStart, string $periodEnd): array
    {
        $profiles = BillingProfile::query()
            ->approved()
            ->where('client_id', $anchor->client_id)
            ->when(
                $anchor->site_id,
                fn ($query) => $query->where('site_id', $anchor->site_id),
                fn ($query) => $query->whereNull('site_id'),
            )
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart))
            ->with('site:id,name,code')
            ->orderBy('effective_from')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $cursor = Carbon::parse($periodStart)->startOfDay();
        $last = Carbon::parse($periodEnd)->startOfDay();
        $slices = [];

        while ($cursor->lte($last)) {
            $covering = $profiles->filter(fn (BillingProfile $profile) => $profile->coversDate($cursor->toDateString()))->values();

            if ($covering->isEmpty()) {
                throw new InvalidArgumentException('No approved billing profile covers '.$cursor->toFormattedDateString().' for this client and site. Extend the contract or shorten the period.');
            }

            if ($covering->count() > 1) {
                throw new InvalidArgumentException('More than one approved billing profile covers '.$cursor->toFormattedDateString().'. Adjust the contract dates so they do not overlap.');
            }

            /** @var BillingProfile $profile */
            $profile = $covering->first();
            $sliceEnd = $last->copy();

            if ($profile->effective_to !== null && $profile->effective_to->copy()->startOfDay()->lt($sliceEnd)) {
                $sliceEnd = $profile->effective_to->copy()->startOfDay();
            }

            $slices[] = [
                'profile' => $profile,
                'from' => $cursor->toDateString(),
                'to' => $sliceEnd->toDateString(),
            ];

            $cursor = $sliceEnd->copy()->addDay();
        }

        return $slices;
    }

    /**
     * @param  list<array{profile: BillingProfile, from: string, to: string}>  $slices
     * @return array{lines: list<array{description: string, quantity: float, unit_price: float, site_id: int|null}>, shift_ids: list<int>}
     */
    private function linesForSlices(array $slices): array
    {
        $lines = [];
        $shiftIds = [];

        foreach ($slices as $slice) {
            $profile = $slice['profile'];
            $mode = $profile->billing_mode ?? BillingMode::Monthly;

            if ($mode->usesMonthlyRates()) {
                $lines = array_merge($lines, $this->monthlyLinesForRange($profile, $slice['from'], $slice['to']));
            }

            if ($mode->usesShiftRates()) {
                $shiftLines = $this->shiftLinesForRange(
                    $profile,
                    $slice['from'],
                    $slice['to'],
                    extrasOnly: $mode === BillingMode::Hybrid,
                );
                $lines = array_merge($lines, $shiftLines['lines']);
                $shiftIds = array_merge($shiftIds, $shiftLines['shift_ids']);
            }
        }

        return [
            'lines' => $lines,
            'shift_ids' => array_values(array_unique($shiftIds)),
        ];
    }

    /**
     * @return list<array{description: string, quantity: float, unit_price: float, site_id: int|null}>
     */
    private function monthlyLinesForRange(BillingProfile $profile, string $from, string $to): array
    {
        $lines = [];
        $cursor = Carbon::parse($from)->startOfDay();
        $end = Carbon::parse($to)->startOfDay();
        $siteName = $profile->site?->name ?? 'all sites';
        $armedRate = $profile->monthlyArmedRate();
        $unarmedRate = $profile->monthlyUnarmedRate();

        while ($cursor->lte($end)) {
            $monthEnd = $cursor->copy()->endOfMonth()->startOfDay();
            $sliceEnd = $end->lt($monthEnd) ? $end->copy() : $monthEnd;
            $days = $cursor->diff($sliceEnd)->days + 1;
            $monthDays = $cursor->daysInMonth;
            $factor = $days === $monthDays ? 1 : ($days / $monthDays);
            $label = $this->periodLabel($cursor->toDateString(), $sliceEnd->toDateString());

            if ($factor < 1) {
                $label .= ', '.$days.'/'.$monthDays.' days';
            }

            foreach ([
                ['label' => 'Day armed', 'count' => (int) $profile->contracted_day_armed_guards, 'rate' => $armedRate],
                ['label' => 'Day unarmed', 'count' => (int) $profile->contracted_day_unarmed_guards, 'rate' => $unarmedRate],
                ['label' => 'Night armed', 'count' => (int) $profile->contracted_night_armed_guards, 'rate' => $armedRate],
                ['label' => 'Night unarmed', 'count' => (int) $profile->contracted_night_unarmed_guards, 'rate' => $unarmedRate],
            ] as $row) {
                if ($row['count'] <= 0 || $row['rate'] <= 0) {
                    continue;
                }

                $lines[] = [
                    'description' => $row['label'].' security posts — '.$siteName.' ('.$row['count'].' posts, '.$label.')',
                    'quantity' => (float) $row['count'],
                    'unit_price' => round($row['rate'] * $factor, 2),
                    'site_id' => $profile->site_id,
                ];
            }

            $cursor = $sliceEnd->copy()->addDay();
        }

        return $lines;
    }

    /**
     * @return array{lines: list<array{description: string, quantity: float, unit_price: float, site_id: int|null}>, shift_ids: list<int>}
     */
    private function shiftLinesForRange(BillingProfile $profile, string $periodStart, string $periodEnd, bool $extrasOnly = false): array
    {
        $siteIds = $this->siteIdsForProfile($profile);

        if ($siteIds === []) {
            return ['lines' => [], 'shift_ids' => []];
        }

        $query = Shift::query()
            ->whereIn('site_id', $siteIds)
            ->where('status', ShiftStatus::Completed)
            ->whereBetween('shift_date', [$periodStart, $periodEnd]);

        if ($extrasOnly) {
            $query->whereIn('shift_type', [ShiftType::Overtime->value, ShiftType::SpecialDuty->value]);
        } else {
            $query->whereIn('shift_type', array_map(
                static fn (ShiftType $type) => $type->value,
                array_filter(ShiftType::cases(), static fn (ShiftType $type) => $type->countsAsWorked()),
            ));
        }

        $shifts = $query->orderBy('id')->lockForUpdate()->get(['id', 'site_id', 'period', 'guard_classification', 'shift_type']);

        if ($shifts->isEmpty()) {
            return ['lines' => [], 'shift_ids' => []];
        }

        $grouped = $shifts->groupBy(function (Shift $shift) {
            $classification = ($shift->guard_classification ?? GuardClassification::Unarmed)->value;
            $period = ($shift->period ?? ShiftPeriod::Day)->value;

            return $classification.'|'.$period.'|'.($shift->site_id ?? 0);
        });

        $lines = [];
        $shiftIds = [];
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

            $shiftIds = array_merge($shiftIds, $bucket->pluck('id')->map(fn ($id) => (int) $id)->all());
            $prefix = $extrasOnly ? 'Extra duty — ' : '';
            $lines[] = [
                'description' => $prefix.$period->label().' '.$classification->label().' shifts — '.$siteName.' ('.$periodLabel.')',
                'quantity' => (float) $bucket->count(),
                'unit_price' => $rate,
                'site_id' => $profile->site_id ?? ((int) $bucketSiteId ?: null),
            ];
        }

        return ['lines' => $lines, 'shift_ids' => $shiftIds];
    }

    /** @param  list<int>  $shiftIds */
    private function assertShiftsAvailable(array $shiftIds): void
    {
        if ($shiftIds === []) {
            return;
        }

        $taken = DB::table('invoice_billed_shifts')->whereIn('shift_id', $shiftIds)->lockForUpdate()->exists();

        if ($taken) {
            throw new InvalidArgumentException('Completed shifts in this period are already on another invoice.');
        }
    }

    private function assertNoOverlappingInvoice(BillingProfile $anchor, string $periodStart, string $periodEnd, ?int $ignoreInvoiceId = null): void
    {
        $overlap = Invoice::query()
            ->with('site:id,name,code')
            ->where('client_id', $anchor->client_id)
            ->where('status', '!=', InvoiceStatus::Cancelled->value)
            ->whereDate('period_start', '<=', $periodEnd)
            ->whereDate('period_end', '>=', $periodStart)
            ->when($ignoreInvoiceId, fn ($query) => $query->where('id', '!=', $ignoreInvoiceId))
            ->when(
                $anchor->site_id,
                fn ($query) => $query->where(fn ($inner) => $inner->whereNull('site_id')->orWhere('site_id', $anchor->site_id)),
                fn ($query) => $query->whereNull('site_id'),
            )
            ->lockForUpdate()
            ->first();

        if ($overlap) {
            $scope = $overlap->site_id
                ? ($overlap->site?->name ?? 'one site')
                : 'all sites';

            throw new InvalidArgumentException(
                'Invoice '.$overlap->reference.' already covers '.$scope.' for '
                .$overlap->period_start->format('d M Y').' – '.$overlap->period_end->format('d M Y')
                .'. Cancel that invoice or choose a period that does not overlap.'
            );
        }
    }

    private function assertReadyToIssue(Invoice $invoice): void
    {
        if ($invoice->client_id === null) {
            throw new InvalidArgumentException('This invoice has no client.');
        }

        if ($invoice->billing_profile_id === null) {
            return;
        }

        $profile = $invoice->billingProfile()->lockForUpdate()->first();

        if ($profile === null || ! $profile->isApproved()) {
            throw new InvalidArgumentException('The billing profile is no longer approved.');
        }

        if ($invoice->vat_treatment === null) {
            throw new InvalidArgumentException('This draft has no VAT treatment. Generate it again from the billing profile.');
        }

        if ($invoice->vat_treatment === VatTreatment::Standard && (float) $invoice->vat_rate <= 0) {
            throw new InvalidArgumentException('Set the VAT rate in platform settings before issuing a standard-rated invoice.');
        }

        if ($invoice->period_end->lt($invoice->period_start)) {
            throw new InvalidArgumentException('The service period is not valid.');
        }

        $this->assertNoOverlappingInvoice(
            $profile,
            $invoice->period_start->toDateString(),
            $invoice->period_end->toDateString(),
            $invoice->id,
        );

        $shiftIds = DB::table('invoice_billed_shifts')->where('invoice_id', $invoice->id)->pluck('shift_id');

        if ($shiftIds->isEmpty()) {
            return;
        }

        $conflict = DB::table('invoice_billed_shifts as billed')
            ->join('invoices', 'invoices.id', '=', 'billed.invoice_id')
            ->whereIn('billed.shift_id', $shiftIds)
            ->where('billed.invoice_id', '!=', $invoice->id)
            ->where('invoices.status', '!=', InvoiceStatus::Cancelled->value)
            ->whereNull('invoices.deleted_at')
            ->exists();

        if ($conflict) {
            throw new InvalidArgumentException('Some shifts on this draft are already billed on another invoice.');
        }
    }

    private function taxFor(Invoice $invoice, float $subtotal): float
    {
        if ($invoice->vat_treatment === null) {
            return round((float) $invoice->tax_amount, 2);
        }

        if (! $invoice->vat_treatment->chargesVat()) {
            return 0.0;
        }

        $rate = (float) ($invoice->vat_rate ?? config('psg.vat_rate', 18));

        return round($subtotal * $rate / 100, 2);
    }

    private function draftNotes(VatTreatment $treatment, bool $cashTerms): ?string
    {
        $parts = [];

        if ($cashTerms) {
            $parts[] = 'Payment terms: cash settlement.';
        }

        $parts[] = match ($treatment) {
            VatTreatment::Standard => 'VAT classification: standard-rated.',
            VatTreatment::ZeroRated => 'VAT classification: zero-rated.',
            VatTreatment::Exempt => 'VAT classification: exempt.',
        };

        return implode(' ', $parts);
    }

    /** @param  list<array{profile: BillingProfile, from: string, to: string}>|null  $slices */
    private function rememberCalculation(Invoice $invoice, ?array $slices = null): void
    {
        $existing = $invoice->calculation_snapshot ?? [];
        $payload = $this->snapshotPayload($invoice, frozen: false);

        if ($slices !== null) {
            $payload['slices'] = array_map(fn (array $slice) => [
                'billing_profile_id' => $slice['profile']->id,
                'from' => $slice['from'],
                'to' => $slice['to'],
                'billing_mode' => $slice['profile']->billing_mode?->value,
                'vat_treatment' => $slice['profile']->vatTreatment()->value,
            ], $slices);
        } elseif (isset($existing['slices'])) {
            $payload['slices'] = $existing['slices'];
        }

        $invoice->update(['calculation_snapshot' => $payload]);
    }

    /** @return array<string, mixed> */
    private function snapshotPayload(Invoice $invoice, bool $frozen): array
    {
        $invoice->loadMissing('lines');

        return [
            'frozen' => $frozen,
            'captured_at' => now()->toIso8601String(),
            'billing_profile_id' => $invoice->billing_profile_id,
            'client_id' => $invoice->client_id,
            'site_id' => $invoice->site_id,
            'period_start' => $invoice->period_start?->toDateString(),
            'period_end' => $invoice->period_end?->toDateString(),
            'vat_treatment' => $invoice->vat_treatment?->value,
            'vat_rate' => $invoice->vat_rate !== null ? (float) $invoice->vat_rate : null,
            'subtotal' => (float) $invoice->subtotal,
            'tax_amount' => (float) $invoice->tax_amount,
            'total' => (float) $invoice->total,
            'balance' => (float) $invoice->balance,
            'lines' => $invoice->lines->map(fn (InvoiceLine $line) => [
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'line_total' => (float) $line->line_total,
                'site_id' => $line->site_id,
            ])->values()->all(),
        ];
    }

    /** @return array{0: string, 1: string} */
    private function periodFromProfile(BillingProfile $profile): array
    {
        $standing = BillingProfile::standingsFor([$profile])[$profile->id] ?? 'ended';
        $period = $profile->invoicePeriod($standing);

        if ($period === null) {
            throw new InvalidArgumentException(BillingProfile::standingMessage($profile, $standing));
        }

        return $period;
    }

    private function rangeOverlapsProfile(BillingProfile $profile, string $periodStart, string $periodEnd): bool
    {
        $from = $profile->effective_from?->toDateString();
        $to = $profile->effective_to?->toDateString();

        if ($from !== null && $from > $periodEnd) {
            return false;
        }

        return $to === null || $to >= $periodStart;
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
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');

        $seq = 1;
        if (is_string($latest) && preg_match('/-(\d+)$/', $latest, $m)) {
            $seq = ((int) $m[1]) + 1;
        }

        return $prefix.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
