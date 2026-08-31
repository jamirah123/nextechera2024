<?php

namespace Database\Seeders;

use App\Enums\AbsenceReason;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\User;
use App\Services\AbsenceService;
use App\Services\DesertionService;
use App\Services\LeaveService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $this->seedLeaves();
        $this->seedAbsences();
        $this->seedDesertions();

        $this->call(StaffSeeder::class);

        Auth::logout();
    }

    private function seedLeaves(): void
    {
        $service = app(LeaveService::class);
        $limit = max(10, (int) config('psg.seed.leaves', 200));
        $guards = Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->where('operational_status', '!=', OperationalStatus::Deserted)
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        $created = 0;
        foreach ($guards as $index => $guard) {
            $start = now()->addDays(7 + ($index % 40));
            $end = (clone $start)->addDays(fake()->numberBetween(2, 7));

            try {
                $service->create([
                    'guard_id' => $guard->id,
                    'leave_type' => fake()->randomElement(LeaveType::cases())->value,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'expected_return_date' => $end->copy()->addDay()->toDateString(),
                    'reason' => 'Volume seeded leave request',
                    'status' => fake()->randomElement([
                        LeaveStatus::Pending->value,
                        LeaveStatus::Approved->value,
                        LeaveStatus::Pending->value,
                    ]),
                    'notes' => 'Seeded for HR module testing',
                ]);
                $created++;
            } catch (\Throwable) {
                // Ignore conflicts.
            }
        }

        $this->command?->info("Leaves created: {$created}");
    }

    private function seedAbsences(): void
    {
        $service = app(AbsenceService::class);
        $limit = max(10, (int) config('psg.seed.absences', 300));
        $guards = Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereNotNull('current_site_id')
            ->where('operational_status', '!=', OperationalStatus::Deserted)
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        $created = 0;
        foreach ($guards as $index => $guard) {
            try {
                $service->record([
                    'guard_id' => $guard->id,
                    'absence_date' => now()->subDays($index % 14)->toDateString(),
                    'reason' => fake()->randomElement(AbsenceReason::cases())->value,
                    'site_id' => $guard->current_site_id,
                    'action_taken' => 'Supervisor notified',
                    'replacement_required' => fake()->boolean(40),
                    'notes' => 'Volume seeded absence',
                ]);
                $created++;
            } catch (\Throwable) {
                // Ignore conflicts.
            }
        }

        $this->command?->info("Absences created: {$created}");
    }

    private function seedDesertions(): void
    {
        $service = app(DesertionService::class);
        $limit = max(5, (int) config('psg.seed.desertions', 40));
        $guards = Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->where('operational_status', '!=', OperationalStatus::Deserted)
            ->whereNotNull('current_site_id')
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        $created = 0;
        foreach ($guards as $guard) {
            try {
                $service->report([
                    'guard_id' => $guard->id,
                    'date_reported' => now()->subDays(fake()->numberBetween(1, 20))->toDateString(),
                    'last_known_duty_date' => now()->subDays(fake()->numberBetween(2, 25))->toDateString(),
                    'last_known_site_id' => $guard->current_site_id,
                    'circumstances' => 'Abandoned post without notice (volume seed).',
                    'action_taken' => 'Reported to HR',
                    'notes' => 'Volume seeded desertion',
                ]);
                $created++;
            } catch (\Throwable) {
                // Ignore conflicts.
            }
        }

        $this->command?->info("Desertions created: {$created}");
    }
}
