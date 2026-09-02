<?php

namespace Tests\Feature\Documents;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\UserRole;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\DeploymentTransfer;
use App\Models\Guard;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Leave;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormalDocumentPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_user_can_download_invoice_pdf(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $client = Client::factory()->create();

        $invoice = Invoice::query()->create([
            'reference' => 'INV-PDF-001',
            'client_id' => $client->id,
            'status' => InvoiceStatus::Issued,
            'period_start' => now()->startOfMonth(),
            'period_end' => now()->endOfMonth(),
            'issue_date' => now(),
            'due_date' => now()->addDays(14),
            'currency' => 'UGX',
            'subtotal' => 500000,
            'tax_amount' => 0,
            'total' => 500000,
            'amount_paid' => 0,
            'balance' => 500000,
        ]);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Security services',
            'quantity' => 1,
            'unit_price' => 500000,
            'line_total' => 500000,
        ]);

        $response = $this->actingAs($finance)
            ->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_ops_user_can_download_deployment_letter_pdf(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $guard = Guard::factory()->create();
        $site = Site::factory()->create();

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $response = $this->actingAs($ops)
            ->get(route('deployments.letter', $deployment));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_hr_user_can_download_leave_approval_letter_pdf(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create();

        $leave = Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => LeaveType::Annual,
            'start_date' => now()->addWeek(),
            'end_date' => now()->addWeeks(2),
            'status' => LeaveStatus::Approved,
            'requested_by' => $hr->id,
            'approved_by' => $hr->id,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($hr)
            ->get(route('leaves.letter', $leave));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_hr_user_can_download_termination_letter_for_ended_employment(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Terminated,
            'employment_end_date' => now()->subDay(),
        ]);

        $response = $this->actingAs($hr)
            ->get(route('guards.termination-letter', $guard));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_transfer_letter_pdf_is_available(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $guard = Guard::factory()->create();
        $fromSite = Site::factory()->create();
        $toSite = Site::factory()->create();

        $fromDeployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $fromSite->id,
            'region_id' => $fromSite->region_id,
            'status' => DeploymentStatus::Transferred,
            'is_current' => false,
        ]);

        $toDeployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $toSite->id,
            'region_id' => $toSite->region_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $transfer = DeploymentTransfer::query()->create([
            'guard_id' => $guard->id,
            'from_deployment_id' => $fromDeployment->id,
            'to_deployment_id' => $toDeployment->id,
            'from_site_id' => $fromSite->id,
            'to_site_id' => $toSite->id,
            'reason' => 'Client request',
            'transferred_by' => $ops->id,
            'effective_at' => now(),
        ]);

        $response = $this->actingAs($ops)
            ->get(route('deployments.transfers.letter', $transfer));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
