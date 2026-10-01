<?php

namespace Tests\Feature\Performance;

use App\Enums\UserRole;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Services\Dashboards\DashboardStatisticsService;
use App\Services\NotificationFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HotPathQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_charts_use_grouped_queries_for_thousands_of_shifts(): void
    {
        Cache::flush();

        $director = User::factory()->role(UserRole::ManagingDirector)->create();
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
        ]);

        $rows = [];
        $now = now()->toDateTimeString();

        for ($i = 0; $i < 2000; $i++) {
            $day = now()->subDays($i % 7)->toDateString();
            $rows[] = [
                'reference' => 'SHF-PERF-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'shift_date' => $day,
                'starts_at' => $day.' 06:00:00',
                'ends_at' => $day.' 18:00:00',
                'period' => 'day',
                'shift_type' => 'normal',
                'status' => $i % 8 === 0 ? 'missed' : 'completed',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Shift::query()->insert($chunk);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $charts = app(DashboardStatisticsService::class)->for($director);
        $queries = DB::getQueryLog();

        $shiftQueries = array_values(array_filter(
            $queries,
            fn (array $query) => str_contains($query['query'], 'shifts'),
        ));

        $this->assertCount(1, $shiftQueries);
        $this->assertStringNotContainsString('date(', strtolower($shiftQueries[0]['query']));
        $this->assertLessThanOrEqual(8, count($queries));

        $outcomes = collect($charts)->firstWhere('id', 'shift-outcomes');
        $this->assertNotNull($outcomes);
        $this->assertCount(7, $outcomes['labels']);
        $this->assertGreaterThan(1000, array_sum($outcomes['datasets'][0]['data']));

        DB::flushQueryLog();
        app(DashboardStatisticsService::class)->for($director);
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_notification_feed_looks_up_the_user_rows_first(): void
    {
        $hr = User::factory()->role(UserRole::HrManager)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();
        app(NotificationFeedService::class)->feed($hr);

        $list = collect(DB::getQueryLog())->first(function (array $query): bool {
            $sql = strtolower($query['query']);

            return str_contains($sql, 'from `audit_logs`') || str_contains($sql, 'from "audit_logs"');
        });

        $this->assertNotNull($list);
        $sql = strtolower($list['query']);
        $this->assertStringContainsString('notification_states', $sql);
        $this->assertStringContainsString('audit_log_id', $sql);
        $this->assertStringContainsString('in (select', $sql);
    }
}
