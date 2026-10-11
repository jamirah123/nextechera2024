<?php

namespace Tests\Feature\Finance;

use App\Enums\BillingMode;
use App\Enums\GlJournalSource;
use App\Enums\GlJournalStatus;
use App\Enums\GuardClassification;
use App\Enums\InvoiceStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Enums\VatTreatment;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\GlJournal;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileDrivenInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_generates_reviews_and_issues_from_a_billing_profile(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $client = Client::factory()->create(['name' => 'Nakasero Office Complex']);
        $profile = $this->monthlyProfile($client, 1000000, VatTreatment::Standard);

        $this->actingAs($ops)
            ->get(route('invoices.create'))
            ->assertForbidden();

        $this->actingAs($finance)
            ->get(route('invoices.create'))
            ->assertOk()
            ->assertSee('Generate draft')
            ->assertSee($client->name)
            ->assertSee('All sites')
            ->assertSee('Current')
            ->assertDontSee('name="tax_amount"', false)
            ->assertDontSee('name="client_id"', false)
            ->assertSee('Due date')
            ->assertDontSee('name="period_start"', false)
            ->assertDontSee('name="period_end"', false);

        $start = now()->startOfMonth()->toDateString();
        $end = now()->endOfMonth()->toDateString();

        $this->actingAs($finance)
            ->post(route('invoices.store'), [
                'billing_profile_id' => $profile->id,
                'period_start' => $start,
                'period_end' => $end,
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame($profile->id, $invoice->billing_profile_id);
        $this->assertSame($client->id, $invoice->client_id);
        $this->assertSame(VatTreatment::Standard, $invoice->vat_treatment);
        $this->assertSame(1000000.0, (float) $invoice->subtotal);
        $this->assertSame(180000.0, (float) $invoice->tax_amount);
        $this->assertSame(1180000.0, (float) $invoice->total);
        $this->assertSame(1180000.0, (float) $invoice->balance);
        $this->assertSame(now()->endOfMonth()->addDays(14)->toDateString(), $invoice->due_date->toDateString());
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d{4}$/', $invoice->reference);
        $this->assertNotNull($invoice->calculation_snapshot);

        $this->actingAs($finance)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee($invoice->reference)
            ->assertSee('Standard-rated')
            ->assertSee('Issue invoice')
            ->assertSee('Balance due');

        $this->actingAs($finance)
            ->put(route('invoices.update', $invoice), [
                'due_date' => $invoice->due_date->toDateString(),
                'notes' => 'Reviewed by finance.',
                'lines' => [[
                    'description' => 'Day armed security posts — adjusted',
                    'quantity' => 1,
                    'unit_price' => 500000,
                ]],
            ])
            ->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(500000.0, (float) $invoice->subtotal);
        $this->assertSame(90000.0, (float) $invoice->tax_amount);
        $this->assertSame('Reviewed by finance.', $invoice->notes);

        $this->actingAs($ops)
            ->post(route('invoices.issue', $invoice))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->actingAs($finance)
            ->post(route('invoices.issue', $invoice))
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->issued_snapshot);
        $this->assertSame(590000.0, (float) $invoice->issued_snapshot['total']);

        $journal = GlJournal::query()
            ->where('source', GlJournalSource::Invoice->value)
            ->where('source_document_id', $invoice->id)
            ->first();
        $this->assertNotNull($journal);
        $this->assertTrue($journal->isBalanced());
        $this->assertSame(GlJournalStatus::Posted, $journal->status);

        $this->actingAs($finance)
            ->post(route('invoices.issue', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertSame(1, GlJournal::query()->where('source', GlJournalSource::Invoice->value)->where('source_document_id', $invoice->id)->count());

        $this->actingAs($ops)
            ->get(route('invoices.pdf', $invoice))
            ->assertOk();

        $this->actingAs($finance)
            ->get(route('invoices.export-document', $invoice))
            ->assertOk();

        $this->actingAs($finance)
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => 100000,
                'payment_date' => now()->toDateString(),
                'method' => 'cash',
            ])
            ->assertRedirect();

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
        $this->assertSame(100000.0, (float) $invoice->amount_paid);
        $this->assertSame(490000.0, (float) $invoice->balance);
    }

    public function test_cash_terms_do_not_remove_vat_and_exempt_profiles_do(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        $cash = $this->monthlyProfile($client, 200000, VatTreatment::Standard, cash: true);

        $this->actingAs($finance)
            ->post(route('invoices.store'), $this->periodPayload($cash))
            ->assertRedirect();

        $cashInvoice = Invoice::query()->firstOrFail();
        $this->assertSame(VatTreatment::Standard, $cashInvoice->vat_treatment);
        $this->assertSame(36000.0, (float) $cashInvoice->tax_amount);
        $this->assertStringContainsString('cash settlement', strtolower((string) $cashInvoice->notes));
        $this->assertStringNotContainsString('no vat', strtolower((string) $cashInvoice->notes));

        $exemptClient = Client::factory()->create();
        $exempt = $this->monthlyProfile($exemptClient, 200000, VatTreatment::Exempt, cash: true);

        $this->actingAs($finance)
            ->post(route('invoices.store'), $this->periodPayload($exempt))
            ->assertRedirect();

        $exemptInvoice = Invoice::query()->where('client_id', $exemptClient->id)->firstOrFail();
        $this->assertSame(VatTreatment::Exempt, $exemptInvoice->vat_treatment);
        $this->assertSame(0.0, (float) $exemptInvoice->tax_amount);
        $this->assertSame(200000.0, (float) $exemptInvoice->total);

        $zeroClient = Client::factory()->create();
        $zero = $this->monthlyProfile($zeroClient, 200000, VatTreatment::ZeroRated);

        $this->actingAs($finance)
            ->post(route('invoices.store'), $this->periodPayload($zero))
            ->assertRedirect();

        $zeroInvoice = Invoice::query()->where('client_id', $zeroClient->id)->firstOrFail();
        $this->assertSame(VatTreatment::ZeroRated, $zeroInvoice->vat_treatment);
        $this->assertSame(0.0, (float) $zeroInvoice->tax_amount);
        $this->assertSame(200000.0, (float) $zeroInvoice->total);
    }

    public function test_a_rate_change_inside_the_period_is_prorated_and_a_tax_change_is_rejected(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        $start = now()->startOfMonth();
        $mid = $start->copy()->addDays(14);
        $end = now()->endOfMonth();
        $monthDays = $start->daysInMonth;
        $firstDays = 15;
        $secondDays = $monthDays - 15;

        $first = $this->monthlyProfile($client, 310000, VatTreatment::Standard, from: $start->toDateString(), to: $mid->toDateString());
        $this->monthlyProfile($client, 620000, VatTreatment::Standard, from: $mid->copy()->addDay()->toDateString(), to: $end->toDateString());

        $this->actingAs($finance)
            ->post(route('invoices.store'), [
                'billing_profile_id' => $first->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->firstOrFail();
        $expected = round(310000 * $firstDays / $monthDays, 2) + round(620000 * $secondDays / $monthDays, 2);
        $this->assertSame($expected, (float) $invoice->subtotal);
        $this->assertCount(2, $invoice->lines);
        $this->assertCount(2, $invoice->calculation_snapshot['slices']);

        $splitClient = Client::factory()->create();
        $standard = $this->monthlyProfile($splitClient, 100000, VatTreatment::Standard, from: $start->toDateString(), to: $mid->toDateString());
        $this->monthlyProfile($splitClient, 100000, VatTreatment::Exempt, from: $mid->copy()->addDay()->toDateString(), to: $end->toDateString());

        $this->actingAs($finance)
            ->from(route('invoices.create'))
            ->post(route('invoices.store'), [
                'billing_profile_id' => $standard->id,
                'period_start' => $start->toDateString(),
                'period_end' => $end->toDateString(),
            ])
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('invoice');

        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_duplicate_periods_and_completed_shifts_are_rejected(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        $profile = $this->monthlyProfile($client, 100000, VatTreatment::Exempt);

        $this->actingAs($finance)->post(route('invoices.store'), $this->periodPayload($profile))->assertRedirect();

        $this->actingAs($finance)
            ->from(route('invoices.create'))
            ->post(route('invoices.store'), $this->periodPayload($profile))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('invoice');

        $this->assertSame(1, Invoice::query()->count());

        $siteClient = Client::factory()->create();
        $site = Site::factory()->create(['client_id' => $siteClient->id]);
        $guard = Guard::factory()->create();
        $shiftProfile = BillingProfile::query()->create([
            'client_id' => $siteClient->id,
            'site_id' => $site->id,
            'currency' => 'UGX',
            'billing_mode' => BillingMode::PerShift,
            'vat_treatment' => VatTreatment::Exempt,
            'rate_per_armed_day_shift' => 40000,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
        ]);
        $shift = Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => now()->startOfMonth()->toDateString(),
            'period' => ShiftPeriod::Day,
            'guard_classification' => GuardClassification::Armed,
            'status' => ShiftStatus::Completed,
            'shift_type' => ShiftType::Normal,
        ]);

        $this->actingAs($finance)->post(route('invoices.store'), $this->periodPayload($shiftProfile))->assertRedirect();
        $this->assertDatabaseHas('invoice_billed_shifts', ['shift_id' => $shift->id]);

        $this->actingAs($finance)
            ->from(route('invoices.create'))
            ->post(route('invoices.store'), $this->periodPayload($shiftProfile))
            ->assertSessionHasErrors('invoice');
    }

    public function test_a_gap_in_the_contract_does_not_create_an_invoice(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        $profile = $this->monthlyProfile(
            $client,
            100000,
            VatTreatment::Standard,
            from: now()->startOfMonth()->toDateString(),
            to: now()->startOfMonth()->addDays(4)->toDateString(),
        );

        $this->actingAs($finance)
            ->from(route('invoices.create'))
            ->post(route('invoices.store'), $this->periodPayload($profile))
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('invoice');

        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_a_single_site_invoice_does_not_block_the_all_sites_profile(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        $site = Site::factory()->create(['client_id' => $client->id]);
        $siteProfile = $this->monthlyProfile($client, 250000, VatTreatment::Exempt);
        $siteProfile->update(['site_id' => $site->id]);
        $allSites = $this->monthlyProfile($client, 800000, VatTreatment::Standard);

        $this->actingAs($finance)
            ->post(route('invoices.store'), $this->periodPayload($siteProfile))
            ->assertRedirect();

        $this->actingAs($finance)
            ->post(route('invoices.store'), ['billing_profile_id' => $allSites->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->where('billing_profile_id', $allSites->id)->firstOrFail();
        $this->assertNull($invoice->site_id);
        $this->assertSame(now()->startOfMonth()->toDateString(), $invoice->period_start->toDateString());
        $this->assertSame(now()->endOfMonth()->toDateString(), $invoice->period_end->toDateString());
        $this->assertSame(2, Invoice::query()->count());
    }

    public function test_the_service_period_comes_from_the_current_billing_profile(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        $current = $this->monthlyProfile($client, 1000000, VatTreatment::Standard);
        $periodFrom = now()->subMonth()->startOfMonth()->toDateString();
        $periodTo = now()->subMonth()->endOfMonth()->toDateString();
        $dated = $this->monthlyProfile(
            $client,
            400000,
            VatTreatment::Standard,
            from: $periodFrom,
            to: $periodTo,
        );
        $olderClient = Client::factory()->create();
        $superseded = $this->monthlyProfile(
            $olderClient,
            700000,
            VatTreatment::Standard,
            from: now()->subMonths(2)->startOfMonth()->toDateString(),
        );
        $this->monthlyProfile($olderClient, 900000, VatTreatment::Standard);

        $due = now()->endOfMonth()->addDays(21)->toDateString();

        $this->actingAs($finance)
            ->post(route('invoices.store'), [
                'billing_profile_id' => $current->id,
                'due_date' => $due,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame($current->id, $invoice->billing_profile_id);
        $this->assertSame(now()->startOfMonth()->toDateString(), $invoice->period_start->toDateString());
        $this->assertSame(now()->endOfMonth()->toDateString(), $invoice->period_end->toDateString());
        $this->assertSame($due, $invoice->due_date->toDateString());

        $this->actingAs($finance)
            ->post(route('invoices.store'), ['billing_profile_id' => $dated->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $datedInvoice = Invoice::query()->where('billing_profile_id', $dated->id)->firstOrFail();
        $this->assertNull($datedInvoice->site_id);
        $this->assertSame($periodFrom, $datedInvoice->period_start->toDateString());
        $this->assertSame($periodTo, $datedInvoice->period_end->toDateString());
        $this->assertSame(400000.0, (float) $datedInvoice->subtotal);
        $this->assertStringContainsString('all sites', (string) $datedInvoice->lines()->value('description'));

        $this->actingAs($finance)
            ->from(route('invoices.create'))
            ->post(route('invoices.store'), ['billing_profile_id' => $superseded->id])
            ->assertRedirect(route('invoices.create'))
            ->assertSessionHasErrors('invoice');

        $this->assertSame(2, Invoice::query()->count());
    }

    public function test_the_newest_billing_profile_is_listed_first_on_the_invoice_form(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $older = $this->monthlyProfile(
            Client::factory()->create(['name' => 'Older Client']),
            100000,
            VatTreatment::Standard,
            from: '2024-01-01',
        );
        $newer = $this->monthlyProfile(
            Client::factory()->create(['name' => 'Newer Client']),
            100000,
            VatTreatment::Standard,
            from: now()->startOfMonth()->toDateString(),
        );

        $html = $this->actingAs($finance)
            ->get(route('invoices.create'))
            ->assertOk()
            ->getContent();

        $olderAt = strpos($html, '\u0022id\u0022:'.$older->id);
        $newerAt = strpos($html, '\u0022id\u0022:'.$newer->id);

        $this->assertNotFalse($olderAt);
        $this->assertNotFalse($newerAt);
        $this->assertLessThan($olderAt, $newerAt);
    }

    private function monthlyProfile(
        Client $client,
        float $armedRate,
        VatTreatment $treatment,
        bool $cash = false,
        ?string $from = null,
        ?string $to = null,
    ): BillingProfile {
        return BillingProfile::query()->create([
            'client_id' => $client->id,
            'currency' => 'UGX',
            'billing_mode' => BillingMode::Monthly,
            'vat_treatment' => $treatment,
            'cash_no_tax' => $cash,
            'contracted_day_armed_guards' => 1,
            'contracted_day_unarmed_guards' => 0,
            'contracted_night_armed_guards' => 0,
            'contracted_night_unarmed_guards' => 0,
            'contracted_armed_guards' => 1,
            'monthly_rate_per_armed_guard' => $armedRate,
            'monthly_rate_per_armed_day_guard' => $armedRate,
            'monthly_rate_per_armed_night_guard' => $armedRate,
            'effective_from' => $from ?? now()->startOfMonth()->toDateString(),
            'effective_to' => $to,
            'is_active' => true,
        ]);
    }

    /** @return array{billing_profile_id: int, period_start: string, period_end: string} */
    private function periodPayload(BillingProfile $profile): array
    {
        return [
            'billing_profile_id' => $profile->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
        ];
    }
}
