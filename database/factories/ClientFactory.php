<?php

namespace Database\Factories;

use App\Enums\ContractStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Client> */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'contact_person' => fake()->name(),
            'phone' => fake()->e164PhoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'contract_start_date' => now()->subMonths(3)->toDateString(),
            'contract_end_date' => now()->addYear()->toDateString(),
            'contract_status' => ContractStatus::Active,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
