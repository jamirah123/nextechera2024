<?php

namespace Tests\Feature\Organization;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\ManpowerMonitorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManpowerMonitorTest extends TestCase
{
    use RefreshDatabase;

    public function test_overtime_cover_keeps_the_normal_manpower_deficit_visible(): void
    {
        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'name' => 'Western Site 2',
            'required_guards' => 2,
            'required_day_guards' => 0,
            'required_night_guards' => 2,
        ]);

        $this->postGuard($site, DeploymentShiftType::Night);
        $this->postGuard($site, DeploymentShiftType::Night, temporary: true);

        $date = now()->toDateString();
        $overview = app(ManpowerMonitorService::class)->overview($site->region_id, $date);

        $this->assertSame(2, $overview['required']);
        $this->assertSame(1, $overview['normal']);
        $this->assertSame(1, $overview['ot']);
        $this->assertSame(2, $overview['operational']);
        $this->assertSame(0, $overview['remaining']);
        $this->assertSame(1, $overview['deficit']);
        $this->assertSame(1, $overview['ot_sites']);
        $this->assertSame(0, $overview['shortage_sites']);

        $rows = app(ManpowerMonitorService::class)->siteReport([
            'date' => $date,
            'site_id' => $site->id,
            'period' => 'night',
        ]);

        $this->assertSame('OT Supported', $rows[0]['status']);
        $this->assertSame(0, $rows[0]['remaining']);
        $this->assertSame(1, $rows[0]['deficit']);

        $this->actingAs($ops)
            ->get(route('manpower.coverage'))
            ->assertOk()
            ->assertSee('Company manpower')
            ->assertSee('OT Supported')
            ->assertSee('Western Site 2');

        $this->actingAs($ops)
            ->get(route('manpower.deficit-report', ['site_id' => $site->id, 'period' => 'night']))
            ->assertOk()
            ->assertSee('Operational coverage')
            ->assertSee('OT Supported')
            ->assertSee('Western Site 2');
    }

    public function test_partial_overtime_is_reported_as_a_shortage_and_a_larger_deficit(): void
    {
        $site = Site::factory()->create([
            'required_guards' => 4,
            'required_day_guards' => 0,
            'required_night_guards' => 4,
        ]);

        $this->postGuard($site, DeploymentShiftType::Night);
        $this->postGuard($site, DeploymentShiftType::Night);
        $this->postGuard($site, DeploymentShiftType::Night, temporary: true);

        $rows = app(ManpowerMonitorService::class)->siteReport([
            'date' => now()->toDateString(),
            'site_id' => $site->id,
            'period' => 'night',
        ]);

        $this->assertSame(4, $rows[0]['required']);
        $this->assertSame(2, $rows[0]['normal']);
        $this->assertSame(1, $rows[0]['ot']);
        $this->assertSame(3, $rows[0]['deployed']);
        $this->assertSame(1, $rows[0]['remaining']);
        $this->assertSame(2, $rows[0]['deficit']);
        $this->assertSame('Shortage', $rows[0]['status']);
    }

    public function test_repeated_overtime_flags_the_guard_for_operations(): void
    {
        config(['psg.manpower.monitor.max_consecutive_ot' => 3]);

        $site = Site::factory()->create(['name' => 'Alpha Warehouse']);
        $guard = Guard::factory()->create([
            'employment_id' => 'PSG001',
            'full_name' => 'Kaheru Richard',
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => OperationalStatus::OffDuty,
            'region_id' => $site->region_id,
            'current_site_id' => null,
        ]);

        foreach (range(0, 2) as $offset) {
            $start = now()->subDays(2 - $offset)->setTime(18, 0);
            Shift::factory()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_date' => $start->toDateString(),
                'starts_at' => $start,
                'ends_at' => $start->copy()->addHours(12),
                'period' => ShiftPeriod::Night,
                'shift_type' => ShiftType::Overtime,
                'status' => ShiftStatus::Recorded,
                'is_overnight' => true,
            ]);
        }

        $flags = app(ManpowerMonitorService::class)->guardFlags(now()->toDateString(), $site->region_id);

        $this->assertSame('PSG001', $flags[0]['code']);
        $this->assertSame('Kaheru Richard', $flags[0]['guard']);
        $this->assertGreaterThanOrEqual(3, $flags[0]['consecutive_ot']);

        app(ManpowerMonitorService::class)->scanAlerts();

        $alert = AuditLog::query()
            ->where('action', 'manpower.repeated_ot')
            ->where('subject_type', $guard->getMorphClass())
            ->where('subject_id', $guard->id)
            ->first();
        $this->assertNotNull($alert);
        $this->assertStringContainsString('PSG001 — Kaheru Richard has been used for repeated overtime coverage', $alert->summary);
    }

    public function test_deficit_report_paginates_each_shift_row(): void
    {
        config(['psg.pagination.per_page' => 1]);

        $ops = User::factory()->role(UserRole::OperationsManager)->create();
        $site = Site::factory()->create([
            'name' => 'Paged Gate',
            'required_guards' => 2,
            'required_day_guards' => 1,
            'required_night_guards' => 1,
        ]);

        $this->actingAs($ops)
            ->get(route('manpower.deficit-report', ['site_id' => $site->id]))
            ->assertOk()
            ->assertSee('Showing 1–1 of 2')
            ->assertSee('Paged Gate');

        $this->actingAs($ops)
            ->get(route('manpower.deficit-report', ['site_id' => $site->id, 'page' => 2]))
            ->assertOk()
            ->assertSee('Showing 2–2 of 2')
            ->assertSee('Paged Gate');
    }

    public function test_super_admin_saves_monitoring_thresholds(): void
    {
        $admin = User::factory()->role(UserRole::SuperAdmin)->create();
        $shiftManager = User::factory()->role(UserRole::ShiftManager)->create();

        $this->actingAs($admin)
            ->get(route('manpower.coverage'))
            ->assertOk()
            ->assertSee('Monitoring thresholds');

        $this->actingAs($admin)
            ->post(route('manpower.monitor.update'), [
                'period_days' => 10,
                'min_rest_hours' => 12,
                'max_consecutive_shifts' => 5,
                'max_consecutive_ot' => 2,
                'max_ot_shifts' => 4,
                'max_hours' => 60,
                'site_ot_shift_alert' => 8,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $rules = app(ManpowerMonitorService::class)->rules();
        $this->assertSame(10, $rules['period_days']);
        $this->assertSame(12, $rules['min_rest_hours']);
        $this->assertSame(8, $rules['site_ot_shift_alert']);

        $this->actingAs($shiftManager)
            ->from(route('manpower.coverage'))
            ->post(route('manpower.monitor.update'), [
                'period_days' => 10,
                'min_rest_hours' => 12,
                'max_consecutive_shifts' => 5,
                'max_consecutive_ot' => 2,
                'max_ot_shifts' => 4,
                'max_hours' => 60,
                'site_ot_shift_alert' => 8,
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'You do not have permission to perform this action.');
    }

    private function postGuard(Site $site, DeploymentShiftType $shift, bool $temporary = false): Guard
    {
        $guard = Guard::factory()->create([
            'employment_status' => EmploymentStatus::Active,
            'operational_status' => $temporary ? OperationalStatus::OffDuty : OperationalStatus::OnDuty,
            'region_id' => $site->region_id,
            'current_site_id' => $temporary ? null : $site->id,
        ]);

        Deployment::factory()->create([
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => $shift,
            'status' => DeploymentStatus::Active,
            'is_current' => true,
            'is_temporary' => $temporary,
            'duty_type' => $temporary ? ShiftType::Overtime : ShiftType::Normal,
            'start_date' => Carbon::today()->toDateString(),
        ]);

        return $guard;
    }
}
