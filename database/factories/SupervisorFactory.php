<?php

namespace Database\Factories;

use App\Enums\SupervisorStatus;
use App\Models\Region;
use App\Models\Supervisor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Supervisor> */
class SupervisorFactory extends Factory
{
    protected $model = Supervisor::class;

    public function definition(): array
    {
        return [
            'supervisor_code' => 'SUP'.fake()->unique()->numerify('####'),
            'name' => fake()->name(),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'email' => fake()->optional()->safeEmail(),
            'region_id' => Region::factory(),
            'status' => SupervisorStatus::Active,
            'assignment_date' => now()->toDateString(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
