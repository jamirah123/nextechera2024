<?php

namespace Tests\Feature\Operations;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\Site;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_manager_can_log_and_view_occurrence(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();

        $this->actingAs($ops)
            ->post(route('incidents.store'), [
                'site_id' => $site->id,
                'incident_type' => IncidentType::Theft->value,
                'severity' => IncidentSeverity::High->value,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'title' => 'Missing copper cable',
                'description' => 'Approximately 20 metres of cable removed from store room.',
                'client_notified' => true,
            ])
            ->assertRedirect();

        $incident = Incident::query()->first();

        $this->assertNotNull($incident);
        $this->assertStringStartsWith('OB-', $incident->reference);
        $this->assertSame(IncidentType::Theft, $incident->incident_type);
        $this->assertTrue($incident->client_notified);

        $this->actingAs($ops)
            ->get(route('incidents.show', $incident))
            ->assertOk()
            ->assertSee('Missing copper cable', false)
            ->assertSee($incident->reference, false);
    }

    public function test_region_supervisor_cannot_log_outside_own_region(): void
    {
        $supervisor = Supervisor::factory()->create();
        $user = User::factory()->regionSupervisor($supervisor->id)->create();
        $otherSite = Site::factory()->create();

        $this->actingAs($user)
            ->post(route('incidents.store'), [
                'site_id' => $otherSite->id,
                'incident_type' => IncidentType::Trespass->value,
                'severity' => IncidentSeverity::Medium->value,
                'occurred_at' => now()->format('Y-m-d H:i:s'),
                'title' => 'Unauthorized entry',
                'description' => 'Two individuals attempted to enter via rear gate.',
            ])
            ->assertSessionHasErrors('site_id');

        $this->assertDatabaseCount('incidents', 0);
    }

    public function test_region_supervisor_sees_only_own_region_occurrences(): void
    {
        $supervisor = Supervisor::factory()->create();
        $user = User::factory()->regionSupervisor($supervisor->id)->create();
        $ownSite = Site::factory()->create(['region_id' => $supervisor->region_id]);
        $otherSite = Site::factory()->create();

        $visible = Incident::query()->create([
            'reference' => 'OB-TEST-0001',
            'site_id' => $ownSite->id,
            'incident_type' => IncidentType::Medical->value,
            'severity' => IncidentSeverity::Medium->value,
            'status' => IncidentStatus::Reported->value,
            'occurred_at' => now(),
            'reported_at' => now(),
            'title' => 'Guard fainted on post',
            'description' => 'First aid administered on site.',
            'reported_by' => $user->id,
        ]);

        Incident::query()->create([
            'reference' => 'OB-TEST-0002',
            'site_id' => $otherSite->id,
            'incident_type' => IncidentType::Fire->value,
            'severity' => IncidentSeverity::Critical->value,
            'status' => IncidentStatus::Reported->value,
            'occurred_at' => now(),
            'reported_at' => now(),
            'title' => 'Other region fire alarm',
            'description' => 'Should not appear in regional list.',
            'reported_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('incidents.index'))
            ->assertOk()
            ->assertSee('Guard fainted on post', false)
            ->assertDontSee('Other region fire alarm', false);

        $this->actingAs($user)
            ->get(route('incidents.show', $visible))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('incidents.show', Incident::query()->where('reference', 'OB-TEST-0002')->first()))
            ->assertForbidden();
    }

    public function test_follow_up_update_changes_status(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $assignee = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();

        $incident = Incident::query()->create([
            'reference' => 'OB-TEST-0003',
            'site_id' => $site->id,
            'incident_type' => IncidentType::Disturbance->value,
            'severity' => IncidentSeverity::Low->value,
            'status' => IncidentStatus::Reported->value,
            'occurred_at' => now(),
            'reported_at' => now(),
            'title' => 'Noise complaint',
            'description' => 'Loud music from adjacent property.',
            'reported_by' => $ops->id,
        ]);

        $this->actingAs($ops)
            ->put(route('incidents.update', $incident), [
                'status' => IncidentStatus::FollowUp->value,
                'assigned_to' => $assignee->id,
                'follow_up_notes' => 'Site manager to meet client tomorrow.',
                'client_notified' => true,
            ])
            ->assertRedirect();

        $incident->refresh();

        $this->assertSame(IncidentStatus::FollowUp, $incident->status);
        $this->assertSame($assignee->id, $incident->assigned_to);
        $this->assertTrue($incident->client_notified);
    }

    public function test_csv_export_is_available_to_report_viewers(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();

        Incident::query()->create([
            'reference' => 'OB-TEST-0004',
            'site_id' => $site->id,
            'incident_type' => IncidentType::Breach->value,
            'severity' => IncidentSeverity::High->value,
            'status' => IncidentStatus::Investigating->value,
            'occurred_at' => now(),
            'reported_at' => now(),
            'title' => 'Perimeter fence cut',
            'description' => 'Hole discovered during patrol.',
            'reported_by' => $ops->id,
        ]);

        $this->actingAs($ops)
            ->get(route('incidents.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_daily_pdf_export_requires_site_and_date(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();

        Incident::query()->create([
            'reference' => 'OB-TEST-0005',
            'site_id' => $site->id,
            'incident_type' => IncidentType::Other->value,
            'severity' => IncidentSeverity::Low->value,
            'status' => IncidentStatus::Reported->value,
            'occurred_at' => now(),
            'reported_at' => now(),
            'title' => 'Routine note',
            'description' => 'Handover note for client report.',
            'reported_by' => $ops->id,
        ]);

        $this->actingAs($ops)
            ->get(route('incidents.export-daily', [
                'site_id' => $site->id,
                'date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
