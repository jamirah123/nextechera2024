<?php

namespace Tests\Feature\Deployments;

use App\Enums\CompensationType;
use App\Enums\DeploymentShiftType;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\PayrollPayslip;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\Finance\PayrollRunService;
use App\Support\Finance\PayrollRates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OvertimeConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_normal_shift_followed_by_another_window_asks_before_saving_overtime(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));
        [$manager, $siteA, $siteB, $guard] = $this->boardFixture('Kaheru Richard', 'PSG001');
        $dutyDate = '2026-09-30';

        $this->postShift($manager, $guard, $siteA, $dutyDate, DeploymentShiftType::Day, ShiftType::Normal)
            ->assertSessionHas('status')
            ->assertSessionMissing('overtime_prompts');

        $this->assertSame(ShiftType::Normal, $this->shiftFor($guard, 'day', $dutyDate)?->shift_type);
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->fresh()->operational_status);

        $this->postShift($manager, $guard, $siteB, $dutyDate, DeploymentShiftType::Night, ShiftType::Normal)
            ->assertSessionHas('overtime_prompts', function (array $prompts) use ($guard, $siteA, $siteB): bool {
                $prompt = $prompts[0] ?? [];

                return ($prompt['guard_id'] ?? null) === $guard->id
                    && ($prompt['employment_id'] ?? null) === 'PSG001'
                    && ($prompt['operational_date'] ?? null) === '30 September 2026'
                    && ($prompt['previous_period'] ?? null) === 'Day'
                    && ($prompt['previous_duty'] ?? null) === 'Normal'
                    && ($prompt['previous_site'] ?? null) === $siteA->name
                    && ($prompt['new_period'] ?? null) === 'Night'
                    && ($prompt['new_site'] ?? null) === $siteB->name;
            })
            ->assertSessionMissing('status');

        $this->assertNull($this->shiftFor($guard, 'night', $dutyDate));
        $this->assertSame(1, Shift::query()->where('guard_id', $guard->id)->count());
    }

    public function test_confirming_overtime_saves_the_second_shift_as_overtime_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));
        [$manager, $siteA, $siteB, $guard] = $this->boardFixture('Kaheru Richard', 'PSG001', 300000);
        $dutyDate = '2026-09-30';

        $this->postShift($manager, $guard, $siteA, $dutyDate, DeploymentShiftType::Day, ShiftType::Normal);
        $this->postShift($manager, $guard, $siteB, $dutyDate, DeploymentShiftType::Night, ShiftType::Overtime, confirmed: true)
            ->assertSessionHas('status')
            ->assertSessionMissing('overtime_prompts');

        $night = $this->shiftFor($guard, 'night', $dutyDate);
        $this->assertNotNull($night);
        $this->assertSame(ShiftType::Overtime, $night->shift_type);
        $this->assertSame(ShiftStatus::Recorded, $night->status);
        $this->assertSame(ShiftType::Normal, $this->shiftFor($guard, 'day', $dutyDate)?->shift_type);
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->fresh()->operational_status);
        $this->assertNull($guard->fresh()->current_site_id);

        $audit = AuditLog::query()->where('action', 'deployment.overtime_confirmed')->first();
        $this->assertNotNull($audit);
        $this->assertSame($manager->id, $audit->actor_id);
        $this->assertSame($dutyDate, $audit->context['operational_date'] ?? null);
        $this->assertSame('Day', $audit->context['previous_period'] ?? null);
        $this->assertSame('overtime', $audit->context['new_duty_type'] ?? null);

        $this->postShift($manager, $guard, $siteB, $dutyDate, DeploymentShiftType::Night, ShiftType::Overtime, confirmed: true)
            ->assertSessionHas('deployment_errors');

        $this->assertSame(1, Shift::query()->where('guard_id', $guard->id)->where('shift_type', ShiftType::Overtime)->count());

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $payroll = app(PayrollRunService::class);
        $run = $payroll->calculate($payroll->createDraft([
            'period_year' => 2026,
            'period_month' => 9,
        ], $finance));
        $payslip = PayrollPayslip::query()->where('payroll_run_id', $run->id)->where('guard_id', $guard->id)->firstOrFail();
        $normalRate = round(300000 / PayrollRates::SHIFT_RATE_DIVISOR, 2);
        $overtimeRate = round($normalRate * (float) config('psg.payroll.overtime_multiplier', 1.5), 2);

        $this->assertSame(1, $payslip->normal_shifts);
        $this->assertSame(1, $payslip->overtime_shifts);
        $this->assertEqualsWithDelta($normalRate + $overtimeRate, (float) $payslip->gross_pay, 0.01);

        $again = $payroll->calculate($run->fresh());
        $payslip = PayrollPayslip::query()->where('payroll_run_id', $again->id)->where('guard_id', $guard->id)->firstOrFail();
        $this->assertSame($again->id, $run->id);
        $this->assertSame(1, PayrollPayslip::query()->where('payroll_run_id', $again->id)->where('guard_id', $guard->id)->count());
        $this->assertSame(1, $payslip->overtime_shifts);
        $this->assertSame(1, $payslip->normal_shifts);
    }

    public function test_a_guard_without_a_normal_shift_keeps_the_selected_duty_type(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));
        [$manager, , $siteB, $guard] = $this->boardFixture('Amina Nakato', 'PSG010');

        $this->postShift($manager, $guard, $siteB, '2026-10-10', DeploymentShiftType::Night, ShiftType::Normal)
            ->assertSessionHas('status')
            ->assertSessionMissing('overtime_prompts');

        $this->assertSame(ShiftType::Normal, $this->shiftFor($guard, 'night', '2026-10-10')?->shift_type);

        $overtimeOnly = Guard::factory()->create([
            'employment_id' => 'PSG011',
            'full_name' => 'Peter Okello',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $siteB->region_id,
            'current_site_id' => null,
            'date_employed' => '2025-01-01',
        ]);
        Shift::factory()->create([
            'guard_id' => $overtimeOnly->id,
            'site_id' => $siteB->id,
            'region_id' => $siteB->region_id,
            'shift_date' => '2026-10-10',
            'starts_at' => '2026-10-10 18:00:00',
            'ends_at' => '2026-10-11 06:00:00',
            'period' => 'night',
            'shift_type' => ShiftType::Overtime,
            'status' => ShiftStatus::Recorded,
            'is_overnight' => true,
        ]);

        $daySite = Site::factory()->create([
            'region_id' => $siteB->region_id,
            'required_day_guards' => 2,
            'required_night_guards' => 2,
            'required_guards' => 4,
        ]);

        $this->postShift($manager, $overtimeOnly, $daySite, '2026-10-10', DeploymentShiftType::Day, ShiftType::Normal)
            ->assertSessionMissing('overtime_prompts')
            ->assertSessionHas('status');

        $this->assertSame(ShiftType::Normal, $this->shiftFor($overtimeOnly, 'day', '2026-10-10')?->shift_type);
    }

    public function test_a_conflicting_shift_is_rejected_even_when_overtime_is_confirmed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));
        [$manager, $siteA, $siteB, $guard] = $this->boardFixture('Grace Namuli', 'PSG020');

        $this->postShift($manager, $guard, $siteA, '2026-10-10', DeploymentShiftType::Day, ShiftType::Normal);

        $this->postShift($manager, $guard, $siteB, '2026-10-10', DeploymentShiftType::Day, ShiftType::Overtime, confirmed: true)
            ->assertSessionHas('deployment_errors');

        $this->assertSame(1, Shift::query()->where('guard_id', $guard->id)->where('period', 'day')->count());
        $this->assertSame(0, Deployment::query()->where('guard_id', $guard->id)->where('site_id', $siteB->id)->count());
    }

    public function test_bulk_posting_confirms_each_guard_independently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $site = Site::factory()->create([
            'name' => 'Site A',
            'required_day_guards' => 4,
            'required_night_guards' => 4,
            'required_guards' => 8,
        ]);
        $other = Site::factory()->create([
            'name' => 'Site B',
            'region_id' => $site->region_id,
            'required_day_guards' => 4,
            'required_night_guards' => 4,
            'required_guards' => 8,
        ]);
        $clear = $this->guardOn($site, 'Guard A', 'PSG101');
        $repeat = $this->guardOn($site, 'Guard B', 'PSG102');
        $blocked = $this->guardOn($site, 'Guard C', 'PSG103');
        $alsoRepeat = $this->guardOn($site, 'Guard D', 'PSG104');

        $this->postShift($manager, $repeat, $site, '2026-10-10', DeploymentShiftType::Day, ShiftType::Normal);
        $this->postShift($manager, $alsoRepeat, $site, '2026-10-10', DeploymentShiftType::Day, ShiftType::Normal);
        Shift::factory()->create([
            'guard_id' => $blocked->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'shift_date' => '2026-10-10',
            'starts_at' => '2026-10-10 18:00:00',
            'ends_at' => '2026-10-11 06:00:00',
            'period' => 'night',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
            'is_overnight' => true,
        ]);

        $rows = [
            $clear->id => ['site_id' => $other->id, 'shift_type' => DeploymentShiftType::Night->value, 'duty_type' => ShiftType::Normal->value],
            $repeat->id => ['site_id' => $other->id, 'shift_type' => DeploymentShiftType::Night->value, 'duty_type' => ShiftType::Normal->value],
            $blocked->id => ['site_id' => $other->id, 'shift_type' => DeploymentShiftType::Night->value, 'duty_type' => ShiftType::Overtime->value],
            $alsoRepeat->id => ['site_id' => $other->id, 'shift_type' => DeploymentShiftType::Night->value, 'duty_type' => ShiftType::Normal->value],
        ];

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => '2026-10-10',
                'selected' => array_keys($rows),
                'rows' => $rows,
            ])
            ->assertSessionHas('overtime_prompts', function (array $prompts) use ($repeat, $alsoRepeat, $clear): bool {
                $ids = collect($prompts)->pluck('guard_id')->all();

                return in_array($repeat->id, $ids, true)
                    && in_array($alsoRepeat->id, $ids, true)
                    && ! in_array($clear->id, $ids, true);
            });

        $this->assertNull($this->shiftFor($clear, 'night', '2026-10-10'));
        $this->assertNull($this->shiftFor($repeat, 'night', '2026-10-10'));

        $this->actingAs($manager)
            ->post(route('deployments.board.store'), [
                'start_date' => '2026-10-10',
                'selected' => array_keys($rows),
                'rows' => $rows,
                'overtime_reviewed' => '1',
                'confirm_overtime' => [$repeat->id],
            ])
            ->assertSessionHas('status')
            ->assertSessionHas('deployment_errors');

        $this->assertSame(ShiftType::Normal, $this->shiftFor($clear, 'night', '2026-10-10')?->shift_type);
        $this->assertSame(ShiftType::Overtime, $this->shiftFor($repeat, 'night', '2026-10-10')?->shift_type);
        $this->assertNull($this->shiftFor($alsoRepeat, 'night', '2026-10-10'));
        $this->assertSame(1, Shift::query()->where('guard_id', $blocked->id)->where('period', 'night')->count());
    }

    public function test_backdated_posting_checks_the_selected_duty_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00'));
        [$manager, $siteA, $siteB, $guard] = $this->boardFixture('Kaheru Richard', 'PSG001');

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $siteA->id,
            'region_id' => $siteA->region_id,
            'shift_date' => '2026-10-01',
            'starts_at' => '2026-10-01 06:00:00',
            'ends_at' => '2026-10-01 18:00:00',
            'period' => 'day',
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Recorded,
        ]);

        $this->postShift($manager, $guard, $siteB, '2026-09-30', DeploymentShiftType::Night, ShiftType::Normal)
            ->assertSessionMissing('overtime_prompts')
            ->assertSessionHas('status');

        $this->assertSame(ShiftType::Normal, $this->shiftFor($guard, 'night', '2026-09-30')?->shift_type);
        $this->assertSame(OperationalStatus::AwaitingDeployment, $guard->fresh()->operational_status);
    }

    public function test_unauthorised_users_cannot_skip_overtime_confirmation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-10 10:00:00'));
        [$manager, $siteA, $siteB, $guard] = $this->boardFixture('Kaheru Richard', 'PSG001');
        $this->postShift($manager, $guard, $siteA, '2026-10-10', DeploymentShiftType::Day, ShiftType::Normal);

        $finance = User::factory()->role(UserRole::FinanceManager)->create();
        $this->actingAs($finance)
            ->from(route('deployments.board'))
            ->post(route('deployments.board.store'), [
                'start_date' => '2026-10-10',
                'selected' => [$guard->id],
                'overtime_reviewed' => '1',
                'confirm_overtime' => [$guard->id],
                'rows' => [
                    $guard->id => [
                        'site_id' => $siteB->id,
                        'shift_type' => DeploymentShiftType::Night->value,
                        'duty_type' => ShiftType::Overtime->value,
                    ],
                ],
            ])
            ->assertRedirect(route('deployments.board'))
            ->assertSessionHas('error', 'You do not have permission to perform this action.');

        $this->postShift($manager, $guard, $siteB, '2026-10-10', DeploymentShiftType::Night, ShiftType::Overtime)
            ->assertSessionHas('overtime_prompts');

        $this->assertNull($this->shiftFor($guard, 'night', '2026-10-10'));
    }

    /**
     * @return array{0: User, 1: Site, 2: Site, 3: Guard}
     */
    private function boardFixture(string $name, string $employmentId, float $salary = 180000): array
    {
        $manager = User::factory()->role(UserRole::ShiftManager)->create();
        $siteA = Site::factory()->create([
            'name' => 'Site A',
            'required_day_guards' => 3,
            'required_night_guards' => 3,
            'required_guards' => 6,
        ]);
        $siteB = Site::factory()->create([
            'name' => 'Site B',
            'region_id' => $siteA->region_id,
            'required_day_guards' => 3,
            'required_night_guards' => 3,
            'required_guards' => 6,
        ]);
        $guard = Guard::factory()->create([
            'employment_id' => $employmentId,
            'full_name' => $name,
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $siteA->region_id,
            'current_site_id' => null,
            'date_employed' => '2025-01-01',
            'compensation_type' => CompensationType::Shift,
            'base_shift_rate' => $salary,
            'overtime_shift_rate' => 0,
        ]);

        return [$manager, $siteA, $siteB, $guard];
    }

    private function guardOn(Site $site, string $name, string $employmentId): Guard
    {
        return Guard::factory()->create([
            'employment_id' => $employmentId,
            'full_name' => $name,
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'region_id' => $site->region_id,
            'current_site_id' => null,
            'date_employed' => '2025-01-01',
            'compensation_type' => CompensationType::Shift,
            'base_shift_rate' => 180000,
            'overtime_shift_rate' => 0,
        ]);
    }

    private function postShift(
        User $manager,
        Guard $guard,
        Site $site,
        string $date,
        DeploymentShiftType $shiftType,
        ShiftType $dutyType,
        bool $confirmed = false,
    ) {
        $payload = [
            'start_date' => $date,
            'selected' => [$guard->id],
            'rows' => [
                $guard->id => [
                    'site_id' => $site->id,
                    'shift_type' => $shiftType->value,
                    'duty_type' => $dutyType->value,
                ],
            ],
        ];

        if ($confirmed) {
            $payload['overtime_reviewed'] = '1';
            $payload['confirm_overtime'] = [$guard->id];
        }

        return $this->actingAs($manager)->post(route('deployments.board.store'), $payload);
    }

    private function shiftFor(Guard $guard, string $period, string $date): ?Shift
    {
        return Shift::query()
            ->where('guard_id', $guard->id)
            ->where('period', $period)
            ->whereDate('shift_date', $date)
            ->first();
    }
}
