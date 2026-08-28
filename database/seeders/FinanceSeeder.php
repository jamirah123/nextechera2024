<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Site;
use App\Models\User;
use App\Services\Finance\BillingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $billing = app(BillingService::class);
        $created = 0;

        foreach (Client::query()->orderBy('id')->get() as $client) {
            $sites = Site::query()->where('client_id', $client->id)->orderBy('id')->get();

            // One client-level profile plus site-level profiles for coverage testing.
            try {
                $billing->create([
                    'client_id' => $client->id,
                    'site_id' => null,
                    'contracted_armed_guards' => fake()->numberBetween(0, 4),
                    'contracted_unarmed_guards' => fake()->numberBetween(6, 20),
                    'monthly_rate_per_armed_guard' => fake()->numberBetween(900000, 1400000),
                    'monthly_rate_per_unarmed_guard' => fake()->numberBetween(550000, 850000),
                    'monthly_cost_per_armed_guard' => fake()->numberBetween(650000, 950000),
                    'monthly_cost_per_unarmed_guard' => fake()->numberBetween(380000, 520000),
                    'monthly_site_fee' => fake()->numberBetween(100000, 400000),
                    'rate_per_armed_shift' => fake()->numberBetween(35000, 55000),
                    'rate_per_unarmed_shift' => fake()->numberBetween(22000, 35000),
                    'cost_per_armed_shift' => fake()->numberBetween(22000, 35000),
                    'cost_per_unarmed_shift' => fake()->numberBetween(14000, 22000),
                    'effective_from' => now()->subMonths(3)->toDateString(),
                    'is_active' => true,
                    'notes' => 'Client-level volume billing profile',
                ]);
                $created++;
            } catch (\Throwable) {
                // Skip duplicates / validation issues.
            }

            foreach ($sites->take(2) as $site) {
                try {
                    $billing->create([
                        'client_id' => $client->id,
                        'site_id' => $site->id,
                        'contracted_armed_guards' => fake()->numberBetween(0, 2),
                        'contracted_unarmed_guards' => max(2, (int) $site->required_guards),
                        'monthly_rate_per_armed_guard' => fake()->numberBetween(900000, 1400000),
                        'monthly_rate_per_unarmed_guard' => fake()->numberBetween(550000, 850000),
                        'monthly_cost_per_armed_guard' => fake()->numberBetween(650000, 950000),
                        'monthly_cost_per_unarmed_guard' => fake()->numberBetween(380000, 520000),
                        'monthly_site_fee' => fake()->numberBetween(50000, 200000),
                        'rate_per_armed_shift' => fake()->numberBetween(35000, 55000),
                        'rate_per_unarmed_shift' => fake()->numberBetween(22000, 35000),
                        'cost_per_armed_shift' => fake()->numberBetween(22000, 35000),
                        'cost_per_unarmed_shift' => fake()->numberBetween(14000, 22000),
                        'effective_from' => now()->subMonths(2)->toDateString(),
                        'is_active' => true,
                        'notes' => 'Site-level volume billing profile',
                    ]);
                    $created++;
                } catch (\Throwable) {
                    // Skip.
                }
            }
        }

        $this->command?->info("Billing profiles created: {$created}");

        Auth::logout();
    }
}
