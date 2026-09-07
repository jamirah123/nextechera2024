<?php

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_manager_can_create_billing_profile_and_invoice_cycle(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        \App\Models\Site::factory()->create([
            'client_id' => $client->id,
            'required_day_armed_guards' => 10,
            'required_day_unarmed_guards' => 20,
            'required_night_armed_guards' => 15,
            'required_night_unarmed_guards' => 20,
            'required_day_guards' => 30,
            'required_night_guards' => 35,
            'required_guards' => 65,
            'number_of_posts' => 35,
        ]);

        $this->actingAs($finance)
            ->post(route('billing.store'), [
                'client_id' => $client->id,
                'billing_mode' => 'monthly',
                'monthly_rate_per_armed_guard' => 650000,
                'monthly_rate_per_unarmed_guard' => 450000,
                'monthly_site_fee' => 0,
                'effective_from' => now()->startOfMonth()->toDateString(),
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('billing_profiles', [
            'client_id' => $client->id,
            'contracted_day_armed_guards' => 10,
            'contracted_day_unarmed_guards' => 20,
            'contracted_night_armed_guards' => 15,
            'contracted_night_unarmed_guards' => 20,
            'monthly_site_fee' => 0,
        ]);

        $this->actingAs($finance)
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'due_date' => now()->addDays(20)->toDateString(),
                'tax_amount' => 0,
                'auto_generate' => true,
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('client_id', $client->id)->first();
        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertGreaterThan(0, (float) $invoice->total);

        $this->actingAs($finance)
            ->post(route('invoices.issue', $invoice))
            ->assertRedirect();

        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);

        $this->actingAs($finance)
            ->post(route('payments.store'), [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->fresh()->total,
                'payment_date' => now()->toDateString(),
                'method' => 'bank_transfer',
            ])
            ->assertRedirect();

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
        $this->assertSame(0.0, (float) $invoice->fresh()->balance);
    }

    public function test_shift_manager_cannot_manage_finance(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($manager)
            ->get(route('billing.create'))
            ->assertForbidden();

        $this->actingAs($manager)
            ->get(route('invoices.index'))
            ->assertForbidden();
    }

    public function test_operations_can_view_but_not_create_invoices(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($ops)
            ->get(route('invoices.index'))
            ->assertOk();

        $this->actingAs($ops)
            ->get(route('invoices.create'))
            ->assertForbidden();
    }

    public function test_profitability_page_loads_for_finance(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->get(route('profitability.index'))
            ->assertOk()
            ->assertSee('Profitability')
            ->assertSee('By client');
    }

    public function test_finance_exports_are_available(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->get(route('invoices.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($finance)
            ->get(route('billing.export'))
            ->assertOk();

        $this->actingAs($finance)
            ->get(route('payments.export'))
            ->assertOk();

        $this->actingAs($finance)
            ->get(route('profitability.export'))
            ->assertOk();
    }

    public function test_invoice_auto_generate_supports_armed_only_contract(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();

        BillingProfile::query()->create([
            'client_id' => $client->id,
            'currency' => 'UGX',
            'contracted_day_armed_guards' => 30,
            'contracted_day_unarmed_guards' => 0,
            'contracted_night_armed_guards' => 0,
            'contracted_night_unarmed_guards' => 0,
            'contracted_armed_guards' => 30,
            'contracted_unarmed_guards' => 0,
            'monthly_rate_per_armed_guard' => 650000,
            'monthly_rate_per_unarmed_guard' => 0,
            'monthly_rate_per_armed_day_guard' => 650000,
            'monthly_rate_per_unarmed_day_guard' => 0,
            'monthly_rate_per_armed_night_guard' => 650000,
            'monthly_rate_per_unarmed_night_guard' => 0,
            'monthly_site_fee' => 0,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($finance)
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'auto_generate' => true,
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('client_id', $client->id)->firstOrFail();
        $descriptions = $invoice->lines()->pluck('description')->all();

        $this->assertTrue(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Day armed security posts') && str_contains($line, '30 posts')));
        $this->assertFalse(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Night')));
        $this->assertSame(19500000.0, (float) $invoice->total);
    }

    public function test_invoice_auto_generate_uses_contracted_guard_headcount(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();

        BillingProfile::query()->create([
            'client_id' => $client->id,
            'currency' => 'UGX',
            'billing_mode' => 'monthly',
            'contracted_day_armed_guards' => 10,
            'contracted_day_unarmed_guards' => 20,
            'contracted_night_armed_guards' => 5,
            'contracted_night_unarmed_guards' => 15,
            'contracted_armed_guards' => 15,
            'contracted_unarmed_guards' => 35,
            'monthly_rate_per_armed_guard' => 650000,
            'monthly_rate_per_unarmed_guard' => 450000,
            'monthly_rate_per_armed_day_guard' => 650000,
            'monthly_rate_per_unarmed_day_guard' => 450000,
            'monthly_rate_per_armed_night_guard' => 650000,
            'monthly_rate_per_unarmed_night_guard' => 450000,
            'monthly_site_fee' => 0,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
        ]);

        $this->actingAs($finance)
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'auto_generate' => true,
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('client_id', $client->id)->firstOrFail();
        $descriptions = $invoice->lines()->pluck('description')->all();

        $this->assertTrue(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Day armed security posts') && str_contains($line, '10 posts')));
        $this->assertTrue(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Day unarmed security posts') && str_contains($line, '20 posts')));
        $this->assertTrue(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Night armed security posts') && str_contains($line, '5 posts')));
        $this->assertTrue(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Night unarmed security posts') && str_contains($line, '15 posts')));
        // (10+5)*650000 + (20+15)*450000 = 9750000 + 15750000 = 25500000
        $this->assertSame(25500000.0, (float) $invoice->total);
    }

    public function test_billing_profile_accepts_night_only_manpower(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        \App\Models\Site::factory()->create([
            'client_id' => $client->id,
            'required_day_armed_guards' => 0,
            'required_day_unarmed_guards' => 0,
            'required_night_armed_guards' => 10,
            'required_night_unarmed_guards' => 30,
            'required_day_guards' => 0,
            'required_night_guards' => 40,
            'required_guards' => 40,
            'number_of_posts' => 40,
        ]);

        $this->actingAs($finance)
            ->post(route('billing.store'), [
                'client_id' => $client->id,
                'billing_mode' => 'monthly',
                'monthly_rate_per_armed_guard' => 420000,
                'monthly_rate_per_unarmed_guard' => 400000,
                'monthly_site_fee' => 0,
                'effective_from' => now()->startOfMonth()->toDateString(),
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('billing_profiles', [
            'client_id' => $client->id,
            'contracted_day_armed_guards' => 0,
            'contracted_day_unarmed_guards' => 0,
            'contracted_night_armed_guards' => 10,
            'contracted_night_unarmed_guards' => 30,
        ]);
    }

    public function test_per_shift_billing_generates_lines_from_completed_shifts(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();
        $site = \App\Models\Site::factory()->create(['client_id' => $client->id]);
        $guard = \App\Models\Guard::factory()->create();

        BillingProfile::query()->create([
            'client_id' => $client->id,
            'site_id' => $site->id,
            'currency' => 'UGX',
            'billing_mode' => 'per_shift',
            'cash_no_tax' => true,
            'monthly_site_fee' => 0,
            'rate_per_armed_day_shift' => 50000,
            'rate_per_armed_night_shift' => 60000,
            'rate_per_unarmed_day_shift' => 40000,
            'rate_per_unarmed_night_shift' => 45000,
            'effective_from' => now()->startOfMonth()->toDateString(),
            'is_active' => true,
        ]);

        \App\Models\Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => now()->startOfMonth()->toDateString(),
            'period' => \App\Enums\ShiftPeriod::Day,
            'guard_classification' => \App\Enums\GuardClassification::Armed,
            'status' => \App\Enums\ShiftStatus::Completed,
            'shift_type' => \App\Enums\ShiftType::Normal,
        ]);

        \App\Models\Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => now()->startOfMonth()->addDay()->toDateString(),
            'period' => \App\Enums\ShiftPeriod::Night,
            'guard_classification' => \App\Enums\GuardClassification::Armed,
            'status' => \App\Enums\ShiftStatus::Completed,
            'shift_type' => \App\Enums\ShiftType::Normal,
            'is_overnight' => true,
        ]);

        $this->actingAs($finance)
            ->post(route('invoices.store'), [
                'client_id' => $client->id,
                'site_id' => $site->id,
                'period_start' => now()->startOfMonth()->toDateString(),
                'period_end' => now()->endOfMonth()->toDateString(),
                'auto_generate' => true,
            ])
            ->assertRedirect();

        $invoice = Invoice::query()->where('client_id', $client->id)->firstOrFail();
        $this->assertSame(0.0, (float) $invoice->tax_amount);
        $this->assertSame(110000.0, (float) $invoice->total);
        $this->assertStringContainsString('cash basis', strtolower((string) $invoice->notes));
        $this->assertTrue($invoice->lines()->where('description', 'like', '%Day Armed shifts%')->exists());
        $this->assertTrue($invoice->lines()->where('description', 'like', '%Night Armed shifts%')->exists());
    }

    public function test_billing_service_copies_site_manpower_when_day_night_counts_omitted(): void
    {
        $client = Client::factory()->create();
        $site = \App\Models\Site::factory()->create([
            'client_id' => $client->id,
            'required_day_armed_guards' => 4,
            'required_day_unarmed_guards' => 6,
            'required_night_armed_guards' => 2,
            'required_night_unarmed_guards' => 8,
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);

        $profile = app(\App\Services\Finance\BillingService::class)->create([
            'client_id' => $client->id,
            'site_id' => $site->id,
            'monthly_rate_per_armed_guard' => 900000,
            'monthly_rate_per_unarmed_guard' => 500000,
            'effective_from' => now()->toDateString(),
            'is_active' => true,
        ]);

        $this->assertSame(4, (int) $profile->contracted_day_armed_guards);
        $this->assertSame(6, (int) $profile->contracted_day_unarmed_guards);
        $this->assertSame(2, (int) $profile->contracted_night_armed_guards);
        $this->assertSame(8, (int) $profile->contracted_night_unarmed_guards);
        $this->assertGreaterThan(0, $profile->estimatedMonthlyTotal());
    }
}
