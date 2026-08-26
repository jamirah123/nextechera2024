<?php

namespace Database\Factories;

use App\Enums\SiteStatus;
use App\Models\Client;
use App\Models\Region;
use App\Models\Site;
use App\Models\Supervisor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Site> */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    public function definition(): array
    {
        $day = fake()->numberBetween(2, 8);
        $night = fake()->numberBetween(2, 8);

        return [
            'name' => fake()->unique()->company().' Site',
            'code' => strtoupper(fake()->unique()->bothify('SITE###')),
            'client_id' => Client::factory(),
            'region_id' => Region::factory(),
            'supervisor_id' => null,
            'physical_location' => fake()->streetAddress(),
            'latitude' => fake()->optional()->latitude(),
            'longitude' => fake()->optional()->longitude(),
            'site_contact_person' => fake()->name(),
            'site_contact_phone' => fake()->e164PhoneNumber(),
            'contract_start_date' => now()->subMonths(2)->toDateString(),
            'contract_end_date' => now()->addMonths(10)->toDateString(),
            'required_guards' => $day + $night,
            'required_day_guards' => $day,
            'required_night_guards' => $night,
            'number_of_posts' => fake()->numberBetween(1, 6),
            'status' => SiteStatus::Active,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function forSupervisor(Supervisor $supervisor): static
    {
        return $this->state(fn () => [
            'supervisor_id' => $supervisor->id,
            'region_id' => $supervisor->region_id,
        ]);
    }
}
