<?php

namespace Tests\Feature\Deployments;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\SiteStatus;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\SiteManpowerRequirement;
use App\Models\User;
use App\Services\ManpowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BulkDeploymentBoardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ops_can_open_deployment_board(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'current_site_id' => null,
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee($guard->full_name, false)
            ->assertSee('Deploy selected', false)
            ->assertSee('Showing', false);
    }

    public function test_bulk_deploy_assigns_selected_guards(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'current_site_id' => null,
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
        ]);

        $guard->refresh();
        $this->assertSame(OperationalStatus::OnDuty, $guard->operational_status);
    }

    public function test_a_day_posting_keeps_the_guard_on_the_board_with_the_night_still_open(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
        ]);

        $deployment = Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'shift_type' => DeploymentShiftType::Day,
            'start_date' => now()->toDateString(),
        ]);

        Shift::factory()->forDeployment($deployment)->create([
            'shift_date' => now()->toDateString(),
            'starts_at' => now()->copy()->setTime(6, 0),
            'ends_at' => now()->copy()->setTime(18, 0),
            'status' => ShiftStatus::Recorded,
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee($guard->full_name, false)
            ->assertSee('Day: '.$site->name, false)
            ->assertSee('Night: Available', false);
    }

    public function test_guard_returns_to_the_board_the_day_after_both_shifts_are_allocated(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00'));
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $siteA = Site::factory()->create([
            'name' => 'Site A',
            'required_day_guards' => 2,
            'required_night_guards' => 2,
            'required_guards' => 4,
        ]);
        $siteB = Site::factory()->create([
            'name' => 'Site B',
            'region_id' => $siteA->region_id,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
            'required_guards' => 4,
        ]);
        $guard = Guard::factory()->create([
            'full_name' => 'Agnes Adong',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $siteA->region_id,
            'current_site_id' => null,
            'date_employed' => '2025-01-01',
        ]);
        $dutyDate = '2026-10-09';
        $nextDate = '2026-10-10';

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteA->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($manager)
            ->get(route('deployments.board', ['start_date' => $dutyDate]))
            ->assertOk()
            ->assertSee('Agnes Adong', false)
            ->assertSee('Day: Site A', false)
            ->assertSee('Night: Available', false);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$guard->id],
                'overtime_reviewed' => '1',
                'confirm_overtime' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteA->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->actingAs($manager)
            ->get(route('deployments.board', ['start_date' => $dutyDate]))
            ->assertOk()
            ->assertDontSee('Agnes Adong', false);

        $this->actingAs($manager)
            ->get(route('deployments.board', ['start_date' => $nextDate]))
            ->assertOk()
            ->assertSee('Agnes Adong', false)
            ->assertSee('Day: Available', false)
            ->assertSee('Night: Available', false);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $nextDate,
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteB->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect(route('deployments.board', ['start_date' => $nextDate]))
            ->assertSessionHas('status');

        $this->actingAs($manager)
            ->get(route('deployments.board', ['start_date' => $nextDate]))
            ->assertOk()
            ->assertSee('Agnes Adong', false)
            ->assertSee('Day: Site B', false)
            ->assertSee('Night: Available', false);

        $this->actingAs($manager)
            ->get(route('deployments.board', ['start_date' => $dutyDate]))
            ->assertOk()
            ->assertDontSee('Agnes Adong', false);

        $this->assertTrue(Deployment::query()
            ->where('guard_id', $guard->id)
            ->where('site_id', $siteA->id)
            ->whereDate('start_date', $dutyDate)
            ->exists());
        $this->assertTrue(Deployment::query()
            ->where('guard_id', $guard->id)
            ->where('site_id', $siteB->id)
            ->whereDate('start_date', $nextDate)
            ->exists());
    }

    public function test_shift_manager_chooses_normal_or_overtime_for_each_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 14:00:00'));
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $daySite = Site::factory()->create([
            'name' => 'Wakiso Hospital 5',
            'required_day_guards' => 2,
            'required_night_guards' => 2,
            'required_guards' => 4,
        ]);
        $nightSite = Site::factory()->create([
            'name' => 'Wakiso Shopping Centre 13',
            'region_id' => $daySite->region_id,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
            'required_guards' => 4,
        ]);
        $guard = Guard::factory()->create([
            'full_name' => 'Agnes Adong',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $daySite->region_id,
            'current_site_id' => null,
            'date_employed' => '2025-01-01',
        ]);
        $dutyDate = '2026-10-08';

        Shift::factory()->create([
            'reference' => 'SH77-20261008-d',
            'guard_id' => $guard->id,
            'site_id' => $daySite->id,
            'region_id' => $daySite->region_id,
            'shift_date' => $dutyDate,
            'starts_at' => '2026-10-08 06:00:00',
            'ends_at' => '2026-10-08 18:00:00',
            'period' => 'day',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
            'is_overnight' => false,
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $nightSite->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('overtime_prompts')
            ->assertSessionMissing('status');

        $this->assertNull(
            Shift::query()->where('guard_id', $guard->id)->where('period', 'night')->whereDate('shift_date', $dutyDate)->first()
        );

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$guard->id],
                'overtime_reviewed' => '1',
                'confirm_overtime' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $nightSite->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $night = Shift::query()
            ->where('guard_id', $guard->id)
            ->where('period', 'night')
            ->whereDate('shift_date', $dutyDate)
            ->first();

        $this->assertNotNull($night);
        $this->assertSame(ShiftType::Overtime, $night->shift_type);
        $this->assertSame(ShiftType::Normal, Shift::query()->where('reference', 'SH77-20261008-d')->first()?->shift_type);

        $overtimeGuard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $daySite->region_id,
            'current_site_id' => null,
            'date_employed' => '2025-01-01',
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$overtimeGuard->id],
                'rows' => [
                    $overtimeGuard->id => [
                        'site_id' => $nightSite->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                        'duty_type' => ShiftType::Overtime->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(
            ShiftType::Overtime,
            Shift::query()->where('guard_id', $overtimeGuard->id)->where('period', 'night')->first()?->shift_type,
        );
    }

    public function test_past_date_keeps_the_guard_available_for_the_other_shift(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $siteA = Site::factory()->create([
            'name' => 'Site A',
            'required_day_guards' => 2,
            'required_night_guards' => 2,
            'required_guards' => 4,
        ]);
        $siteB = Site::factory()->create([
            'name' => 'Site B',
            'region_id' => $siteA->region_id,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
            'required_guards' => 4,
        ]);
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG001',
            'full_name' => 'Kaheru Richard',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $siteA->region_id,
            'date_employed' => '2025-01-01',
            'current_site_id' => null,
        ]);
        $dutyDate = '2026-09-30';

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteA->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect(route('deployments.board', ['start_date' => $dutyDate]))
            ->assertSessionHas('status');

        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->fresh()->operational_status);
        $this->assertTrue(Shift::query()
            ->where('guard_id', $guard->id)
            ->where('site_id', $siteA->id)
            ->whereDate('shift_date', $dutyDate)
            ->where('period', 'day')
            ->where('status', ShiftStatus::Recorded->value)
            ->exists());
        $this->assertFalse(Shift::query()
            ->where('guard_id', $guard->id)
            ->whereDate('shift_date', now()->toDateString())
            ->exists());

        $this->actingAs($manager)
            ->get(route('deployments.board', ['start_date' => $dutyDate]))
            ->assertOk()
            ->assertSee('Kaheru Richard', false)
            ->assertSee('Day: Site A', false)
            ->assertSee('Night: Available', false);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteB->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('deployment_errors');

        $this->assertStringContainsString(
            'Guard already deployed for the Day shift on 30 September 2026 at Site A.',
            implode(' | ', session('deployment_errors') ?? []),
        );

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$guard->id],
                'overtime_reviewed' => '1',
                'confirm_overtime' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteA->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                        'duty_type' => ShiftType::Overtime->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->fresh()->operational_status);
        $this->assertTrue(Shift::query()
            ->where('guard_id', $guard->id)
            ->where('site_id', $siteA->id)
            ->whereDate('shift_date', $dutyDate)
            ->where('period', 'night')
            ->where('shift_type', ShiftType::Overtime->value)
            ->where('status', ShiftStatus::Recorded->value)
            ->exists());

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $dutyDate,
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteB->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                        'duty_type' => ShiftType::Overtime->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('deployment_errors', function (array $errors): bool {
                return collect($errors)->contains(
                    fn (string $error): bool => str_contains($error, 'Guard already deployed for the Night shift on 30 September 2026 at Site A.')
                );
            });
    }

    public function test_guards_without_an_open_duty_stay_on_the_board_and_stale_postings_close(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();

        $offDuty = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'full_name' => 'Off Duty Guard',
            'current_site_id' => null,
        ]);
        $absent = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::Absent,
            'region_id' => $site->region_id,
            'full_name' => 'Absent Guard',
            'current_site_id' => null,
        ]);
        $stale = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'full_name' => 'Stale Posting Guard',
            'current_site_id' => $site->id,
        ]);
        Deployment::factory()->create([
            'guard_id' => $stale->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'shift_type' => DeploymentShiftType::Day,
            'start_date' => '2026-09-01',
        ]);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Off Duty Guard', false)
            ->assertSee('Stale Posting Guard', false)
            ->assertSee('Day: Available', false)
            ->assertDontSee('Absent Guard', false);

        $this->assertTrue($stale->fresh()->currentDeployment()->exists());
        $this->assertSame(OperationalStatus::OnDuty, $stale->fresh()->operational_status);
        $this->assertSame(OperationalStatus::OffDuty, $offDuty->fresh()->operational_status);
        $this->assertSame(OperationalStatus::Absent, $absent->fresh()->operational_status);

        $this->actingAs($ops)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$stale->id],
                'rows' => [
                    $stale->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue(Shift::query()
            ->where('guard_id', $stale->id)
            ->whereDate('shift_date', now()->toDateString())
            ->where('period', 'day')
            ->exists());
    }

    public function test_past_duty_date_lists_guards_free_that_day_even_if_on_duty_today(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_day_guards' => 10,
            'required_night_guards' => 10,
            'required_guards' => 20,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'date_employed' => now()->subMonths(2)->toDateString(),
            'full_name' => 'Historical Free Guard',
        ]);

        Deployment::query()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Rotating->value,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
            'start_date' => now()->toDateString(),
            'end_date' => null,
        ]);

        $pastDate = now()->subDays(5)->toDateString();

        $this->actingAs($ops)
            ->get(route('deployments.board', ['start_date' => $pastDate]))
            ->assertOk()
            ->assertSee('Historical Free Guard', false)
            ->assertSee('Historical duty date', false);

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Historical Free Guard', false)
            ->assertSee('Day: Available', false)
            ->assertSee('Night: Available', false);
    }

    public function test_guards_index_shows_deployed_guard_even_if_status_is_off_duty(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'full_name' => 'Stale Status Guard',
        ]);

        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'shift_type' => DeploymentShiftType::Rotating,
        ]);

        $this->actingAs($ops)
            ->get(route('guards.index'))
            ->assertOk()
            ->assertSee('Stale Status Guard', false);

        $this->assertSame(OperationalStatus::OffDuty, $guard->fresh()->operational_status);
    }

    public function test_deploy_only_validates_selected_guard_rows(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $target = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);
        $other = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$target->id],
                'rows' => [
                    $target->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                    ],
                    $other->id => [
                        'site_id' => '',
                        'shift_type' => DeploymentShiftType::Night->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $target->id,
            'site_id' => $site->id,
            'is_current' => true,
        ]);
    }

    public function test_bulk_deploy_can_allocate_shifts_in_same_step(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $shiftDate = now()->toDateString();

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => $shiftDate,
                'shift_date' => $shiftDate,
                'allocate_shifts' => '1',
                'selected' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('deployments', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => DeploymentStatus::Active->value,
            'is_current' => true,
        ]);

        $this->assertDatabaseHas('shifts', [
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'status' => ShiftStatus::Recorded->value,
            'shift_type' => ShiftType::Normal->value,
        ]);

        $this->assertSame(1, Shift::query()
            ->where('guard_id', $guard->id)
            ->whereDate('shift_date', $shiftDate)
            ->count());
    }

    public function test_posting_board_uses_the_manpower_requirement_effective_on_the_duty_date(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'name' => 'Alpha Warehouse',
            'required_day_guards' => 9,
            'required_night_guards' => 9,
        ]);
        SiteManpowerRequirement::query()->create([
            'site_id' => $site->id,
            'required_total' => 4,
            'required_day' => 2,
            'required_night' => 2,
            'effective_from' => '2026-01-01',
            'effective_to' => '2026-02-28',
            'is_current' => false,
        ]);
        SiteManpowerRequirement::query()->create([
            'site_id' => $site->id,
            'required_total' => 6,
            'required_day' => 3,
            'required_night' => 3,
            'effective_from' => '2026-03-01',
            'effective_to' => null,
            'is_current' => true,
        ]);

        $standing = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'date_employed' => '2025-06-01',
            'current_site_id' => $site->id,
        ]);
        Deployment::factory()->create([
            'guard_id' => $standing->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'start_date' => '2026-03-01',
            'end_date' => null,
            'is_current' => true,
            'is_temporary' => false,
            'duty_type' => ShiftType::Normal,
        ]);
        $overtimeGuard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'date_employed' => '2025-06-01',
            'current_site_id' => $site->id,
        ]);
        Deployment::factory()->create([
            'guard_id' => $overtimeGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_type' => DeploymentShiftType::Night,
            'status' => DeploymentStatus::Active,
            'start_date' => '2026-03-10',
            'end_date' => '2026-03-10',
            'is_current' => false,
            'is_temporary' => true,
            'duty_type' => ShiftType::Overtime,
        ]);
        Shift::factory()->create([
            'guard_id' => $standing->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2026-03-10',
            'starts_at' => '2026-03-10 06:00:00',
            'ends_at' => '2026-03-10 18:00:00',
            'period' => 'day',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);
        Shift::factory()->create([
            'guard_id' => $overtimeGuard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2026-03-10',
            'starts_at' => '2026-03-10 18:00:00',
            'ends_at' => '2026-03-11 06:00:00',
            'period' => 'night',
            'shift_type' => ShiftType::Overtime,
            'status' => ShiftStatus::Recorded,
            'is_overnight' => true,
        ]);

        $awaiting = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'date_employed' => '2025-06-01',
            'current_site_id' => null,
        ]);

        $service = app(ManpowerService::class);
        $january = $service->postingBoardCoverage(collect([$site->fresh()]), '2026-01-15');
        $march = $service->postingBoardCoverage(collect([$site->fresh()]), '2026-03-10');

        $this->assertSame(2, $january[(string) $site->id]['day']['required']);
        $this->assertSame(0, $january[(string) $site->id]['day']['normal']);
        $this->assertSame(3, $march[(string) $site->id]['day']['required']);
        $this->assertSame(1, $march[(string) $site->id]['day']['normal']);
        $this->assertSame(2, $march[(string) $site->id]['day']['remaining']);
        $this->assertSame(2, $march[(string) $site->id]['day']['deficit']);
        $this->assertSame(3, $march[(string) $site->id]['night']['required']);
        $this->assertSame(0, $march[(string) $site->id]['night']['normal']);
        $this->assertSame(1, $march[(string) $site->id]['night']['ot']);
        $this->assertSame(1, $march[(string) $site->id]['night']['operational']);
        $this->assertSame(2, $march[(string) $site->id]['night']['remaining']);
        $this->assertSame(3, $march[(string) $site->id]['night']['deficit']);

        $response = $this->actingAs($ops)
            ->get(route('deployments.board', ['start_date' => '2026-01-15']))
            ->assertOk()
            ->assertSee($awaiting->full_name, false)
            ->assertSee('Manpower', false)
            ->assertSee('data-manpower-indicator', false)
            ->assertSee('psgPaintBoardRow(this)', false)
            ->assertSee('Clear selection', false)
            ->assertSee('data-guard-name', false)
            ->assertSee('psg.posting-board.selection', false)
            ->assertSee('| Selected/Deployed:', false)
            ->assertSee('| Left:', false)
            ->assertSee('| Additional:', false)
            ->assertSee('Manpower requirement already fulfilled', false);

        $payload = $this->boardManpowerFrom($response->getContent());
        $this->assertSame(2, $payload[(string) $site->id]['day']['required']);
        $this->assertSame(2, $payload[(string) $site->id]['night']['required']);

        $marchPage = $this->actingAs($ops)
            ->get(route('deployments.board', ['start_date' => '2026-03-10']))
            ->assertOk();
        $marchPayload = $this->boardManpowerFrom($marchPage->getContent());
        $this->assertSame(3, $marchPayload[(string) $site->id]['day']['required']);
        $this->assertSame(1, $marchPayload[(string) $site->id]['day']['normal']);
        $this->assertSame(1, $marchPayload[(string) $site->id]['night']['ot']);
    }

    public function test_closed_posting_does_not_count_as_deployed_on_todays_board(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'name' => 'Amber Residences Car park',
            'required_day_guards' => 2,
            'required_day_unarmed_guards' => 2,
            'required_day_armed_guards' => 0,
            'required_night_guards' => 2,
            'required_night_unarmed_guards' => 2,
            'required_night_armed_guards' => 0,
            'required_guards' => 4,
        ]);

        foreach ([DeploymentShiftType::Day, DeploymentShiftType::Rotating] as $shiftType) {
            $posted = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::AwaitingDeployment,
                'region_id' => $site->region_id,
                'date_employed' => '2025-02-01',
                'current_site_id' => null,
            ]);
            Deployment::factory()->create([
                'guard_id' => $posted->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'shift_type' => $shiftType,
                'status' => DeploymentStatus::Ended,
                'start_date' => '2025-03-01',
                'end_date' => now()->toDateString(),
                'is_current' => false,
                'is_temporary' => false,
                'duty_type' => ShiftType::Normal,
            ]);
        }

        $coverage = app(ManpowerService::class)->postingBoardCoverage(collect([$site->fresh()]), now()->toDateString());
        $this->assertSame(0, $coverage[(string) $site->id]['day']['normal']);
        $this->assertSame(0, $coverage[(string) $site->id]['day']['operational']);
        $this->assertSame(2, $coverage[(string) $site->id]['day']['deficit']);

        $incoming = collect(range(1, 2))->map(fn () => Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'date_employed' => '2025-02-01',
            'current_site_id' => null,
        ]));

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => $incoming->pluck('id')->all(),
                'rows' => $incoming->mapWithKeys(fn ($guard) => [$guard->id => [
                    'site_id' => $site->id,
                    'shift_type' => DeploymentShiftType::Day->value,
                    'duty_type' => ShiftType::Normal->value,
                ]])->all(),
            ])
            ->assertRedirect()
            ->assertSessionMissing('overstaffing_warnings');

        $this->assertSame(2, Deployment::query()->where('site_id', $site->id)->where('is_current', true)->count());
        Carbon::setTestNow();
    }

    public function test_site_dropdown_lists_only_the_guards_region(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $home = Site::factory()->create([
            'name' => 'Home Region Gate',
            'status' => SiteStatus::Active,
        ]);
        $away = Site::factory()->create([
            'name' => 'Away Region Gate',
            'status' => SiteStatus::Active,
        ]);
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $home->region_id,
            'current_site_id' => null,
        ]);

        $html = $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertDontSee('other region', false)
            ->getContent();

        preg_match('/data-region-options="'.$home->region_id.'".*?<\/template>/s', $html, $homeTemplate);
        preg_match('/data-region-options="'.$away->region_id.'".*?<\/template>/s', $html, $awayTemplate);
        $this->assertNotEmpty($homeTemplate);
        $this->assertStringContainsString('Home Region Gate', $homeTemplate[0]);
        $this->assertStringNotContainsString('Away Region Gate', $homeTemplate[0]);
        $this->assertStringContainsString('Choose site in', $homeTemplate[0]);
        $this->assertNotEmpty($awayTemplate);
        $this->assertStringContainsString('Away Region Gate', $awayTemplate[0]);

        preg_match_all('/name="rows\['.$guard->id.'\]\[site_id\]".*?<\/select>/s', $html, $matches);
        $this->assertCount(2, $matches[0]);

        foreach ($matches[0] as $select) {
            $this->assertStringContainsString('data-region-id="'.$home->region_id.'"', $select);
            $this->assertStringNotContainsString('Away Region Gate', $select);
        }
    }

    public function test_board_page_includes_the_overstaffing_warning(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($ops)
            ->get(route('deployments.board'))
            ->assertOk()
            ->assertSee('Overstaffing warning', false)
            ->assertSee('psgBoardOverstaffing', false)
            ->assertSee('Deploy extra cover', false)
            ->assertSee('Required manpower', false)
            ->assertSee('Currently deployed', false);
    }

    public function test_overstaffed_posting_waits_for_confirmation_then_deploys(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'name' => 'Victoria Fisheries Stores',
            'required_day_guards' => 4,
            'required_day_unarmed_guards' => 4,
            'required_day_armed_guards' => 0,
            'required_night_guards' => 2,
            'required_night_unarmed_guards' => 2,
            'required_night_armed_guards' => 0,
            'required_guards' => 6,
        ]);

        foreach (range(1, 3) as $ignored) {
            $posted = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'region_id' => $site->region_id,
                'date_employed' => '2025-01-01',
                'current_site_id' => $site->id,
            ]);
            Deployment::factory()->create([
                'guard_id' => $posted->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'shift_type' => DeploymentShiftType::Day,
                'status' => DeploymentStatus::Active,
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'is_current' => true,
                'is_temporary' => false,
                'duty_type' => ShiftType::Normal,
            ]);
        }

        $incoming = collect(range(1, 2))->map(fn () => Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'date_employed' => '2025-01-01',
            'current_site_id' => null,
        ]));

        $payload = [
            'start_date' => now()->toDateString(),
            'selected' => $incoming->pluck('id')->all(),
            'rows' => $incoming->mapWithKeys(fn ($guard) => [$guard->id => [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'duty_type' => ShiftType::Normal->value,
            ]])->all(),
        ];

        $this->actingAs($manager)
            ->from(route('deployments.board', ['start_date' => now()->toDateString()]))
            ->post(route('deployments.board.store'), $payload)
            ->assertRedirect(route('deployments.board', ['start_date' => now()->toDateString()]))
            ->assertSessionHas('overstaffing_warnings', function (array $warnings): bool {
                $warning = $warnings[0] ?? [];

                return ($warning['site'] ?? null) === 'Victoria Fisheries Stores'
                    && ($warning['period'] ?? null) === 'Day'
                    && ($warning['required'] ?? null) === 4
                    && ($warning['deployed'] ?? null) === 3
                    && ($warning['selected'] ?? null) === 2
                    && ($warning['projected'] ?? null) === 5
                    && ($warning['excess'] ?? null) === 1;
            });

        $this->assertSame(3, Deployment::query()->where('site_id', $site->id)->count());

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), $payload + ['acknowledge_overstaffing' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(5, Deployment::query()->where('site_id', $site->id)->where('is_current', true)->count());
        Carbon::setTestNow();
    }

    public function test_posting_inside_the_requirement_does_not_ask_for_confirmation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-30 10:00:00'));
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'required_day_guards' => 4,
            'required_day_unarmed_guards' => 4,
            'required_day_armed_guards' => 0,
            'required_night_guards' => 2,
            'required_night_unarmed_guards' => 2,
            'required_night_armed_guards' => 0,
            'required_guards' => 6,
        ]);

        foreach (range(1, 3) as $ignored) {
            $posted = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'region_id' => $site->region_id,
                'date_employed' => '2025-01-01',
                'current_site_id' => $site->id,
            ]);
            Deployment::factory()->create([
                'guard_id' => $posted->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'shift_type' => DeploymentShiftType::Day,
                'status' => DeploymentStatus::Active,
                'start_date' => now()->toDateString(),
                'end_date' => null,
                'is_current' => true,
                'is_temporary' => false,
                'duty_type' => ShiftType::Normal,
            ]);
        }

        $incoming = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'date_employed' => '2025-01-01',
            'current_site_id' => null,
        ]);

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => now()->toDateString(),
                'selected' => [$incoming->id],
                'rows' => [
                    $incoming->id => [
                        'site_id' => $site->id,
                        'shift_type' => DeploymentShiftType::Day->value,
                        'duty_type' => ShiftType::Normal->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionMissing('overstaffing_warnings');

        $this->assertSame(4, Deployment::query()->where('site_id', $site->id)->where('is_current', true)->count());
        Carbon::setTestNow();
    }

    public function test_historical_overstaffing_still_requires_confirmation(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $dutyDate = '2026-08-19';
        $site = Site::factory()->create([
            'name' => 'Victoria Fisheries Stores',
            'required_day_guards' => 4,
            'required_day_unarmed_guards' => 4,
            'required_day_armed_guards' => 0,
            'required_night_guards' => 2,
            'required_night_unarmed_guards' => 2,
            'required_night_armed_guards' => 0,
            'required_guards' => 6,
        ]);

        foreach (range(1, 3) as $ignored) {
            $posted = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'region_id' => $site->region_id,
                'date_employed' => '2025-01-01',
                'current_site_id' => $site->id,
            ]);
            Deployment::factory()->create([
                'guard_id' => $posted->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'shift_type' => DeploymentShiftType::Day,
                'status' => DeploymentStatus::Active,
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-19',
                'is_current' => false,
                'is_temporary' => false,
                'duty_type' => ShiftType::Normal,
            ]);
        }

        $incoming = collect(range(1, 2))->map(fn () => Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'date_employed' => '2025-01-01',
            'current_site_id' => null,
        ]));

        $payload = [
            'start_date' => $dutyDate,
            'selected' => $incoming->pluck('id')->all(),
            'rows' => $incoming->mapWithKeys(fn ($guard) => [$guard->id => [
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'duty_type' => ShiftType::Normal->value,
            ]])->all(),
        ];

        $this->actingAs($manager)
            ->from(route('deployments.board', ['start_date' => $dutyDate]))
            ->post(route('deployments.board.store'), $payload)
            ->assertRedirect(route('deployments.board', ['start_date' => $dutyDate]))
            ->assertSessionHas('overstaffing_warnings');

        $this->assertSame(3, Deployment::query()->where('site_id', $site->id)->count());

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), $payload + ['acknowledge_overstaffing' => '1'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(5, Deployment::query()
            ->where('site_id', $site->id)
            ->whereDate('start_date', '<=', $dutyDate)
            ->where(function ($query) use ($dutyDate): void {
                $query->whereNull('end_date')->orWhereDate('end_date', '>=', $dutyDate);
            })
            ->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function boardManpowerFrom(string $html): array
    {
        $this->assertSame(1, preg_match('/id="board-manpower">(.*?)<\/script>/s', $html, $matches));

        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($decoded);

        return $decoded;
    }
}
