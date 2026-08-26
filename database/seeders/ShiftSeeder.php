<?php

namespace Database\Seeders;

use App\Enums\ShiftPeriod;
use App\Enums\ShiftType;
use App\Models\Deployment;
use App\Models\User;
use App\Services\ShiftService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $service = app(ShiftService::class);
        $deployments = Deployment::query()->current()->with('site')->orderBy('id')->limit(3)->get();

        foreach ($deployments as $index => $deployment) {
            $period = $index % 2 === 0 ? ShiftPeriod::Day : ShiftPeriod::Night;

            try {
                $service->create([
                    'guard_id' => $deployment->guard_id,
                    'site_id' => $deployment->site_id,
                    'shift_date' => now()->toDateString(),
                    'start_time' => $period->defaultStartTime(),
                    'end_time' => $period->defaultEndTime(),
                    'period' => $period->value,
                    'shift_type' => ShiftType::Normal->value,
                    'notes' => 'Seeded shift for today',
                    'acknowledge_warnings' => true,
                ]);
            } catch (\Throwable) {
                // Skip conflicts on reseed.
            }
        }

        Auth::logout();
    }
}
