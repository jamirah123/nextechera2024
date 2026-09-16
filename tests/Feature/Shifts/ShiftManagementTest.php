<?php

namespace Tests\Feature\Shifts;

use App\Enums\AttendanceEventType;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\Shifts\ShiftLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShiftManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_shift_manager_can_create_shift_for_deployed_guard(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        [$guard, $site] = $this->deployedGuardAndSite();

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
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'shift_type' => ShiftType::Normal->value,
        ]);

        $status = Shift::query()
            ->where('guard_id', $guard->id)
            ->where('site_id', $site->id)
            ->value('status');

        $statusValue = $status instanceof ShiftStatus ? $status->value : (string) $status;

        $this->assertSame(ShiftStatus::Recorded->value, $statusValue);
    }

    public function test_overlapping_shifts_are_blocked(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        [$guard, $site] = $this->deployedGuardAndSite();

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
            'status' => ShiftStatus::Scheduled,
        ]);

        $this->actingAs($manager)
            ->post(route('shifts.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_date' => now()->toDateString(),
                'start_time' => '12:00',
                'end_time' => '20:00',
                'period' => ShiftPeriod::Day->value,
                'shift_type' => ShiftType::Normal->value,
                'guard_classification' => GuardClassification::Unarmed->value,
                'acknowledge_warnings' => true,
            ])
            ->assertSessionHasErrors('shift');
    }

    public function test_finance_manager_cannot_create_shifts(): void
    {
        $finance = User::factory()->role(UserRole::FinanceManager)->create();

        $this->actingAs($finance)
            ->get(route('shifts.create'))
            ->assertForbidden();
    }

    public function test_overnight_shift_ends_next_day(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        [$guard, $site] = $this->deployedGuardAndSite();

        $this->actingAs($manager)
            ->post(route('shifts.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_date' => now()->toDateString(),
                'start_time' => '18:00',
                'end_time' => '06:00',
                'period' => ShiftPeriod::Night->value,
                'shift_type' => ShiftType::Normal->value,
                'guard_classification' => GuardClassification::Unarmed->value,
                'acknowledge_warnings' => true,
            ])
            ->assertRedirect();

        $shift = Shift::query()->where('guard_id', $guard->id)->latest('id')->first();

        $this->assertTrue($shift->is_overnight);
        $this->assertSame(now()->addDay()->toDateString(), $shift->ends_at->toDateString());
    }

    /** @return array{0: Guard, 1: Site} */
    private function deployedGuardAndSite(): array
    {
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
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

        return [$guard, $site];
    }

    public function test_completed_shift_keeps_standing_deployment_and_marks_guard_off_duty(): void
    {
        Carbon::setTestNow('2026-08-28 18:00:00');

        [$guard, $site] = $this->deployedGuardAndSite();

        Deployment::query()->where('guard_id', $guard->id)->current()->update([
            'shift_type' => DeploymentShiftType::Rotating,
        ]);

        $shift = Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
            'status' => ShiftStatus::InProgress,
        ]);

        Attendance::query()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'shift_id' => $shift->id,
            'event_type' => AttendanceEventType::CheckIn,
            'source' => 'test',
            'occurred_at' => now()->setTime(6, 10),
        ]);

        $guard->update(['operational_status' => OperationalStatus::OnDuty]);

        app(ShiftLifecycleService::class)->sync();
        $shift->refresh();

        $this->assertSame(ShiftStatus::Completed, $shift->status);

        $guard->refresh();

        $this->assertSame(OperationalStatus::OffDuty, $guard->operational_status);
        $this->assertSame($site->id, $guard->current_site_id);
        $this->assertTrue(Deployment::query()->current()->where('guard_id', $guard->id)->exists());

        Carbon::setTestNow();
    }

    public function test_shift_manager_can_correct_completed_shift_guard(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        [$wrongGuard, $site] = $this->deployedGuardAndSite();

        $correctGuard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $shift = Shift::factory()->create([
            'guard_id' => $wrongGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'starts_at' => now()->setTime(6, 0),
            'ends_at' => now()->setTime(18, 0),
            'period' => ShiftPeriod::Day,
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
        ]);

        $this->actingAs($manager)
            ->put(route('shifts.update', $shift), [
                'guard_id' => $correctGuard->id,
                'site_id' => $site->id,
                'shift_date' => $shift->shift_date->toDateString(),
                'start_time' => '06:00',
                'end_time' => '18:00',
                'period' => ShiftPeriod::Day->value,
                'shift_type' => ShiftType::Normal->value,
                'guard_classification' => GuardClassification::Unarmed->value,
                'acknowledge_warnings' => true,
                'notes' => 'Corrected wrong guard',
            ])
            ->assertRedirect(route('shifts.show', $shift));

        $shift->refresh();
        $this->assertSame($correctGuard->id, $shift->guard_id);
        $this->assertSame(ShiftStatus::Completed, $shift->status);
        $this->assertStringContainsString('Corrected guard assignment', (string) $shift->notes);
    }
}
