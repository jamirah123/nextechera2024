<?php

namespace Database\Seeders;

use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use App\Models\User;
use App\Services\LeaveService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class HrSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $guard = Guard::query()
            ->where('employment_status', 'active')
            ->where('operational_status', '!=', OperationalStatus::Deserted->value)
            ->orderByDesc('id')
            ->first();

        if (! $guard) {
            Auth::logout();

            return;
        }

        try {
            app(LeaveService::class)->create([
                'guard_id' => $guard->id,
                'leave_type' => LeaveType::Annual->value,
                'start_date' => now()->addDays(14)->toDateString(),
                'end_date' => now()->addDays(18)->toDateString(),
                'expected_return_date' => now()->addDays(19)->toDateString(),
                'reason' => 'Seeded annual leave request',
                'status' => LeaveStatus::Pending->value,
                'notes' => 'Seeded for HR module demo',
            ]);
        } catch (\Throwable) {
            // Ignore reseed conflicts.
        }

        Auth::logout();
    }
}
