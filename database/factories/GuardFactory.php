<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\GuardGender;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\Region;
use App\Services\GuardService;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Guard> */
class GuardFactory extends Factory
{
    protected $model = Guard::class;

    public function definition(): array
    {
        $first = fake()->firstName();
        $middle = fake()->optional(0.4)->firstName();
        $last = fake()->lastName();
        $service = app(GuardService::class);

        return [
            'employment_id' => 'PSG'.fake()->unique()->numerify('####'),
            'first_name' => $first,
            'middle_name' => $middle,
            'last_name' => $last,
            'full_name' => $service->composeFullName($first, $middle, $last),
            'gender' => fake()->randomElement(GuardGender::cases()),
            'date_of_birth' => fake()->optional()->dateTimeBetween('-55 years', '-20 years')?->format('Y-m-d'),
            'phone' => fake()->optional()->e164PhoneNumber(),
            'alternative_phone' => fake()->optional(0.3)->e164PhoneNumber(),
            'address' => fake()->optional()->streetAddress(),
            'national_id' => fake()->optional()->numerify('##########'),
            'date_employed' => fake()->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'employment_status' => EmploymentStatus::Active,
            'rank_designation' => fake()->optional()->randomElement(['Security Guard', 'Senior Guard', 'Team Leader']),
            'region_id' => Region::factory(),
            'current_site_id' => null,
            'current_supervisor_id' => null,
            'operational_status' => OperationalStatus::AwaitingDeployment,
            'emergency_contact_name' => fake()->optional()->name(),
            'emergency_contact_phone' => fake()->optional()->e164PhoneNumber(),
            'photo_path' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }

    public function onLeave(): static
    {
        return $this->state(fn () => [
            'operational_status' => OperationalStatus::OnLeave,
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn () => [
            'operational_status' => OperationalStatus::Absent,
        ]);
    }

    public function deserted(): static
    {
        return $this->state(fn () => [
            'operational_status' => OperationalStatus::Deserted,
            'employment_status' => EmploymentStatus::Suspended,
        ]);
    }
}
