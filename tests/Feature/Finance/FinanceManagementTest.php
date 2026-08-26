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

        $this->actingAs($finance)
            ->post(route('billing.store'), [
                'client_id' => $client->id,
                'contracted_armed_guards' => 30,
                'contracted_unarmed_guards' => 35,
                'monthly_rate_per_armed_guard' => 650000,
                'monthly_rate_per_unarmed_guard' => 450000,
                'monthly_cost_per_armed_guard' => 380000,
                'monthly_cost_per_unarmed_guard' => 280000,
                'monthly_site_fee' => 1500000,
                'rate_per_armed_shift' => 0,
                'rate_per_unarmed_shift' => 0,
                'cost_per_armed_shift' => 0,
                'cost_per_unarmed_shift' => 0,
                'effective_from' => now()->startOfMonth()->toDateString(),
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('billing_profiles', [
            'client_id' => $client->id,
            'monthly_site_fee' => 1500000,
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
            ->assertSee('Profitability');
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
            'contracted_armed_guards' => 30,
            'contracted_unarmed_guards' => 0,
            'monthly_rate_per_armed_guard' => 650000,
            'monthly_rate_per_unarmed_guard' => 0,
            'monthly_cost_per_armed_guard' => 380000,
            'monthly_cost_per_unarmed_guard' => 0,
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

        $this->assertTrue(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Armed guard coverage') && str_contains($line, '30 guards')));
        $this->assertFalse(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Unarmed guard coverage')));
        $this->assertSame(19500000.0, (float) $invoice->total);
    }

    public function test_invoice_auto_generate_uses_contracted_guard_headcount(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();

        BillingProfile::query()->create([
            'client_id' => $client->id,
            'currency' => 'UGX',
            'contracted_armed_guards' => 30,
            'contracted_unarmed_guards' => 35,
            'monthly_rate_per_armed_guard' => 650000,
            'monthly_rate_per_unarmed_guard' => 450000,
            'monthly_cost_per_armed_guard' => 380000,
            'monthly_cost_per_unarmed_guard' => 280000,
            'monthly_site_fee' => 0,
            'rate_per_armed_shift' => 0,
            'rate_per_unarmed_shift' => 0,
            'rate_per_guard_shift' => 0,
            'cost_per_armed_shift' => 0,
            'cost_per_unarmed_shift' => 0,
            'cost_per_guard_shift' => 0,
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

        $this->assertTrue(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Armed guard coverage') && str_contains($line, '30 guards')));
        $this->assertTrue(collect($descriptions)->contains(fn (string $line) => str_contains($line, 'Unarmed guard coverage') && str_contains($line, '35 guards')));
        $this->assertSame(35250000.0, (float) $invoice->total);
    }

    public function test_billing_profile_accepts_unarmed_only_contract(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();

        $this->actingAs($finance)
            ->post(route('billing.store'), [
                'client_id' => $client->id,
                'contracted_unarmed_guards' => 40,
                'monthly_rate_per_unarmed_guard' => 420000,
                'monthly_cost_per_unarmed_guard' => 260000,
                'monthly_site_fee' => 0,
                'effective_from' => now()->startOfMonth()->toDateString(),
                'is_active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('billing_profiles', [
            'client_id' => $client->id,
            'contracted_armed_guards' => 0,
            'contracted_unarmed_guards' => 40,
        ]);
    }
}
