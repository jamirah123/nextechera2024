<?php

namespace Tests\Feature\Operations;

use App\Enums\AuditCategory;
use App\Enums\AuditSeverity;
use App\Enums\ContractStatus;
use App\Enums\CoverageStatus;
use App\Enums\UserRole;
use App\Enums\WorkOrderCategory;
use App\Enums\WorkOrderStatus;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Guard;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\AuditService;
use App\Services\ManpowerService;
use App\Services\ProactiveAlertService;
use App\Services\WorkOrderService;
use App\Support\Access\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WorkOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_understaffed_alert_creates_assignable_work_order(): void
    {
        $site = Site::factory()->create([
            'required_guards' => 5,
        ]);

        $manpower = Mockery::mock(ManpowerService::class);
        $manpower->shouldReceive('forSite')
            ->andReturn([
                'status' => CoverageStatus::Understaffed,
                'required' => 5,
                'deployed' => 2,
                'shortage' => 3,
                'contracted' => 5,
                'sla_shortage' => 0,
            ]);
        $this->app->instance(ManpowerService::class, $manpower);

        app(ProactiveAlertService::class)->scanUnderstaffedSites();

        $workOrder = WorkOrder::query()->first();
        $this->assertNotNull($workOrder);
        $this->assertSame(WorkOrderCategory::Staffing, $workOrder->category);
        $this->assertStringContainsString('Fill 3 guard(s)', $workOrder->title);
        $this->assertStringContainsString($site->name, $workOrder->title);
        $this->assertSame(WorkOrderStatus::Open, $workOrder->status);
        $this->assertSame($site->id, $workOrder->subject_id);
    }

    public function test_client_contract_alert_creates_renewal_task(): void
    {
        $client = Client::factory()->create([
            'contract_end_date' => now()->addDays(10)->toDateString(),
            'contract_status' => ContractStatus::Active,
        ]);

        app(ProactiveAlertService::class)->alertClientContract($client, expired: false);

        $workOrder = WorkOrder::query()->first();
        $this->assertNotNull($workOrder);
        $this->assertSame(WorkOrderCategory::Contract, $workOrder->category);
        $this->assertStringContainsString('Renew client contract', $workOrder->title);
        $this->assertStringContainsString($client->name, $workOrder->title);
    }

    public function test_desertion_report_creates_follow_up_task(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create();

        $this->actingAs($hr)
            ->post(route('desertions.store'), [
                'guard_id' => $guard->id,
                'date_reported' => now()->toDateString(),
                'circumstances' => 'Did not report for duty',
            ])
            ->assertRedirect();

        $workOrder = WorkOrder::query()->first();
        $this->assertNotNull($workOrder);
        $this->assertSame(WorkOrderCategory::Hr, $workOrder->category);
        $this->assertStringContainsString('Follow up desertion case', $workOrder->title);
        $this->assertStringContainsString($guard->full_name, $workOrder->title);
    }

    public function test_manager_can_assign_and_complete_work_order(): void
    {
        $manager = User::factory()->role(UserRole::OperationsManager)->create();
        $assignee = User::factory()->role(UserRole::ShiftManager)->create();
        app(RolePermissionService::class)->seedDefaults();

        $log = app(AuditService::class)->log(
            action: 'site.understaffed',
            summary: 'Test site understaffed',
            subject: Site::factory()->create(['required_guards' => 3]),
            context: [
                'dedup_key' => 'test-understaffed-1',
                'shortage' => 2,
                'required' => 3,
                'deployed' => 1,
            ],
        );

        $workOrder = WorkOrder::query()->firstOrFail();

        $this->actingAs($manager)
            ->put(route('work-orders.update', $workOrder), [
                'assigned_to' => $assignee->id,
                'status' => WorkOrderStatus::InProgress->value,
            ])
            ->assertRedirect(route('work-orders.show', $workOrder));

        $this->assertSame($assignee->id, $workOrder->fresh()->assigned_to);

        $this->actingAs($assignee)
            ->post(route('work-orders.complete', $workOrder), [
                'resolution_notes' => 'Deployed two relief guards.',
            ])
            ->assertRedirect(route('work-orders.show', $workOrder));

        $this->assertSame(WorkOrderStatus::Completed, $workOrder->fresh()->status);
    }

    public function test_duplicate_alert_does_not_create_second_open_work_order(): void
    {
        $service = app(WorkOrderService::class);

        $log = AuditLog::query()->create([
            'action' => 'leave.pending_reminder',
            'category' => AuditCategory::Hr,
            'severity' => AuditSeverity::Warning,
            'summary' => 'Leave pending',
            'context' => ['dedup_key' => 'leave-pending-99'],
            'created_at' => now(),
        ]);

        $this->assertNotNull($service->maybeCreateFromAudit($log));
        $this->assertNull($service->maybeCreateFromAudit($log));
        $this->assertSame(1, WorkOrder::query()->count());
    }
}
