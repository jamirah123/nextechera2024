<?php

namespace Tests\Feature\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\OperationalStatus;
use App\Enums\GuardClassification;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Leave;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Enums\DeploymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HrManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_approve_leave_and_set_on_leave_status(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'current_site_id' => Site::factory()->create()->id,
        ]);

        $leave = Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => LeaveType::Annual,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'expected_return_date' => now()->addDays(3)->toDateString(),
            'status' => LeaveStatus::Pending,
            'reason' => 'Family',
        ]);

        $this->actingAs($hr)
            ->post(route('leaves.approve', $leave))
            ->assertRedirect();

        $this->assertSame(LeaveStatus::Approved, $leave->fresh()->status);
        $this->assertSame(OperationalStatus::OnLeave, $guard->fresh()->operational_status);
    }

    public function test_approved_leave_blocks_new_shift_scheduling(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
        ]);

        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        Leave::query()->create([
            'guard_id' => $guard->id,
            'leave_type' => LeaveType::Sick,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDay()->toDateString(),
            'status' => LeaveStatus::Approved,
        ]);

        $this->actingAs($manager)
            ->post(route('shifts.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_date' => now()->toDateString(),
                'start_time' => '06:00',
                'end_time' => '18:00',
                'period' => ShiftPeriod::Day->value,
                'shift_type' => ShiftType::Normal->value,
                'guard_classification' => GuardClassification::Unarmed->value,
                'acknowledge_warnings' => true,
            ])
            ->assertSessionHasErrors('shift');
    }

    public function test_absence_marks_guard_absent_and_shift_missed(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'current_site_id' => $site->id,
        ]);

        $shift = Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
            'status' => ShiftStatus::Scheduled,
        ]);

        $this->actingAs($ops)
            ->post(route('absences.store'), [
                'guard_id' => $guard->id,
                'absence_date' => now()->toDateString(),
                'reason' => 'no_show',
                'site_id' => $site->id,
                'shift_id' => $shift->id,
            ])
            ->assertRedirect();

        $this->assertSame(OperationalStatus::Absent, $guard->fresh()->operational_status);
        $this->assertSame(ShiftStatus::Missed, $shift->fresh()->status);
    }

    public function test_finance_cannot_create_desertions(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->get(route('desertions.create'))
            ->assertForbidden();
    }
}
