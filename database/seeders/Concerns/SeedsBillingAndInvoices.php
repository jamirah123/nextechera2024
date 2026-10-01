<?php

namespace Database\Seeders\Concerns;

use App\Enums\BillingMode;
use App\Enums\GlJournalSource;
use App\Enums\PaymentMethod;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\GlJournal;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Site;
use App\Services\Finance\BillingService;
use App\Services\Finance\InvoiceService;
use App\Services\Finance\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Shared seed logic so billing profiles and invoices stay aligned with InvoiceService.
 */
trait SeedsBillingAndInvoices
{
    /**
     * Upsert one active site-scoped billing profile per site using BillingService
     * (contracted posts come from site manpower unless the recipe overrides them).
     */
    protected function seedRealisticBillingProfiles(Carbon $effectiveFrom): void
    {
        $billing = app(BillingService::class);

        Site::query()->with('client')->orderBy('id')->each(function (Site $site) use ($billing, $effectiveFrom): void {
            if ($site->client_id === null) {
                return;
            }

            $recipe = $this->billingRecipeFor($site);
            $this->applyArmedManpowerOverrides($site, $recipe);

            $payload = [
                'client_id' => $site->client_id,
                'site_id' => $site->id,
                'billing_mode' => $recipe['billing_mode'],
                'cash_no_tax' => $recipe['cash_no_tax'],
                'monthly_rate_per_unarmed_guard' => $recipe['monthly_unarmed'],
                'monthly_rate_per_armed_guard' => $recipe['monthly_armed'],
                'monthly_rate_per_unarmed_day_guard' => $recipe['monthly_unarmed'],
                'monthly_rate_per_unarmed_night_guard' => $recipe['monthly_unarmed'],
                'monthly_rate_per_armed_day_guard' => $recipe['monthly_armed'],
                'monthly_rate_per_armed_night_guard' => $recipe['monthly_armed'],
                'rate_per_unarmed_day_shift' => $recipe['shift_unarmed_day'],
                'rate_per_unarmed_night_shift' => $recipe['shift_unarmed_night'],
                'rate_per_armed_day_shift' => $recipe['shift_armed_day'],
                'rate_per_armed_night_shift' => $recipe['shift_armed_night'],
                'rate_per_unarmed_shift' => max($recipe['shift_unarmed_day'], $recipe['shift_unarmed_night']),
                'rate_per_armed_shift' => max($recipe['shift_armed_day'], $recipe['shift_armed_night']),
                'monthly_cost_per_unarmed_guard' => (float) round($recipe['monthly_unarmed'] * 0.55, 2),
                'monthly_cost_per_armed_guard' => (float) round($recipe['monthly_armed'] * 0.55, 2),
                'cost_per_unarmed_shift' => (float) round($recipe['shift_unarmed_day'] * 0.55, 2),
                'cost_per_armed_shift' => (float) round($recipe['shift_armed_day'] * 0.55, 2),
                'effective_from' => $effectiveFrom->toDateString(),
                'effective_to' => null,
                'is_active' => true,
                'notes' => $recipe['notes'],
            ];

            if (isset($recipe['contracted_day_armed_guards'])) {
                $payload['contracted_day_armed_guards'] = $recipe['contracted_day_armed_guards'];
                $payload['contracted_day_unarmed_guards'] = $recipe['contracted_day_unarmed_guards'];
                $payload['contracted_night_armed_guards'] = $recipe['contracted_night_armed_guards'];
                $payload['contracted_night_unarmed_guards'] = $recipe['contracted_night_unarmed_guards'];
            }

            $existing = BillingProfile::query()
                ->where('client_id', $site->client_id)
                ->where('site_id', $site->id)
                ->where('is_active', true)
                ->orderByDesc('effective_from')
                ->orderByDesc('id')
                ->first();

            try {
                if ($existing) {
                    $billing->update($existing, $payload);
                } else {
                    $billing->create($payload);
                }
            } catch (Throwable $e) {
                $this->command?->warn('Billing profile skipped for site '.$site->code.': '.$e->getMessage());
            }
        });
    }

    /**
     * Wipe seeded AR documents, then recreate every invoice from InvoiceService::suggestLines /
     * createDraft(auto_generate) so lines always match the active billing profiles.
     */
    protected function seedInvoicesFromBillingProfiles(Carbon $from, Carbon $to): void
    {
        $this->purgeSeededAccountsReceivable();

        $invoices = app(InvoiceService::class);
        $payments = app(PaymentService::class);
        $vatRate = (float) config('psg.vat_rate', 18);

        $month = $from->copy()->startOfMonth();
        $lastInvoiceMonth = $to->copy()->startOfMonth()->subMonth();

        while ($month->lte($lastInvoiceMonth)) {
            $periodStart = $month->copy()->startOfMonth()->toDateString();
            $periodEnd = $month->copy()->endOfMonth()->toDateString();

            Client::query()->orderBy('id')->each(function (Client $client) use (
                $invoices,
                $payments,
                $periodStart,
                $periodEnd,
                $month,
                $vatRate,
                $to,
            ): void {
                $hasProfile = BillingProfile::query()
                    ->active()
                    ->where('client_id', $client->id)
                    ->whereDate('effective_from', '<=', $periodEnd)
                    ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart))
                    ->exists();

                if (! $hasProfile) {
                    return;
                }

                try {
                    $suggested = $invoices->suggestLines($client->id, null, $periodStart, $periodEnd);
                    if ($suggested === []) {
                        $this->command?->warn(
                            "Invoice seed skipped for {$client->name} {$month->format('Y-m')}: no billable lines from billing profiles."
                        );

                        return;
                    }

                    $subtotal = round(array_sum(array_map(
                        static fn (array $line): float => (float) $line['quantity'] * (float) $line['unit_price'],
                        $suggested,
                    )), 2);

                    $cashNoTax = BillingProfile::query()
                        ->active()
                        ->where('client_id', $client->id)
                        ->whereDate('effective_from', '<=', $periodEnd)
                        ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodStart))
                        ->get()
                        ->contains(fn (BillingProfile $profile) => $profile->cash_no_tax);

                    $taxAmount = $cashNoTax ? 0.0 : round($subtotal * ($vatRate / 100), 2);

                    $invoice = $invoices->createDraft([
                        'client_id' => $client->id,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'tax_amount' => $taxAmount,
                        'auto_generate' => true,
                        'notes' => $cashNoTax
                            ? 'Settled on a cash basis — no VAT charged. Security services for '.$month->format('F Y').'.'
                            : 'Security services for '.$month->format('F Y').'.',
                    ]);

                    if ((float) $invoice->total <= 0) {
                        return;
                    }

                    // Issue as of period end so due date stays after issue date.
                    $invoice = $invoices->issue($invoice, $periodEnd);

                    // Pay most months in full; leave February open for AR dashboards.
                    if ((int) $month->month !== 2) {
                        $invoice = $invoice->fresh();
                        $payments->record([
                            'invoice_id' => $invoice->id,
                            'amount' => (float) ($invoice->balance > 0 ? $invoice->balance : $invoice->total),
                            'payment_date' => Carbon::parse($periodEnd)->addDays(7)->min($to)->toDateString(),
                            'method' => PaymentMethod::BankTransfer->value,
                            'external_reference' => 'SEED-'.$invoice->reference,
                            'notes' => 'Seed payment aligned to billing-profile invoice',
                        ]);
                    }
                } catch (Throwable $e) {
                    $this->command?->warn(
                        "Invoice seed skipped for client #{$client->id} {$month->format('Y-m')}: ".$e->getMessage()
                    );
                }
            });

            $month->addMonth();
        }
    }

    /**
     * @return array{
     *     billing_mode: string,
     *     cash_no_tax: bool,
     *     monthly_unarmed: float,
     *     monthly_armed: float,
     *     shift_unarmed_day: float,
     *     shift_unarmed_night: float,
     *     shift_armed_day: float,
     *     shift_armed_night: float,
     *     notes: string,
     *     contracted_day_armed_guards?: int,
     *     contracted_day_unarmed_guards?: int,
     *     contracted_night_armed_guards?: int,
     *     contracted_night_unarmed_guards?: int,
     *     desired_day_armed?: int,
     *     desired_night_armed?: int
     * }
     */
    private function billingRecipeFor(Site $site): array
    {
        $defaults = [
            'billing_mode' => BillingMode::Monthly->value,
            'cash_no_tax' => false,
            'monthly_unarmed' => 450_000.0,
            'monthly_armed' => 650_000.0,
            'shift_unarmed_day' => 35_000.0,
            'shift_unarmed_night' => 40_000.0,
            'shift_armed_day' => 48_000.0,
            'shift_armed_night' => 55_000.0,
            'notes' => 'Seeded monthly billing profile for '.$site->code.'.',
        ];

        $recipe = match ($site->code) {
            'KLA-WH1' => [
                ...$defaults,
                'billing_mode' => BillingMode::Hybrid->value,
                'shift_unarmed_day' => 36_000.0,
                'shift_unarmed_night' => 41_000.0,
                'shift_armed_day' => 49_000.0,
                'shift_armed_night' => 56_000.0,
                'notes' => 'Alpha Warehouse — monthly posts plus overtime/special duty.',
            ],
            'KLA-PZ1' => [
                ...$defaults,
                'notes' => 'Pearl Plaza — premium monthly plaza rates.',
            ],
            'WES-GS1' => [
                ...$defaults,
                'notes' => 'Mbarara Grain — Western regional monthly rates.',
            ],
            'WES-CL1' => [
                ...$defaults,
                'billing_mode' => BillingMode::Hybrid->value,
                'shift_unarmed_day' => 36_000.0,
                'shift_unarmed_night' => 42_000.0,
                'shift_armed_day' => 50_000.0,
                'shift_armed_night' => 58_000.0,
                'notes' => 'Fort Portal Clinic — monthly posts plus overtime/special duty.',
            ],
            'KLA-EH1' => [
                ...$defaults,
                'billing_mode' => BillingMode::Hybrid->value,
                'cash_no_tax' => true,
                'shift_unarmed_day' => 38_000.0,
                'shift_unarmed_night' => 44_000.0,
                'shift_armed_day' => 52_000.0,
                'shift_armed_night' => 60_000.0,
                'notes' => 'Entebbe Logistics — monthly posts plus overtime/special duty; cash / no VAT.',
            ],
            'KLA-KR1' => [
                ...$defaults,
                'notes' => 'Kololo Residences — residential monthly post rates.',
            ],
            'KLA-NP1' => [
                ...$defaults,
                'desired_day_armed' => 1,
                'notes' => 'Nakawa East — monthly posts with one armed day post.',
            ],
            'KLA-NP2' => [
                ...$defaults,
                'notes' => 'Nakawa West — monthly industrial gate rates.',
            ],
            'WES-KM1' => [
                ...$defaults,
                'billing_mode' => BillingMode::Hybrid->value,
                'desired_night_armed' => 1,
                'shift_unarmed_day' => 34_000.0,
                'shift_unarmed_night' => 39_000.0,
                'shift_armed_day' => 47_000.0,
                'shift_armed_night' => 54_000.0,
                'notes' => 'Kasese Mining — monthly posts plus extra duties; one armed night post.',
            ],
            'WES-BT1' => [
                ...$defaults,
                'notes' => 'Bushenyi Tea — estate monthly post rates.',
            ],
            'WES-HO1' => [
                ...$defaults,
                'notes' => 'Hoima Camp — oilfield premium monthly rates.',
            ],
            'WES-HO2' => [
                ...$defaults,
                'billing_mode' => BillingMode::Hybrid->value,
                'shift_unarmed_day' => 37_000.0,
                'shift_unarmed_night' => 43_000.0,
                'shift_armed_day' => 51_000.0,
                'shift_armed_night' => 59_000.0,
                'notes' => 'Hoima Yard — monthly posts plus overtime/special duty.',
            ],
            default => $defaults,
        };

        $recipe['monthly_unarmed'] = 450_000.0;
        $recipe['monthly_armed'] = 650_000.0;

        return $recipe;
    }

    /**
     * @param  array<string, mixed>  $recipe
     */
    private function applyArmedManpowerOverrides(Site $site, array &$recipe): void
    {
        $dayTotal = (int) $site->required_day_armed_guards + (int) $site->required_day_unarmed_guards;
        $nightTotal = (int) $site->required_night_armed_guards + (int) $site->required_night_unarmed_guards;

        // Absolute targets keep re-seeds idempotent (do not keep converting unarmed → armed).
        $dayArmed = array_key_exists('desired_day_armed', $recipe)
            ? min((int) $recipe['desired_day_armed'], $dayTotal)
            : (int) $site->required_day_armed_guards;
        $nightArmed = array_key_exists('desired_night_armed', $recipe)
            ? min((int) $recipe['desired_night_armed'], $nightTotal)
            : (int) $site->required_night_armed_guards;

        $dayUnarmed = max(0, $dayTotal - $dayArmed);
        $nightUnarmed = max(0, $nightTotal - $nightArmed);

        if (
            $dayArmed !== (int) $site->required_day_armed_guards
            || $dayUnarmed !== (int) $site->required_day_unarmed_guards
            || $nightArmed !== (int) $site->required_night_armed_guards
            || $nightUnarmed !== (int) $site->required_night_unarmed_guards
        ) {
            $site->update([
                'required_day_armed_guards' => $dayArmed,
                'required_day_unarmed_guards' => $dayUnarmed,
                'required_night_armed_guards' => $nightArmed,
                'required_night_unarmed_guards' => $nightUnarmed,
                'required_day_guards' => $dayArmed + $dayUnarmed,
                'required_night_guards' => $nightArmed + $nightUnarmed,
                'required_guards' => $dayArmed + $dayUnarmed + $nightArmed + $nightUnarmed,
            ]);
            $site->refresh();
        }

        $recipe['contracted_day_armed_guards'] = (int) $site->required_day_armed_guards;
        $recipe['contracted_day_unarmed_guards'] = (int) $site->required_day_unarmed_guards;
        $recipe['contracted_night_armed_guards'] = (int) $site->required_night_armed_guards;
        $recipe['contracted_night_unarmed_guards'] = (int) $site->required_night_unarmed_guards;
    }

    private function purgeSeededAccountsReceivable(): void
    {
        DB::transaction(function (): void {
            $payments = Payment::query()
                ->whereNotNull('invoice_id')
                ->orderBy('id')
                ->get();

            foreach ($payments as $payment) {
                $this->deleteJournalsFor(GlJournalSource::Payment, $payment);
                $payment->delete();
            }

            Invoice::withTrashed()->orderBy('id')->each(function (Invoice $invoice): void {
                $this->deleteJournalsFor(GlJournalSource::Invoice, $invoice);
                $invoice->lines()->delete();
                $invoice->forceDelete();
            });
        });
    }

    private function deleteJournalsFor(GlJournalSource $source, Invoice|Payment $document): void
    {
        GlJournal::query()
            ->where('source', $source->value)
            ->where('source_document_type', $document::class)
            ->where('source_document_id', $document->id)
            ->each(function (GlJournal $journal): void {
                // Clear FK references that would block deletes.
                GlJournal::query()
                    ->where('reversal_of_id', $journal->id)
                    ->update(['reversal_of_id' => null]);

                if (DB::getSchemaBuilder()->hasTable('bank_statement_lines')) {
                    DB::table('bank_statement_lines')
                        ->where('matched_journal_id', $journal->id)
                        ->update(['matched_journal_id' => null]);
                }

                $journal->delete();
            });
    }
}
