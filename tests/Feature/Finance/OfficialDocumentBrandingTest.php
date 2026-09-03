<?php

namespace Tests\Feature\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficialDocumentBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_printout_includes_company_logo_and_letterhead(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create(['name' => 'Acme Security Client']);

        $invoice = Invoice::query()->create([
            'reference' => 'INV-BRAND-001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total' => 1000000,
            'amount_paid' => 0,
            'balance' => 1000000,
        ]);

        $this->actingAs($finance)
            ->get(route('invoices.show', $invoice))
            ->assertOk()
            ->assertSee('images/logo.jpeg', false)
            ->assertSee('Invoice')
            ->assertSee(config('psg.company'))
            ->assertSee('New Age Security and Protection')
            ->assertSee('Authorized signature')
            ->assertSee('Client acknowledgment');
    }

    public function test_report_printout_includes_branded_header(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($ops)
            ->get(route('reports.daily-shifts'))
            ->assertOk()
            ->assertSee('images/logo.jpeg', false)
            ->assertSee('Daily shifts report')
            ->assertSee(config('psg.company'));
    }
}
