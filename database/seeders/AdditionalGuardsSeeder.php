<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Enums\GuardClassification;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\Region;
use App\Models\User;
use App\Services\GuardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class AdditionalGuardsSeeder extends Seeder
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
            $this->command?->error('No regions found. Seed organization data first.');
            Auth::logout();

            return;
        }

        $service = app(GuardService::class);
        $existing = Guard::withTrashed()->count();
        $target = $existing + 60;
        $created = 0;
        $ranks = ['Security Guard', 'Senior Guard', 'Team Leader'];

        for ($i = $existing + 1; $i <= $target; $i++) {
            $employmentId = 'PSG'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            if (Guard::withTrashed()->where('employment_id', $employmentId)->exists()) {
                continue;
            }

            $region = $regions[($i - 1) % $regions->count()];
            $first = fake()->firstName();
            $last = fake()->lastName();

            $service->createGuard([
                'employment_id' => $employmentId,
                'first_name' => $first,
                'last_name' => $last,
                'gender' => fake()->randomElement(GuardGender::cases())->value,
                'phone' => '+2567'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
                'address' => fake()->streetAddress().', '.$region->name,
                'date_employed' => now()->subMonths(fake()->numberBetween(1, 36))->toDateString(),
                'employment_status' => EmploymentStatus::Active->value,
                'operational_status' => OperationalStatus::AwaitingDeployment->value,
                'rank_designation' => $ranks[$i % count($ranks)],
                'guard_classification' => fake()->boolean(15)
                    ? GuardClassification::Armed->value
                    : GuardClassification::Unarmed->value,
                'region_id' => $region->id,
            ]);
            $created++;
        }

        Auth::logout();

        $this->command?->info("Created {$created} guards. Total: ".Guard::query()->count());
    }
}
