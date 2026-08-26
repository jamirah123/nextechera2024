<?php

namespace Database\Factories;

use App\Enums\RegionStatus;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Region> */
class RegionFactory extends Factory
{
    protected $model = Region::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Central', 'Eastern', 'Northern', 'Western', 'Southern', 'Coastal', 'Lake Zone']);

        return [
            'name' => $name,
            'code' => strtoupper(substr($name, 0, 3)).fake()->unique()->numerify('##'),
            'description' => fake()->optional()->sentence(),
            'manager_name' => fake()->optional()->name(),
            'manager_phone' => fake()->optional()->e164PhoneNumber(),
            'status' => RegionStatus::Active,
        ];
    }
}
