<?php

namespace Tests\Feature\Seed;

use App\Models\Guard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReplaceSeededDatabaseCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_refuses_without_explicit_authorization(): void
    {
        $this->app['env'] = 'production';
        config(['psg.seed.allow_production' => false]);

        $this->artisan('psg:replace-seeded-database')
            ->assertFailed()
            ->expectsOutputToContain('Production replacement is refused');

        $this->assertSame(0, Guard::query()->count());
    }

    public function test_refuses_when_the_checked_out_branch_is_not_the_seed_branch(): void
    {
        config(['psg.seed.replace_branch' => 'seed/not-this-branch']);

        $this->artisan('psg:replace-seeded-database')
            ->assertFailed()
            ->expectsOutputToContain('Replacement runs only from the seed/not-this-branch branch');

        $this->assertSame(0, Guard::query()->count());
    }

    public function test_yes_is_not_enough_to_replace_the_database(): void
    {
        config(['psg.seed.replace_branch' => $this->checkedOutBranch()]);

        $this->artisan('psg:replace-seeded-database')
            ->expectsQuestion('Type the database name shown above to confirm it is the intended Platinum Security database', ':memory:')
            ->expectsQuestion('Type REPLACE-DATABASE to continue', 'yes')
            ->assertFailed()
            ->expectsOutputToContain('Confirmation was not the exact required phrase');

        $this->assertSame(0, Guard::query()->count());
    }

    public function test_a_different_database_name_stops_the_replacement(): void
    {
        config(['psg.seed.replace_branch' => $this->checkedOutBranch()]);

        $this->artisan('psg:replace-seeded-database')
            ->expectsQuestion('Type the database name shown above to confirm it is the intended Platinum Security database', 'some_other_database')
            ->assertFailed()
            ->expectsOutputToContain('The database name did not match');

        $this->assertSame(0, Guard::query()->count());
    }

    private function checkedOutBranch(): string
    {
        $process = new Process(['git', 'branch', '--show-current'], base_path());
        $process->run();

        return trim($process->getOutput());
    }
}
