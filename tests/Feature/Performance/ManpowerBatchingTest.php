<?php

namespace Tests\Feature\Performance;

use App\Enums\CoverageStatus;
use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\SiteStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Site;
use App\Services\ManpowerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManpowerBatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_sites_matches_for_site_and_uses_few_queries(): void
    {
        $sites = Site::factory()->count(3)->create([
            'status' => SiteStatus::Active,
            'required_guards' => 2,
            'required_day_guards' => 1,
            'required_night_guards' => 1,
        ]);

        foreach ($sites as $index => $site) {
            $guard = Guard::factory()->create(['region_id' => $site->region_id]);
            Deployment::query()->create([
                'guard_id' => $guard->id,
                'site_id' => $site->id,
                'region_id' => $site->region_id,
                'supervisor_id' => $site->supervisor_id,
                'shift_type' => $index % 2 === 0 ? DeploymentShiftType::Day->value : DeploymentShiftType::Night->value,
                'status' => DeploymentStatus::Active->value,
                'start_date' => now()->toDateString(),
                'is_current' => true,
            ]);
        }

        $service = app(ManpowerService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $batched = $service->forSites($sites);
        $batchQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(5, $batchQueries, 'Batching should load coverage in a handful of queries.');

        foreach ($sites as $site) {
            $freshService = new ManpowerService;
            $single = $freshService->forSite($site);
            $fromBatch = $batched->get($site->id);

            $this->assertNotNull($fromBatch);
            $this->assertSame($single['deployed'], $fromBatch['deployed']);
            $this->assertSame($single['deployed_day'], $fromBatch['deployed_day']);
            $this->assertSame($single['deployed_night'], $fromBatch['deployed_night']);
            $this->assertSame($single['shortage'], $fromBatch['shortage']);
            $this->assertSame($single['status'], $fromBatch['status']);
        }

        $this->assertTrue(
            $batched->contains(fn (array $snap) => $snap['status'] === CoverageStatus::Understaffed)
        );
    }
}
