<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\Region;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GuardSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $regions = Region::query()->orderBy('id')->get();
        if ($regions->isEmpty()) {
            Auth::logout();

            return;
        }

        $target = max(100, (int) config('psg.seed.guards', 2500));
        $existing = Guard::withTrashed()->count();
        if ($existing >= $target) {
            $this->command?->info("Guards already seeded ({$existing}).");
            Auth::logout();

            return;
        }

        $ranks = ['Security Guard', 'Senior Guard', 'Team Leader', 'Supervisor Guard'];
        $statuses = $this->statusMix();
        $now = now()->toDateTimeString();
        $adminId = $admin?->id;
        $batch = [];
        $created = 0;

        for ($i = $existing + 1; $i <= $target; $i++) {
            $region = $regions[($i - 1) % $regions->count()];
            [$employment, $operational] = $statuses[($i - 1) % count($statuses)];
            $first = fake()->firstName();
            $middle = fake()->optional(0.35)->firstName();
            $last = fake()->lastName();
            $full = trim($first.' '.($middle ? $middle.' ' : '').$last);

            $batch[] = [
                'employment_id' => 'PSG'.str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'first_name' => $first,
                'middle_name' => $middle,
                'last_name' => $last,
                'full_name' => $full,
                'gender' => fake()->randomElement(GuardGender::cases())->value,
                'date_of_birth' => now()->subYears(fake()->numberBetween(22, 52))->toDateString(),
                'phone' => '+2567'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'alternative_phone' => null,
                'address' => fake()->streetAddress().', '.$region->name,
                'national_id' => 'CM'.str_pad((string) $i, 10, '0', STR_PAD_LEFT),
                'date_employed' => now()->subMonths(fake()->numberBetween(1, 60))->toDateString(),
                'employment_status' => $employment->value,
                'rank_designation' => $ranks[$i % count($ranks)],
                'region_id' => $region->id,
                'current_site_id' => null,
                'current_supervisor_id' => null,
                'operational_status' => $operational->value,
                'emergency_contact_name' => fake()->name(),
                'emergency_contact_phone' => '+25670'.str_pad((string) $i, 7, '0', STR_PAD_LEFT),
                'notes' => 'Volume seed guard for '.$region->name.'.',
                'created_by' => $adminId,
                'updated_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 250) {
                DB::table('guards')->insert($batch);
                $created += count($batch);
                $batch = [];
                $this->command?->getOutput()?->write('.');
            }
        }

        if ($batch !== []) {
            DB::table('guards')->insert($batch);
            $created += count($batch);
        }

        $this->command?->newLine();
        $this->command?->info("Guards created: {$created} (total target {$target})");

        Auth::logout();
    }

    /**
     * @return list<array{0: EmploymentStatus, 1: OperationalStatus}>
     */
    private function statusMix(): array
    {
        $mix = [];

        for ($i = 0; $i < 78; $i++) {
            $mix[] = [EmploymentStatus::Active, OperationalStatus::AwaitingDeployment];
        }
        for ($i = 0; $i < 8; $i++) {
            $mix[] = [EmploymentStatus::Active, OperationalStatus::Training];
        }
        for ($i = 0; $i < 6; $i++) {
            $mix[] = [EmploymentStatus::Active, OperationalStatus::OnLeave];
        }
        for ($i = 0; $i < 4; $i++) {
            $mix[] = [EmploymentStatus::Active, OperationalStatus::Absent];
        }
        for ($i = 0; $i < 3; $i++) {
            $mix[] = [EmploymentStatus::Suspended, OperationalStatus::Deserted];
        }
        for ($i = 0; $i < 1; $i++) {
            $mix[] = [EmploymentStatus::Terminated, OperationalStatus::AwaitingDeployment];
        }

        return $mix;
    }
}
