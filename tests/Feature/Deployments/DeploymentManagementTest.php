<?php

namespace Tests\Feature\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Region;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeploymentManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_manager_can_deploy_guard(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'is_current' => true,
            'status' => DeploymentStatus::Active->value,
        ]);

        $this->assertTrue(
            Shift::query()
                ->where('guard_id', $guard->id)
                ->where('site_id', $site->id)
                ->whereDate('shift_date', now()->toDateString())
                ->where('period', ShiftPeriod::Day->value)
                ->exists()
        );

        $guard->refresh();
        $this->assertSame($site->id, $guard->current_site_id);
        $this->assertSame(OperationalStatus::OnDuty, $guard->operational_status);
    }

    public function test_transfer_preserves_previous_deployment_history(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $fromSite = Site::factory()->create();
        $toSite = Site::factory()->create(['region_id' => $fromSite->region_id]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $fromSite->region_id,
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $fromSite->id,
            'region_id' => $fromSite->region_id,
            'supervisor_id' => $fromSite->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $guard->update([
            'current_site_id' => $fromSite->id,
            'current_supervisor_id' => $fromSite->supervisor_id,
        ]);

        $this->actingAs($admin)
            ->post(route('deployments.transfer.store', $deployment), [
                'site_id' => $toSite->id,
                'shift_type' => DeploymentShiftType::Night->value,
                'reason' => 'Coverage rebalance',
            ])
            ->assertRedirect();

        $deployment->refresh();
        $this->assertSame(DeploymentStatus::Transferred, $deployment->status);
        $this->assertFalse($deployment->is_current);

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $toSite->id,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('deployment_transfers', [
            'guard_id' => $guard->id,
            'from_site_id' => $fromSite->id,
            'to_site_id' => $toSite->id,
        ]);
    }

    public function test_hr_manager_cannot_create_deployments(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();

        $this->actingAs($hr)
            ->get(route('deployments.create'))
            ->assertForbidden();
    }

    public function test_site_postings_date_filter_shows_only_postings_with_duty_on_that_date(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $earlyGuard = Guard::factory()->create([
            'full_name' => 'Early Guard',
            'employment_id' => 'PSG1001',
            'region_id' => $site->region_id,
        ]);
        $laterGuard = Guard::factory()->create([
            'full_name' => 'Later Guard',
            'employment_id' => 'PSG1002',
            'region_id' => $site->region_id,
        ]);

        Deployment::factory()->create([
            'guard_id' => $earlyGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Ended,
            'is_current' => false,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-05',
        ]);

        Deployment::factory()->create([
            'guard_id' => $laterGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Night,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'start_date' => '2026-09-06',
            'end_date' => null,
        ]);

        Shift::factory()->create([
            'guard_id' => $earlyGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2026-09-03',
            'period' => ShiftPeriod::Day,
            'status' => ShiftStatus::Recorded,
        ]);

        Shift::factory()->create([
            'guard_id' => $laterGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2026-09-07',
            'period' => ShiftPeriod::Night,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.index', ['date' => '2026-09-03']))
            ->assertOk()
            ->assertSee('Early Guard')
            ->assertDontSee('Later Guard')
            ->assertSee('Guards with a duty on 03 Sep 2026');

        $this->actingAs($ops)
            ->get(route('deployments.index', ['date' => '2026-09-07']))
            ->assertOk()
            ->assertSee('Later Guard')
            ->assertDontSee('Early Guard');

        // Coverage alone is not enough — no duty on 04 Sep for early guard.
        $this->actingAs($ops)
            ->get(route('deployments.index', ['date' => '2026-09-04']))
            ->assertOk()
            ->assertDontSee('Early Guard')
            ->assertDontSee('Later Guard');
    }

    public function test_manpower_counts_active_deployments(): void
    {
        $site = Site::factory()->create([
            'required_guards' => 2,
            'required_day_guards' => 1,
            'required_night_guards' => 1,
        ]);

        Deployment::factory()->create([
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'shift_type' => DeploymentShiftType::Day,
        ]);

        $snapshot = $site->manpowerSnapshot();

        $this->assertSame(1, $snapshot['deployed']);
        $this->assertSame(1, $snapshot['shortage']);
    }

    public function test_cannot_deploy_guard_to_site_in_another_region(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $otherRegion = Region::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $otherRegion->id,
        ]);

        $this->actingAs($ops)
            ->from(route('deployments.create'))
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertDatabaseMissing('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'is_current' => true,
        ]);
    }

    public function test_shift_manager_can_end_deployment(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $shift = Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'period' => ShiftPeriod::Day,
            'status' => ShiftStatus::InProgress,
        ]);

        $this->actingAs($manager)
            ->get(route('deployments.show', $deployment))
            ->assertOk()
            ->assertSee('End deployment', false)
            ->assertSee(route('deployments.end', $deployment), false);

        $this->actingAs($manager)
            ->post(route('deployments.end', $deployment))
            ->assertRedirect(route('deployments.show', $deployment));

        $deployment->refresh();
        $guard->refresh();
        $shift->refresh();

        $this->assertSame(DeploymentStatus::Ended, $deployment->status);
        $this->assertFalse($deployment->is_current);
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);
        $this->assertNull($guard->current_site_id);
        $this->assertSame(ShiftStatus::Cancelled, $shift->status);
        $this->assertStringContainsString('Withdrawn — deployment ended.', (string) $shift->notes);
    }

    public function test_past_duty_date_records_completed_shift_taken(): void
    {
        Carbon::setTestNow('2026-09-06 10:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'duty_type' => ShiftType::Normal->value,
                'start_date' => '2026-09-01',
                'duty_date_to' => '2026-09-03',
            ])
            ->assertRedirect();

        $this->assertSame(3, Shift::query()
            ->where('guard_id', $guard->id)
            ->where('site_id', $site->id)
            ->where('status', ShiftStatus::Recorded->value)
            ->count());

        $guard->refresh();
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);
        $this->assertNull($guard->current_site_id);
        $this->assertFalse(
            Deployment::query()->where('guard_id', $guard->id)->where('is_current', true)->exists()
        );
        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'is_current' => false,
            'status' => DeploymentStatus::Ended->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_backdated_posting_keeps_selected_shift_date_separate_from_entry_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 20:05:00'));

        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'name' => 'Site A',
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG5512',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'duty_type' => ShiftType::Normal->value,
                'start_date' => '2026-09-05',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $shift = Shift::query()
            ->where('guard_id', $guard->id)
            ->where('site_id', $site->id)
            ->first();

        $this->assertNotNull($shift);
        $this->assertSame('2026-09-05', $shift->shift_date->toDateString());
        $this->assertSame('2026-09-07 20:05:00', $shift->created_at->format('Y-m-d H:i:s'));
        $this->assertSame($manager->id, $shift->created_by);
        $this->assertSame($manager->id, $shift->updated_by);
        $this->assertNotSame($shift->shift_date->toDateString(), $shift->created_at->toDateString());

        $guard->refresh();
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);
        $this->assertNull($guard->current_site_id);
        $historical = Deployment::query()
            ->where('guard_id', $guard->id)
            ->where('site_id', $site->id)
            ->where('is_current', false)
            ->where('status', DeploymentStatus::Ended)
            ->first();
        $this->assertNotNull($historical);
        $this->assertSame('2026-09-05', $historical->start_date->toDateString());
        $this->assertSame('2026-09-05', $historical->end_date->toDateString());

        Carbon::setTestNow();
    }

    public function test_past_posting_then_today_posting_sets_on_duty_only_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 10:00:00'));

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $siteA = Site::factory()->create([
            'name' => 'Site A',
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $siteC = Site::factory()->create([
            'name' => 'Site C',
            'region_id' => $siteA->region_id,
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG5512',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $siteA->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $siteA->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => '2026-09-05',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $guard->refresh();
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->operational_status);
        $this->assertNull($guard->current_site_id);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $siteC->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => '2026-09-07',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $guard->refresh();
        $this->assertSame(OperationalStatus::OnDuty, $guard->operational_status);
        $this->assertSame($siteC->id, $guard->current_site_id);
        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $siteC->id,
            'is_current' => true,
            'status' => DeploymentStatus::Active->value,
        ]);
        $this->assertTrue(
            Shift::query()
                ->where('guard_id', $guard->id)
                ->where('site_id', $siteA->id)
                ->whereDate('shift_date', '2026-09-05')
                ->where('status', ShiftStatus::Recorded)
                ->exists()
        );
        $this->assertTrue(
            Shift::query()
                ->where('guard_id', $guard->id)
                ->where('site_id', $siteC->id)
                ->whereDate('shift_date', '2026-09-07')
                ->where('status', ShiftStatus::Recorded)
                ->exists()
        );

        Carbon::setTestNow();
    }

    public function test_todays_open_window_posting_records_completed_shift_taken(): void
    {
        Carbon::setTestNow('2026-09-06 10:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'duty_type' => ShiftType::Overtime->value,
                'start_date' => '2026-09-06',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => ShiftStatus::Recorded->value,
            'shift_type' => ShiftType::Overtime->value,
        ]);

        $this->assertTrue(
            Shift::query()
                ->where('guard_id', $guard->id)
                ->where('site_id', $site->id)
                ->whereDate('shift_date', '2026-09-06')
                ->where('status', ShiftStatus::Recorded)
                ->where('shift_type', ShiftType::Overtime)
                ->exists()
        );

        Carbon::setTestNow();
    }

    public function test_future_duty_date_posting_also_records_completed_shift_taken(): void
    {
        Carbon::setTestNow('2026-09-06 10:00:00');

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'duty_type' => ShiftType::Normal->value,
                'start_date' => '2026-09-10',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('shifts', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => ShiftStatus::Recorded->value,
            'shift_type' => ShiftType::Normal->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_shift_manager_can_correct_deployment_guard(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'required_day_guards' => 4,
            'required_night_guards' => 2,
            'required_guards' => 6,
        ]);

        $wrongGuard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
        ]);
        $correctGuard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'current_site_id' => null,
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $wrongGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $this->actingAs($manager)
            ->put(route('deployments.update', $deployment), [
                'guard_id' => $correctGuard->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => now()->toDateString(),
                'correction_reason' => 'Wrong guard was recorded on deploy',
            ])
            ->assertRedirect(route('deployments.show', $deployment));

        $deployment->refresh();
        $wrongGuard->refresh();
        $correctGuard->refresh();

        $this->assertSame($correctGuard->id, $deployment->guard_id);
        $this->assertSame(OperationalStatus::AwaitingDeployment, $wrongGuard->operational_status);
        $this->assertNull($wrongGuard->current_site_id);
        $this->assertSame(OperationalStatus::OnDuty, $correctGuard->operational_status);
        $this->assertSame($site->id, $correctGuard->current_site_id);
    }

    public function test_cannot_deploy_same_guard_to_second_site_on_same_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 10:00:00'));

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $siteA = Site::factory()->create([
            'name' => 'Site A',
            'required_day_guards' => 4,
            'required_night_guards' => 4,
            'required_guards' => 8,
        ]);
        $siteB = Site::factory()->create([
            'name' => 'Site B',
            'region_id' => $siteA->region_id,
            'required_day_guards' => 4,
            'required_night_guards' => 4,
            'required_guards' => 8,
        ]);
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG5512',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $siteA->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $siteA->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => '2026-09-07',
            ])
            ->assertRedirect();

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $siteB->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => '2026-09-07',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('deployment');

        $this->assertStringContainsString(
            'Deployment Conflict: Guard PSG5512 is already deployed at Site A for this shift',
            session('errors')->first('deployment'),
        );

        $this->assertSame(1, Deployment::query()->where('guard_id', $guard->id)->where('is_current', true)->count());
        $this->assertDatabaseMissing('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $siteB->id,
            'is_current' => true,
        ]);

        Carbon::setTestNow();
    }

    public function test_can_deploy_opposite_period_cover_at_another_site_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-07 10:00:00'));

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $siteA = Site::factory()->create([
            'name' => 'Site A',
            'required_day_guards' => 4,
            'required_night_guards' => 4,
            'required_guards' => 8,
        ]);
        $siteB = Site::factory()->create([
            'name' => 'Site B',
            'region_id' => $siteA->region_id,
            'required_day_guards' => 4,
            'required_night_guards' => 4,
            'required_guards' => 8,
        ]);
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG5512',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'current_site_id' => null,
            'region_id' => $siteA->region_id,
        ]);

        $this->actingAs($ops)
            ->post(route('deployments.store'), [
                'guard_id' => $guard->id,
                'site_id' => $siteA->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => '2026-09-07',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->actingAs($ops)
            ->post(route('deployments.board.store'), [
                'start_date' => '2026-09-07',
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteB->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertTrue(
            Shift::query()
                ->where('guard_id', $guard->id)
                ->where('site_id', $siteA->id)
                ->whereDate('shift_date', '2026-09-07')
                ->where('period', ShiftPeriod::Day->value)
                ->exists()
        );
        $this->assertTrue(
            Shift::query()
                ->where('guard_id', $guard->id)
                ->where('site_id', $siteB->id)
                ->whereDate('shift_date', '2026-09-07')
                ->where('period', ShiftPeriod::Night->value)
                ->exists()
        );

        // Standing posting remains at Site A (Day); night cover is duty-only.
        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $siteA->id,
            'is_current' => true,
        ]);

        Carbon::setTestNow();
    }

    public function test_transfer_to_another_site_same_period_is_still_allowed(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $fromSite = Site::factory()->create(['name' => 'Site A']);
        $toSite = Site::factory()->create([
            'name' => 'Site B',
            'region_id' => $fromSite->region_id,
            'required_day_guards' => 4,
            'required_guards' => 4,
        ]);
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG5512',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $fromSite->region_id,
            'current_site_id' => $fromSite->id,
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $fromSite->id,
            'region_id' => $fromSite->region_id,
            'supervisor_id' => $fromSite->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('deployments.transfer.store', $deployment), [
                'site_id' => $toSite->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'reason' => 'Coverage rebalance',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $deployment->refresh();
        $this->assertFalse((bool) $deployment->is_current);
        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $toSite->id,
            'is_current' => true,
            'shift_type' => DeploymentShiftType::Day->value,
        ]);
    }
}
