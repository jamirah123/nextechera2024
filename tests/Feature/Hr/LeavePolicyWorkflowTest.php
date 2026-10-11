<?php

namespace Tests\Feature\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\OperationalStatus;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\LeaveAdjustment;
use App\Models\LeaveDecision;
use App\Models\LeaveEntitlement;
use App\Models\LeaveTypeConfig;
use App\Models\Site;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class LeavePolicyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_consecutive_day_limit_blocks_an_overlong_request(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = LeaveTypeConfig::query()->where('code', 'emergency')->firstOrFail();

        $this->actingAs($hr)
            ->from(route('leaves.create'))
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-12',
                'end_date' => '2026-10-16',
                'reason' => 'Family emergency',
            ])
            ->assertRedirect(route('leaves.create'))
            ->assertSessionHasErrors('leave');

        $this->assertSame(0, Leave::query()->count());
    }

    public function test_notice_period_applies_unless_the_actor_can_approve_leave(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = LeaveTypeConfig::query()->where('code', 'annual')->firstOrFail();
        $type->update(['min_notice_days' => 7]);

        $payload = [
            'guard_id' => $guard->id,
            'leave_type_id' => $type->id,
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'reason' => 'Short notice',
        ];

        $this->actingAs($finance);
        try {
            app(LeaveService::class)->create($payload);
            $this->fail('A user who cannot approve leave was allowed to skip the notice period.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('notice', $e->getMessage());
        }

        $this->actingAs($hr);
        $leave = app(LeaveService::class)->create($payload);
        $this->assertSame(LeaveStatus::Pending, $leave->status);
    }

    public function test_an_employee_cannot_approve_their_own_self_service_request(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $other = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = LeaveTypeConfig::query()->where('code', 'annual')->firstOrFail();

        $leave = Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => 'annual',
            'leave_type_id' => $type->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'days' => 1,
            'status' => LeaveStatus::Pending,
            'requested_by' => $hr->id,
            'self_service' => true,
        ]);

        $this->actingAs($hr)
            ->from(route('leaves.show', $leave))
            ->post(route('leaves.approve', $leave))
            ->assertRedirect(route('leaves.show', $leave))
            ->assertSessionHasErrors('leave');

        $this->assertSame(LeaveStatus::Pending, $leave->fresh()->status);

        $this->actingAs($other)
            ->post(route('leaves.approve', $leave))
            ->assertRedirect();

        $this->assertSame(LeaveStatus::Approved, $leave->fresh()->status);
        $this->assertTrue(LeaveDecision::query()->where('leave_id', $leave->id)->where('decision', 'approved')->exists());
    }

    public function test_return_and_balance_adjustment_are_recorded_without_rewriting_the_leave(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $type = LeaveTypeConfig::query()->where('code', 'annual')->firstOrFail();

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $type->id,
                'start_date' => '2026-10-05',
                'end_date' => '2026-10-07',
                'expected_return_date' => '2026-10-08',
                'reason' => 'Rest',
                'handover_notes' => 'Desk covered by the duty clerk.',
                'approve_now' => 1,
            ])
            ->assertRedirect();

        $leave = Leave::query()->firstOrFail();
        $this->assertSame(LeaveStatus::Approved, $leave->status);
        $this->assertSame('Desk covered by the duty clerk.', $leave->handover_notes);

        $this->actingAs($hr)
            ->post(route('leaves.return', $leave), [
                'actual_return_date' => '2026-10-09',
                'notes' => 'Returned a day late.',
            ])
            ->assertRedirect();

        $leave->refresh();
        $this->assertSame(LeaveStatus::Completed, $leave->status);
        $this->assertSame('2026-10-09', $leave->actual_return_date->toDateString());
        $this->assertNotSame(OperationalStatus::Deserted, $guard->fresh()->operational_status);

        $this->actingAs($hr)
            ->post(route('leaves.adjust', $leave), [
                'days' => 1,
                'reason' => 'Opening balance correction',
            ])
            ->assertRedirect();

        $balance = LeaveEntitlement::query()->where('guard_id', $guard->id)->where('leave_type_id', $type->id)->firstOrFail();
        $this->assertSame(1.0, (float) $balance->adjustments);
        $adjustment = LeaveAdjustment::query()->where('guard_id', $guard->id)->firstOrFail();
        $this->assertSame('Opening balance correction', $adjustment->reason);
        $this->assertSame($hr->id, $adjustment->adjusted_by);
        $this->assertSame(LeaveStatus::Completed, $leave->fresh()->status);
    }

    public function test_a_draft_does_not_reserve_the_balance(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => LeaveTypeConfig::query()->where('code', 'annual')->value('id'),
                'start_date' => '2026-10-21',
                'end_date' => '2026-10-21',
                'reason' => 'Not ready',
                'save_draft' => 1,
            ])
            ->assertRedirect();

        $leave = Leave::query()->firstOrFail();
        $this->assertSame(LeaveStatus::Draft, $leave->status);
        $this->assertNull(LeaveEntitlement::query()->where('guard_id', $guard->id)->first());
    }

    public function test_hr_can_approve_a_draft(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = $this->guard();
        $typeId = LeaveTypeConfig::query()->where('code', 'annual')->value('id');

        $this->actingAs($hr)
            ->post(route('leaves.store'), [
                'guard_id' => $guard->id,
                'leave_type_id' => $typeId,
                'start_date' => '2026-10-21',
                'end_date' => '2026-10-21',
                'reason' => 'Ready to approve',
                'save_draft' => 1,
            ])
            ->assertRedirect();

        $leave = Leave::query()->firstOrFail();

        $this->actingAs($hr)
            ->get(route('leaves.show', $leave))
            ->assertOk()
            ->assertSee('Approve');

        $this->actingAs($hr)
            ->post(route('leaves.approve', $leave))
            ->assertRedirect();

        $leave->refresh();
        $balance = LeaveEntitlement::query()->where('guard_id', $guard->id)->where('leave_type_id', $typeId)->firstOrFail();
        $this->assertSame(LeaveStatus::Approved, $leave->status);
        $this->assertSame($hr->id, $leave->approved_by);
        $this->assertSame(0.0, (float) $balance->pending);
        $this->assertGreaterThan(0, (float) $balance->taken);
    }

    private function guard(): Guard
    {
        $site = Site::factory()->create();

        return Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
        ]);
    }
}
