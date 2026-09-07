<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_header_includes_live_search_for_all_roles(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('id="global-search"', false)
                ->assertSee('aria-label="Search"', false)
                ->assertDontSee('>Search system<', false)
                ->assertSee('globalSearch', false)
                ->assertSee('/search', false);
        }
    }

    public function test_search_returns_matching_organization_records(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $region = Region::factory()->create(['name' => 'Nairobi Metro', 'code' => 'NBO']);
        $supervisor = Supervisor::factory()->create([
            'name' => 'Jane Supervisor',
            'supervisor_code' => 'SUP0099',
            'region_id' => $region->id,
        ]);
        $client = Client::factory()->create(['name' => 'Acme Holdings']);
        $site = Site::factory()->create([
            'name' => 'Westgate Post',
            'code' => 'WG01',
            'client_id' => $client->id,
            'region_id' => $region->id,
            'supervisor_id' => $supervisor->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'Westgate']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'site',
                'title' => 'Westgate Post',
                'url' => route('sites.show', $site),
            ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'Acme']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'client',
                'title' => 'Acme Holdings',
            ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'Nairobi']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'region',
                'title' => 'Nairobi Metro',
            ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'SUP0099']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'supervisor',
                'title' => 'Jane Supervisor',
            ]);
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson(route('search', ['q' => 'test']))
            ->assertUnauthorized();
    }

    public function test_search_returns_invoices_payroll_and_incidents(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $client = Client::factory()->create(['name' => 'Harbor Retail Group']);
        $site = Site::factory()->create(['name' => 'Harbor HQ', 'client_id' => $client->id]);

        $invoice = \App\Models\Invoice::query()->create([
            'reference' => 'INV-SEARCH-9001',
            'client_id' => $client->id,
            'status' => \App\Enums\InvoiceStatus::Issued,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 1000,
            'tax_amount' => 0,
            'total' => 1000,
            'amount_paid' => 0,
            'balance' => 1000,
        ]);

        $payroll = \App\Models\PayrollRun::query()->create([
            'reference' => 'PAY-SEARCH-42',
            'period_year' => (int) now()->year,
            'period_month' => (int) now()->month,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'status' => \App\Enums\PayrollRunStatus::Draft,
            'currency' => 'UGX',
            'guard_count' => 0,
        ]);

        $incident = \App\Models\Incident::query()->create([
            'reference' => 'OB-SEARCH-77',
            'title' => 'Perimeter breach reported',
            'description' => 'Test incident for search',
            'site_id' => $site->id,
            'incident_type' => \App\Enums\IncidentType::Theft->value,
            'severity' => \App\Enums\IncidentSeverity::Medium->value,
            'status' => \App\Enums\IncidentStatus::Reported->value,
            'occurred_at' => now(),
            'reported_at' => now(),
            'reported_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'INV-SEARCH']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'invoice',
                'title' => 'INV-SEARCH-9001',
                'url' => route('invoices.show', $invoice),
            ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'PAY-SEARCH']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'payroll',
                'title' => 'PAY-SEARCH-42',
                'url' => route('payroll.show', $payroll),
            ]);

        $this->actingAs($admin)
            ->getJson(route('search', ['q' => 'Perimeter']))
            ->assertOk()
            ->assertJsonFragment([
                'type' => 'incident',
                'title' => 'Perimeter breach reported',
                'url' => route('incidents.show', $incident),
            ]);
    }

    public function test_finance_manager_can_open_advances_hub(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->get(route('advances.index'))
            ->assertOk()
            ->assertSee('Salary advances')
            ->assertSee('Open balance');
    }
}
