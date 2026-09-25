<?php

namespace Tests\Feature\Compliance;

use App\Enums\ContractStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardDocumentType;
use App\Enums\UserRole;
use App\Models\BillingProfile;
use App\Models\Client;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\GuardAttachment;
use App\Models\Site;
use App\Models\User;
use App\Services\Compliance\ComplianceSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_counts_guards_with_expired_documents(): void
    {
        $guardA = Guard::factory()->create();
        $guardB = Guard::factory()->create();

        GuardAttachment::query()->create([
            'guard_id' => $guardA->id,
            'label' => 'National ID',
            'document_type' => GuardDocumentType::NationalId,
            'expires_at' => now()->subDays(5)->toDateString(),
            'original_name' => 'id.pdf',
            'path' => 'guards/'.$guardA->id.'/id.pdf',
            'mime_type' => 'application/pdf',
            'size' => 900,
        ]);

        GuardAttachment::query()->create([
            'guard_id' => $guardB->id,
            'label' => 'Medical',
            'document_type' => GuardDocumentType::Medical,
            'expires_at' => now()->subDay()->toDateString(),
            'original_name' => 'med.pdf',
            'path' => 'guards/'.$guardB->id.'/med.pdf',
            'mime_type' => 'application/pdf',
            'size' => 900,
        ]);

        $snapshot = app(ComplianceSnapshotService::class)->snapshot();

        $this->assertSame(2, $snapshot['guards_expired_documents']);
    }

    public function test_snapshot_detects_sla_breach_from_billing_profile(): void
    {
        $client = Client::factory()->create();
        $site = Site::factory()->create([
            'client_id' => $client->id,
            'required_guards' => 2,
        ]);

        BillingProfile::query()->create([
            'client_id' => $client->id,
            'site_id' => $site->id,
            'currency' => 'UGX',
            'contracted_armed_guards' => 4,
            'contracted_unarmed_guards' => 0,
            'effective_from' => now()->subMonth(),
            'is_active' => true,
        ]);

        Deployment::factory()->create([
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'is_current' => true,
        ]);

        $snapshot = app(ComplianceSnapshotService::class)->snapshot();

        $this->assertSame(1, $snapshot['sla_breach_sites']);
        $this->assertSame(4, $snapshot['sla_breaches'][0]['contracted']);
        $this->assertIsInt($snapshot['sla_breaches'][0]['site_id']);
    }

    public function test_dashboard_shows_compliance_widget_for_hr(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();

        $guard = Guard::factory()->create(['employment_status' => EmploymentStatus::Active]);
        GuardAttachment::query()->create([
            'guard_id' => $guard->id,
            'label' => 'License',
            'document_type' => GuardDocumentType::License,
            'expires_at' => now()->subDays(2)->toDateString(),
            'original_name' => 'license.pdf',
            'path' => 'guards/'.$guard->id.'/license.pdf',
            'mime_type' => 'application/pdf',
            'size' => 900,
        ]);

        $this->actingAs($hr)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Compliance &amp; contracts', false)
            ->assertSee('expired compliance documents', false);
    }

    public function test_proactive_scan_alerts_expiring_client_contract(): void
    {
        $client = Client::factory()->create([
            'contract_status' => ContractStatus::Active,
            'contract_end_date' => now()->addDays(10),
        ]);

        $this->artisan('psg:scan-proactive-alerts')->assertSuccessful();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'client.contract_expiring',
            'subject_type' => Client::class,
            'subject_id' => $client->id,
        ]);
    }
}
