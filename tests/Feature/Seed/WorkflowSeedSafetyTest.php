<?php

namespace Tests\Feature\Seed;

use App\Models\Guard;
use App\Models\PayrollRun;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WorkflowOperationsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowSeedSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_mode_off_writes_nothing(): void
    {
        config(['psg.seed.mode' => 'off']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Region::query()->count());
        $this->assertSame(0, Guard::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_existing_company_is_left_unchanged_in_load_mode(): void
    {
        config([
            'psg.seed.mode' => 'load',
            'psg.seed.start_date' => '2025-01-01',
            'psg.seed.resume' => false,
            'psg.seed.allow_production' => false,
        ]);

        Region::factory()->create([
            'code' => 'KLA',
            'name' => 'Kampala',
        ]);

        $this->seed(WorkflowOperationsSeeder::class);

        $this->assertSame(1, Region::query()->count());
        $this->assertSame(0, Guard::query()->count());
        $this->assertSame(0, PayrollRun::query()->count());
        $this->assertSame(0, User::query()->count());
    }

    public function test_production_refuses_the_load_dataset(): void
    {
        $this->app['env'] = 'production';
        config([
            'psg.seed.mode' => 'load',
            'psg.seed.start_date' => '2025-01-01',
            'psg.seed.allow_production' => false,
        ]);

        (new DatabaseSeeder)->run();

        $this->assertSame(0, Region::query()->count());
        $this->assertSame(0, Guard::query()->count());
    }
}
