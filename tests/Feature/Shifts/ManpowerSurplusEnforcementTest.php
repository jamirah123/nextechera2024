<?php

namespace Tests\Feature\Shifts;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Tests\TestCase;

class ManpowerSurplusEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_third_day_shift_is_blocked_when_site_requires_two(): void
    {
        [$site, $guards] = $this->siteWithDayRequirement(2, 3);

        foreach ($guards->take(2) as $guard) {
            app(ShiftService::class)->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'shift_date' => now()->toDateString(),
                'start_time' => '06:00',
                'end_time' => '18:00',
                'period' => ShiftPeriod::Day->value,
                'shift_type' => ShiftType::Normal->value,
                'guard_classification' => GuardClassification::Unarmed->value,
                'acknowledge_warnings' => true,
            ]);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Day requirement is 2');

        app(ShiftService::class)->create([
            'guard_id' => $guards[2]->id,
            'site_id' => $site->id,
            'shift_date' => now()->toDateString(),
            'start_time' => '06:00',
            'end_time' => '18:00',
            'period' => ShiftPeriod::Day->value,
            'shift_type' => ShiftType::Normal->value,
            'guard_classification' => GuardClassification::Unarmed->value,
            'acknowledge_warnings' => true,
        ]);
    }

    public function test_allocate_board_rejects_day_surplus(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        [$site, $guards] = $this->siteWithDayRequirement(2, 3);

        $deployments = $guards->map(fn (Guard $guard) => Deployment::query()
            ->current()
            ->where('guard_id', $guard->id)
            ->firstOrFail());

        foreach ($deployments->take(2) as $deployment) {
            app(ShiftService::class)->create([
                'guard_id' => $deployment->guard_id,
                'site_id' => $site->id,
                'shift_date' => now()->toDateString(),
                'start_time' => '06:00',
                'end_time' => '18:00',
                'period' => ShiftPeriod::Day->value,
                'shift_type' => ShiftType::Normal->value,
                'guard_classification' => GuardClassification::Unarmed->value,
                'acknowledge_warnings' => true,
            ]);
        }

        $third = $deployments[2];

        $this->actingAs($manager)
            ->post(route('shifts.allocate.store'), [
                'shift_date' => now()->toDateString(),
                'selected' => [$third->id],
                'rows' => [
                    $third->id => [
                        'period' => ShiftPeriod::Day->value,
                        'shift_type' => ShiftType::Normal->value,
                        'guard_classification' => GuardClassification::Unarmed->value,
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('allocation_errors');

        $this->assertSame(2, Shift::query()->where('site_id', $site->id)->blocking()->count());
    }

    public function test_cannot_deploy_third_day_posting_when_site_requires_two(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'required_guards' => 4,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
        ]);

        foreach (range(1, 2) as $i) {
            $guard = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::AwaitingDeployment,
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
        }

        $extra = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
        ]);

        $this->actingAs($ops)
            ->from(route('deployments.create'))
            ->post(route('deployments.store'), [
                'guard_id' => $extra->id,
                'site_id' => $site->id,
                'shift_type' => DeploymentShiftType::Day->value,
                'start_date' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('deployment');

        $this->assertSame(2, Deployment::query()->current()->where('site_id', $site->id)->count());
    }

    public function test_shifts_index_flags_overstaffed_site(): void
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'name' => 'Overstaff Flag Site',
            'required_guards' => 2,
            'required_day_guards' => 2,
            'required_night_guards' => 0,
        ]);

        foreach (range(1, 3) as $i) {
            $guard = Guard::factory()->create([
                'employment_status' => EmploymentStatus::Active,
                'operational_status' => OperationalStatus::OnDuty,
                'region_id' => $site->region_id,
                'current_site_id' => $site->id,
            ]);
            Deployment::factory()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => DeploymentShiftType::Day,
                'status' => DeploymentStatus::Active,
                'is_current' => true,
            ]);
            Shift::factory()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_date' => now()->toDateString(),
                'period' => ShiftPeriod::Day,
                'status' => ShiftStatus::InProgress,
            ]);
        }

        $this->actingAs($manager)
            ->get(route('shifts.index', ['date' => now()->toDateString()]))
            ->assertOk()
            ->assertSee('Overstaffed', false)
            ->assertSee('3/2', false)
            ->assertSee('deployed', false);
    }

    /** @return array{0: Site, 1: Collection<int, Guard>} */
    private function siteWithDayRequirement(int $requiredDay, int $guardCount): array
    {
        $site = Site::factory()->create([
            'required_guards' => $requiredDay + 2,
            'required_day_guards' => $requiredDay,
            'required_night_guards' => 2,
        ]);

        $guards = collect();
        for ($i = 0; $i < $guardCount; $i++) {
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
                'shift_type' => DeploymentShiftType::Day,
                'status' => DeploymentStatus::Active,
                'is_current' => true,
            ]);
            $guards->push($guard);
        }

        return [$site, $guards];
    }
}
