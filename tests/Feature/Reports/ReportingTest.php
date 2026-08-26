<?php

namespace Tests\Feature\Reports;

use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_roles_can_open_reports_hub(): void
    {
        $user = User::factory()->role(UserRole::OperationsManager)->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Reports hub');
    }

    public function test_monthly_shift_summary_counts_completed_normal_and_overtime(): void
    {
        $user = User::factory()->role(UserRole::FinanceManager)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'region_id' => $site->region_id,
            'site_id' => $site->id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Completed,
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'region_id' => $site->region_id,
            'site_id' => $site->id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'shift_type' => ShiftType::Overtime,
            'status' => ShiftStatus::Completed,
            'reference' => 'SHF-'.now()->format('Ymd').'-9999',
        ]);

        Shift::factory()->create([
            'guard_id' => $guard->id,
            'region_id' => $site->region_id,
            'site_id' => $site->id,
            'supervisor_id' => $site->supervisor_id,
            'shift_date' => now()->toDateString(),
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Scheduled,
            'reference' => 'SHF-'.now()->format('Ymd').'-8888',
        ]);

        $this->actingAs($user)
            ->get(route('reports.monthly-shifts', [
                'year' => now()->year,
                'month' => now()->month,
            ]))
            ->assertOk()
            ->assertSee($guard->full_name);

        $this->actingAs($user)
            ->get(route('reports.monthly-shifts.export', [
                'year' => now()->year,
                'month' => now()->month,
                'format' => 'csv',
            ]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_all_reports_support_csv_export(): void
    {
        $user = User::factory()->role(UserRole::SuperAdmin)->create();

        $routes = [
            'reports.monthly-shifts.export',
            'reports.daily-shifts.export',
            'reports.weekly-shifts.export',
            'reports.guards.export',
            'reports.deployments.export',
            'reports.hr.export',
        ];

        foreach ($routes as $route) {
            $this->actingAs($user)
                ->get(route($route, ['format' => 'csv']))
                ->assertOk()
                ->assertHeader('content-disposition');
        }
    }
}
