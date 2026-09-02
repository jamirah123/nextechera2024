<?php

namespace Tests\Feature\Jobs;

use App\Enums\InvoiceStatus;
use App\Enums\UserRole;
use App\Jobs\CalculatePayrollRunJob;
use App\Jobs\ProcessCsvImportJob;
use App\Jobs\RunAccountingExportJob;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\SystemSetting;
use App\Models\User;
use App\Enums\PayrollRunStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QueueJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_calculate_dispatches_job_when_queue_is_not_sync(): void
    {
        Queue::fake();
        config(['queue.default' => 'database']);

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $run = PayrollRun::query()->create([
            'reference' => 'PAY-2026-08-001',
            'period_year' => 2026,
            'period_month' => 8,
            'period_start' => '2026-08-01',
            'period_end' => '2026-08-31',
            'status' => PayrollRunStatus::Draft,
            'currency' => 'UGX',
        ]);

        $this->actingAs($finance)
            ->post(route('payroll.calculate', $run))
            ->assertRedirect()
            ->assertSessionHas('status', 'Payroll calculation queued. Refresh this page in a moment.');

        Queue::assertPushed(CalculatePayrollRunJob::class, fn (CalculatePayrollRunJob $job) => $job->payrollRunId === $run->id);
    }

    public function test_manual_accounting_export_dispatches_job(): void
    {
        Queue::fake();

        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->post(route('data-import.accounting-export.run'))
            ->assertRedirect()
            ->assertSessionHas('status');

        Queue::assertPushed(RunAccountingExportJob::class);
    }

    public function test_csv_guard_import_dispatches_job(): void
    {
        Queue::fake();
        Storage::fake('local');

        $hr = User::factory()->role(UserRole::HrManager)->create();
        Region::factory()->create(['code' => 'CENTRAL']);

        $csv = "first_name,middle_name,last_name,gender,date_of_birth,phone,email,national_id,date_employed,employment_status,operational_status,region_code,compensation_type,base_shift_rate,monthly_gross,bank_name,bank_account,nssf_number,address,notes,rank_designation\n";
        $csv .= "Jane,,Doe,female,,0700111222,,,".now()->toDateString().",active,training,CENTRAL,shift,,,,,,,,\n";

        $file = UploadedFile::fake()->createWithContent('guards.csv', $csv);

        $this->actingAs($hr)
            ->post(route('data-import.guards'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('status');

        Queue::assertPushed(ProcessCsvImportJob::class, fn (ProcessCsvImportJob $job) => $job->type === 'guards');
    }

    public function test_queue_health_command_reports_failed_jobs(): void
    {
        $this->artisan('psg:queue-health')
            ->assertSuccessful();
    }

    public function test_scheduled_accounting_export_command_still_runs_inline(): void
    {
        Storage::fake('local');

        SystemSetting::query()->first()?->update([
            'accounting_export_enabled' => true,
            'accounting_export_path' => 'exports/accounting',
        ]);

        $client = Client::factory()->create();

        Invoice::query()->create([
            'reference' => 'INV-QUEUE-0001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 50000,
            'tax_amount' => 0,
            'total' => 50000,
            'amount_paid' => 0,
            'balance' => 50000,
        ]);

        Artisan::call('psg:export-accounting');

        $this->assertNotEmpty(Storage::disk('local')->allFiles('exports/accounting'));
    }
}
