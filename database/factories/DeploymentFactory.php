<?php

namespace Database\Factories;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deployment> */
class DeploymentFactory extends Factory
{
    protected $model = Deployment::class;

    public function definition(): array
    {
        $site = Site::factory()->create();

        return [
            'guard_id' => Guard::factory()->create([
                'region_id' => $site->region_id,
                'current_site_id' => null,
            ]),
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'shift_type' => DeploymentShiftType::Day,
            'status' => DeploymentStatus::Active,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'is_current' => true,
            'notes' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => DeploymentStatus::Ended,
            'is_current' => false,
            'end_date' => now()->toDateString(),
        ]);
    }
}
