<?php

namespace Tests\Feature\Dashboard;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Dashboards\DashboardStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_statistics_charts_for_all_roles(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->role($role)->create();

            $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->assertSee('id="dashboard-statistics"', false)
                ->assertSee('dashboardCharts', false);
        }
    }

    public function test_finance_role_receives_finance_charts(): void
    {
        $charts = app(DashboardStatisticsService::class)->for(
            User::factory()->role(UserRole::FinanceManager)->create(),
        );

        $this->assertCount(2, $charts);
        $this->assertSame('invoice-status', $charts[0]['id']);
        $this->assertSame('collections-trend', $charts[1]['id']);
    }

    public function test_hr_role_receives_hr_charts(): void
    {
        $charts = app(DashboardStatisticsService::class)->for(
            User::factory()->role(UserRole::HrManager)->create(),
        );

        $this->assertCount(2, $charts);
        $this->assertSame('workforce-status', $charts[0]['id']);
        $this->assertSame('leave-pipeline', $charts[1]['id']);
    }

    public function test_managing_director_receives_operations_and_finance_charts(): void
    {
        $charts = app(DashboardStatisticsService::class)->for(
            User::factory()->managingDirector()->create(),
        );

        $this->assertCount(4, $charts);
        $this->assertSame(
            ['shift-outcomes', 'workforce-status', 'invoice-status', 'collections-trend'],
            array_column($charts, 'id'),
        );
    }

    public function test_operations_role_receives_shift_and_workforce_charts(): void
    {
        $charts = app(DashboardStatisticsService::class)->for(
            User::factory()->role(UserRole::OperationsManager)->create(),
        );

        $this->assertCount(2, $charts);
        $this->assertSame('shift-outcomes', $charts[0]['id']);
        $this->assertSame('workforce-status', $charts[1]['id']);
    }
}
