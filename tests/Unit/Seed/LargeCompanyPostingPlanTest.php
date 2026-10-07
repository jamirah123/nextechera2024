<?php

namespace Tests\Unit\Seed;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class LargeCompanyPostingPlanTest extends TestCase
{
    public function test_postings_stay_within_each_sites_day_and_night_requirement(): void
    {
        config(['psg.seed.start_date' => '2023-01-01']);
        Carbon::setTestNow('2023-04-01');

        $site = [
            'id' => 10,
            'region_id' => 1,
            'supervisor_id' => null,
            'opened' => '2023-01-01',
            'client_id' => 1,
            'day' => 1,
            'night' => 2,
        ];
        $guards = [];
        for ($id = 1; $id <= 8; $id++) {
            $guards[] = [
                'id' => $id,
                'region_id' => 1,
                'hired' => '2023-01-01',
                'left' => null,
                'active' => true,
            ];
        }

        $plans = $this->plans($guards, [$site]);

        $this->assertNotEmpty($plans);
        $this->assertLessThanOrEqual(1, $this->peak($plans, 10, 'day'));
        $this->assertLessThanOrEqual(2, $this->peak($plans, 10, 'night'));
        $this->assertSame(1, $this->peakForGuard($plans));

        $current = array_filter($plans, fn (array $plan): bool => $plan['is_current'] && $plan['shift_type'] === 'day');
        $this->assertCount(1, $current);
        $currentNight = array_filter($plans, fn (array $plan): bool => $plan['is_current'] && $plan['shift_type'] === 'night');
        $this->assertCount(2, $currentNight);
    }

    public function test_a_guard_is_not_posted_before_hire_or_after_leaving(): void
    {
        config(['psg.seed.start_date' => '2023-01-01']);
        Carbon::setTestNow('2023-06-01');

        $site = [
            'id' => 4,
            'region_id' => 2,
            'supervisor_id' => 9,
            'opened' => '2023-01-15',
            'client_id' => 1,
            'day' => 1,
            'night' => 1,
        ];
        $guards = [
            [
                'id' => 3,
                'region_id' => 2,
                'hired' => '2023-02-01',
                'left' => '2023-03-15',
                'active' => false,
            ],
        ];

        $plans = $this->plans($guards, [$site]);

        $this->assertNotEmpty($plans);
        foreach ($plans as $plan) {
            $this->assertGreaterThanOrEqual('2023-02-01', $plan['start_date']);
            $this->assertGreaterThanOrEqual('2023-01-15', $plan['start_date']);
            $this->assertLessThanOrEqual('2023-03-15', $plan['work_end']);
            $this->assertFalse($plan['is_current']);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $guards
     * @param  list<array<string, mixed>>  $sites
     * @return list<array<string, mixed>>
     */
    private function plans(array $guards, array $sites): array
    {
        $method = new ReflectionMethod(DatabaseSeeder::class, 'deploymentPlans');
        $method->setAccessible(true);

        return $method->invoke(new DatabaseSeeder, $guards, $sites);
    }

    /** @param  list<array<string, mixed>>  $plans */
    private function peak(array $plans, int $siteId, string $period): int
    {
        $events = [];
        foreach ($plans as $plan) {
            if ((int) $plan['site_id'] !== $siteId || $plan['shift_type'] !== $period) {
                continue;
            }
            $events[] = [$plan['start_date'], 1];
            $events[] = [(new \DateTimeImmutable($plan['work_end']))->modify('+1 day')->format('Y-m-d'), -1];
        }

        return $this->peakEvents($events);
    }

    /** @param  list<array<string, mixed>>  $plans */
    private function peakForGuard(array $plans): int
    {
        $byGuard = [];
        foreach ($plans as $plan) {
            $byGuard[$plan['guard_id']][] = [$plan['start_date'], 1];
            $byGuard[$plan['guard_id']][] = [(new \DateTimeImmutable($plan['work_end']))->modify('+1 day')->format('Y-m-d'), -1];
        }
        $peak = 0;
        foreach ($byGuard as $events) {
            $peak = max($peak, $this->peakEvents($events));
        }

        return $peak;
    }

    /** @param  list<array{0: string, 1: int}>  $events */
    private function peakEvents(array $events): int
    {
        usort($events, fn (array $left, array $right): int => [$left[0], $left[1]] <=> [$right[0], $right[1]]);
        $running = 0;
        $peak = 0;
        foreach ($events as $event) {
            $running += $event[1];
            $peak = max($peak, $running);
        }

        return $peak;
    }
}
