<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Models\Region;
use App\Models\Staff;
use App\Services\StaffService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Staff> */
class StaffFactory extends Factory
{
    protected $model = Staff::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $middle = fake()->optional(0.3)->firstName();
        $last = fake()->lastName();

        return [
            'employment_id' => 'PSG'.fake()->unique()->numerify('####'),
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'full_name' => app(StaffService::class)->composeFullName($first, $middle, $last),
            'gender' => fake()->randomElement(GuardGender::cases()),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'date_employed' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'employment_status' => EmploymentStatus::Active,
            'job_title' => fake()->randomElement(['Finance Officer', 'HR Assistant', 'Admin Officer', 'Operations Clerk']),
            'department' => fake()->randomElement(['Finance', 'Human Resources', 'Administration', 'Operations']),
            'region_id' => Region::factory(),
            'monthly_salary' => fake()->randomElement([800000, 1200000, 1500000, 2000000]),
            'bank_name' => fake()->optional()->company(),
            'bank_account' => fake()->optional()->numerify('##########'),
        ];
    }
}
