<?php

namespace Database\Seeders;

use App\Enums\DeploymentShiftType;
use App\Enums\DeploymentStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Models\Deployment;
use App\Models\Guard;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DeploymentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@platinumsecurity.local')->first()
            ?? User::query()->first();

        if ($admin) {
            Auth::login($admin);
        }

        $sites = Site::query()->orderBy('id')->get();
        if ($sites->isEmpty()) {
            Auth::logout();

            return;
        }

        $guardsByRegion = Guard::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereIn('operational_status', [
                OperationalStatus::AwaitingDeployment,
                OperationalStatus::Training,
            ])
            ->whereDoesntHave('deployments', fn ($q) => $q->current())
            ->orderBy('id')
            ->get(['id', 'region_id'])
            ->groupBy('region_id')
            ->map(fn ($group) => $group->values());

        $shiftTypes = [
            DeploymentShiftType::Day->value,
            DeploymentShiftType::Night->value,
            DeploymentShiftType::Rotating->value,
        ];

        $now = now();
        $adminId = $admin?->id;
        $rows = [];
        $guardUpdates = [];
        $deployed = 0;

        foreach ($sites as $siteIndex => $site) {
            $pool = $guardsByRegion->get($site->region_id, collect());
            if ($pool->isEmpty()) {
                continue;
            }

            $target = min(
                max((int) $site->required_guards, 10),
                $pool->count(),
            );

            for ($n = 0; $n < $target; $n++) {
                /** @var Guard|null $guard */
                $guard = $pool->shift();
                if (! $guard) {
                    break;
                }

                $guardsByRegion[$site->region_id] = $pool;
                $shiftType = $shiftTypes[($siteIndex + $n) % count($shiftTypes)];

                $rows[] = [
                    'guard_id' => $guard->id,
                    'site_id' => $site->id,
                    'region_id' => $site->region_id,
                    'supervisor_id' => $site->supervisor_id,
                    'shift_type' => $shiftType,
                    'status' => DeploymentStatus::Active->value,
                    'start_date' => $now->copy()->subDays(($siteIndex + $n) % 40 + 3)->toDateString(),
                    'end_date' => null,
                    'is_current' => true,
                    'notes' => 'Volume seeded deployment',
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                    'created_at' => $now->toDateTimeString(),
                    'updated_at' => $now->toDateTimeString(),
                ];

                $guardUpdates[$guard->id] = [
                    'current_site_id' => $site->id,
                    'current_supervisor_id' => $site->supervisor_id,
                    'operational_status' => OperationalStatus::OnDuty->value,
                    'region_id' => $site->region_id,
                ];

                $deployed++;

                if (count($rows) >= 200) {
                    DB::table('deployments')->insert($rows);
                    $rows = [];
                    $this->command?->getOutput()?->write('.');
                }
            }
        }

        if ($rows !== []) {
            DB::table('deployments')->insert($rows);
        }

        foreach (array_chunk($guardUpdates, 200, true) as $chunk) {
            foreach ($chunk as $guardId => $data) {
                DB::table('guards')->where('id', $guardId)->update(array_merge($data, [
                    'updated_at' => $now->toDateTimeString(),
                    'updated_by' => $adminId,
                ]));
            }
        }

        $this->command?->newLine();
        $this->command?->info('Deployments created: '.$deployed.' (current total '.Deployment::query()->current()->count().')');

        Auth::logout();
    }
}
