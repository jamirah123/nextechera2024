<?php

namespace Database\Seeders;

use App\Enums\DeploymentShiftType;
use App\Enums\GuardClassification;
use App\Enums\ShiftPeriod;
use App\Enums\ShiftStatus;
use App\Enums\ShiftType;
use App\Models\Deployment;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShiftSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $deployments = Deployment::query()
            ->current()
            ->with(['site:id,region_id,supervisor_id'])
            ->orderBy('id')
            ->get();

        if ($deployments->isEmpty()) {
            Auth::logout();

            return;
        }

        $days = max(1, (int) config('psg.seed.shift_days', 5));
        $dayStart = config('psg.shift_defaults.day.start', '06:00');
        $dayEnd = config('psg.shift_defaults.day.end', '18:00');
        $nightStart = config('psg.shift_defaults.night.start', '18:00');
        $nightEnd = config('psg.shift_defaults.night.end', '06:00');
        $adminId = $admin?->id;
        $now = now();
        $seq = 1;
        $rows = [];
        $created = 0;

        // Aim near ~5k–6k shifts: if deployments are large, shorten day window.
        $maxShifts = 6000;
        $daysNeeded = max(1, (int) ceil($maxShifts / max(1, $deployments->count())));
        $days = min($days, $daysNeeded);

        foreach ($deployments as $index => $deployment) {
            $period = match ($deployment->shift_type) {
                DeploymentShiftType::Night => ShiftPeriod::Night,
                DeploymentShiftType::Day => ShiftPeriod::Day,
                default => $index % 2 === 0 ? ShiftPeriod::Day : ShiftPeriod::Night,
            };

            $isNight = $period === ShiftPeriod::Night;
            $startTime = $isNight ? $nightStart : $dayStart;
            $endTime = $isNight ? $nightEnd : $dayEnd;

            for ($dayOffset = 0; $dayOffset < $days; $dayOffset++) {
                $date = $now->copy()->subDays($dayOffset)->startOfDay();
                [$hStart, $mStart] = array_map('intval', explode(':', $startTime));
                [$hEnd, $mEnd] = array_map('intval', explode(':', $endTime));

                $startsAt = $date->copy()->setTime($hStart, $mStart);
                $endsAt = $date->copy()->setTime($hEnd, $mEnd);
                if ($endsAt->lte($startsAt)) {
                    $endsAt->addDay();
                }

                $status = match (true) {
                    $dayOffset === 0 => ShiftStatus::Scheduled->value,
                    $dayOffset === 1 => ShiftStatus::Completed->value,
                    default => fake()->randomElement([
                        ShiftStatus::Completed->value,
                        ShiftStatus::Completed->value,
                        ShiftStatus::Missed->value,
                        ShiftStatus::Cancelled->value,
                    ]),
                };

                $rows[] = [
                    'reference' => 'SHF-'.$startsAt->format('Ymd').'-'.str_pad((string) $seq, 6, '0', STR_PAD_LEFT),
                    'guard_id' => $deployment->guard_id,
                    'site_id' => $deployment->site_id,
                    'region_id' => $deployment->region_id,
                    'supervisor_id' => $deployment->supervisor_id,
                    'deployment_id' => $deployment->id,
                    'recurrence_id' => null,
                    'replaced_shift_id' => null,
                    'shift_date' => $date->toDateString(),
                    'starts_at' => $startsAt->toDateTimeString(),
                    'ends_at' => $endsAt->toDateTimeString(),
                    'period' => $period->value,
                    'shift_type' => ShiftType::Normal->value,
                    'guard_classification' => GuardClassification::Unarmed->value,
                    'status' => $status,
                    'is_overnight' => $isNight,
                    'notes' => 'Volume seeded shift',
                    'override_used' => false,
                    'override_reason' => null,
                    'override_by' => null,
                    'override_at' => null,
                    'validation_snapshot' => null,
                    'approved_by' => $status === ShiftStatus::Completed->value ? $adminId : null,
                    'approved_at' => $status === ShiftStatus::Completed->value ? $now->toDateTimeString() : null,
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];

                $seq++;
                $created++;

                if (count($rows) >= 300) {
                    DB::table('shifts')->insert($rows);
                    $rows = [];
                    $this->command?->getOutput()?->write('.');
                }

                if ($created >= $maxShifts) {
                    break 2;
                }
            }
        }

        if ($rows !== []) {
            DB::table('shifts')->insert($rows);
        }

        $this->command?->newLine();
        $this->command?->info("Shifts created: {$created} (total ".Shift::query()->count().')');

        Auth::logout();
    }
}
