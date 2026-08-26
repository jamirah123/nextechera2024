<?php

namespace Database\Seeders;

use App\Enums\DeploymentShiftType;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\Site;
use App\Models\User;
use App\Services\DeploymentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class DeploymentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $service = app(DeploymentService::class);
        $sites = Site::query()->orderBy('id')->get();
        $guards = Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereIn('operational_status', [
                OperationalStatus::AwaitingDeployment,
                OperationalStatus::OffDuty,
                OperationalStatus::Training,
            ])
            ->orderBy('id')
            ->get();

        if ($sites->isEmpty() || $guards->isEmpty()) {
            Auth::logout();

            return;
        }

        $pairs = min(3, $sites->count(), $guards->count());
        $shifts = [DeploymentShiftType::Day, DeploymentShiftType::Night, DeploymentShiftType::Rotating];

        for ($i = 0; $i < $pairs; $i++) {
            $guard = $guards[$i];
            $site = $sites[$i % $sites->count()];

            if ($guard->deployments()->current()->exists()) {
                continue;
            }

            try {
                $service->deploy([
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'shift_type' => $shifts[$i % count($shifts)]->value,
                    'start_date' => now()->subDays(7 - $i)->toDateString(),
                    'notes' => 'Seeded active deployment',
                ]);
            } catch (\Throwable) {
                // Skip invalid combinations during reseeds.
            }
        }

        Auth::logout();
    }
}
