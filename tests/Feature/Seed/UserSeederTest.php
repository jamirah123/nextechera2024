<?php

namespace Tests\Feature\Seed;

use App\Models\Guard;
use App\Models\Region;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_writes_only_the_head_office_users(): void
    {
        config(['psg.seed.mode' => 'off']);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, User::query()->count());
        $this->assertSame(0, Region::query()->count());
        $this->assertSame(0, Guard::query()->count());
        $this->assertTrue(Hash::check('Password@123', (string) User::query()->where('email', 'admin@platinumsecurity.local')->value('password')));
    }

    public function test_existing_user_is_left_unchanged(): void
    {
        $admin = User::factory()->create([
            'name' => 'Existing Admin',
            'email' => 'admin@platinumsecurity.local',
            'password' => 'OtherPassword@1',
        ]);

        config(['psg.seed.mode' => 'off']);

        $this->seed(DatabaseSeeder::class);

        $admin->refresh();
        $this->assertSame('Existing Admin', $admin->name);
        $this->assertTrue(Hash::check('OtherPassword@1', (string) $admin->password));
        $this->assertSame(7, User::query()->count());
    }

    public function test_production_refuses_the_user_seed(): void
    {
        $this->app['env'] = 'production';
        config(['psg.seed.allow_production' => false]);

        (new DatabaseSeeder)->run();

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Guard::query()->count());
    }

    public function test_load_mode_leaves_an_existing_company_unchanged(): void
    {
        config([
            'psg.seed.mode' => 'load',
            'psg.seed.start_date' => '2023-01-01',
            'psg.seed.resume' => false,
            'psg.seed.allow_production' => false,
        ]);

        Region::factory()->create([
            'code' => 'KLA',
            'name' => 'Kampala',
        ]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, Region::query()->count());
        $this->assertSame(0, Guard::query()->count());
        $this->assertSame(7, User::query()->count());
    }
}
