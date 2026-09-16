<?php

namespace Tests\Feature\Admin;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_manager_can_import_guards_from_csv(): void
    {
        Storage::fake('local');

        $hr = User::factory()->role(UserRole::HrManager)->create();
        $region = Region::factory()->create(['code' => 'CENTRAL']);

        $csv = $this->buildCsv($this->guardHeaders(), [[
            'Jane', '', 'Doe', 'female', '', '0700111222', '', '', now()->toDateString(),
            'active', 'training', 'CENTRAL', 'shift', '', '', '', '', '', '', '', '',
        ]]);

        $file = UploadedFile::fake()->createWithContent('guards.csv', $csv);

        $response = $this->actingAs($hr)
            ->post(route('data-import.guards'), ['file' => $file]);

        $response->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('guards', [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'region_id' => $region->id,
        ]);
    }

    public function test_ops_manager_can_import_sites_from_csv(): void
    {
        Storage::fake('local');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $client = Client::factory()->create(['name' => 'Acme Industries Ltd']);
        Region::factory()->create(['code' => 'WEST']);

        $csv = $this->buildCsv($this->siteHeaders(), [[
            'Warehouse Gate', 'WH-GATE', 'Acme Industries Ltd', 'WEST', '', 'Plot 5',
            '2', '1', 'active', '', '', '', '', '',
        ]]);

        $file = UploadedFile::fake()->createWithContent('sites.csv', $csv);

        $this->actingAs($ops)
            ->post(route('data-import.sites'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('sites', [
            'code' => 'WH-GATE',
            'client_id' => $client->id,
            'required_day_guards' => 2,
            'required_night_guards' => 1,
        ]);
    }

    public function test_finance_manager_can_import_opening_invoice_balance(): void
    {
        Storage::fake('local');

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create(['name' => 'Legacy Client Ltd']);

        $csv = $this->buildCsv($this->openingBalanceHeaders(), [[
            'client_invoice', 'Legacy Client Ltd', '', '', 'Opening services balance', '1500000', '500000', '', '',
            now()->addDays(7)->toDateString(), now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString(), 'Go-live',
        ]]);

        $file = UploadedFile::fake()->createWithContent('balances.csv', $csv);

        $this->actingAs($finance)
            ->post(route('data-import.opening-balances'), ['file' => $file])
            ->assertRedirect();

        $invoice = Invoice::query()->first();

        $this->assertNotNull($invoice);
        $this->assertSame(1500000.0, (float) $invoice->total);
        $this->assertSame(500000.0, (float) $invoice->amount_paid);
        $this->assertSame(1000000.0, (float) $invoice->balance);
        $this->assertSame(InvoiceStatus::PartiallyPaid, $invoice->status);
    }

    public function test_finance_manager_can_import_guard_advance_opening_balance(): void
    {
        Storage::fake('local');

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $guard = Guard::factory()->create(['employment_id' => 'PSG0099']);

        $csv = $this->buildCsv($this->openingBalanceHeaders(), [[
            'guard_advance', '', '', 'PSG0099', 'Legacy advance', '400000', '', '300000', '40000', '', '', '', '',
        ]]);

        $file = UploadedFile::fake()->createWithContent('advances.csv', $csv);

        $this->actingAs($finance)
            ->post(route('data-import.opening-balances'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('guard_salary_advances', [
            'guard_id' => $guard->id,
            'original_amount' => 400000,
            'balance_remaining' => 300000,
        ]);
    }

    public function test_accounting_export_command_writes_csv_files_when_enabled(): void
    {
        Storage::fake('local');

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();

        SystemSetting::query()->first()?->update([
            'accounting_export_enabled' => true,
            'accounting_export_path' => 'exports/accounting',
        ]);

        Invoice::query()->create([
            'reference' => 'INV-TEST-0001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total' => 100000,
            'amount_paid' => 0,
            'balance' => 100000,
        ]);

        $this->actingAs($finance);

        Artisan::call('psg:export-accounting');

        $files = Storage::disk('local')->allFiles('exports/accounting');
        $this->assertNotEmpty($files);
        $this->assertTrue(collect($files)->contains(fn (string $path) => str_contains($path, 'invoices-')));
    }

    public function test_shift_manager_cannot_access_data_import_page(): void
    {
        $shift = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($shift)
            ->get(route('data-import.index'))
            ->assertForbidden();
    }

    /** @param  list<string>  $headers  @param  list<list<string>>  $rows */
    private function buildCsv(array $headers, array $rows): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);

        return $csv;
    }

    /** @return list<string> */
    private function guardHeaders(): array
    {
        return [
            'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth', 'phone', 'email',
            'national_id', 'date_employed', 'employment_status', 'operational_status', 'region_code',
            'compensation_type', 'rank_designation', 'base_shift_rate', 'overtime_shift_rate', 'bank_name',
            'bank_account', 'nssf_number', 'emergency_contact_name', 'emergency_contact_phone', 'notes',
        ];
    }

    /** @return list<string> */
    private function siteHeaders(): array
    {
        return [
            'name', 'code', 'client_name', 'region_code', 'supervisor_code', 'physical_location',
            'required_day_guards', 'required_night_guards', 'status', 'contract_start_date',
            'contract_end_date', 'site_contact_person', 'site_contact_phone', 'notes',
        ];
    }

    /** @return list<string> */
    private function openingBalanceHeaders(): array
    {
        return [
            'record_type', 'client_name', 'site_code', 'employment_id', 'description', 'total_amount',
            'amount_paid', 'balance_remaining', 'monthly_installment', 'due_date', 'period_start',
            'period_end', 'notes',
        ];
    }
}
