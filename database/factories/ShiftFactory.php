<?php

namespace Database\Factories;

use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Shift;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Shift> */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    public function definition(): array
    {
        $site = Site::factory()->create();
        $guard = Guard::factory()->create([
            'region_id' => $site->region_id,
            'current_site_id' => $site->id,
            'current_supervisor_id' => $site->supervisor_id,
        ]);

        $date = now()->toDateString();
        $startsAt = now()->setTime(6, 0);
        $endsAt = now()->setTime(18, 0);

        return [
            'reference' => 'SHF-'.$startsAt->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'guard_id' => $guard->id,
            'site_id' => $site->id,
            'region_id' => $site->region_id,
            'supervisor_id' => $site->supervisor_id,
            'deployment_id' => null,
            'shift_date' => $date,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'period' => ShiftPeriod::Day,
            'shift_type' => ShiftType::Normal,
            'status' => ShiftStatus::Scheduled,
            'is_overnight' => false,
            'notes' => null,
        ];
    }

    public function forDeployment(Deployment $deployment): static
    {
        return $this->state(fn () => [
            'guard_id' => $deployment->guard_id,
            'site_id' => $deployment->site_id,
            'region_id' => $deployment->region_id,
            'supervisor_id' => $deployment->supervisor_id,
            'deployment_id' => $deployment->id,
        ]);
    }
}
