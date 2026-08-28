<?php

namespace App\Console\Commands;

use App\Enums\EmploymentStatus;
use App\Enums\OperationalStatus;
use App\Models\Guard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ClearDeploymentShiftDataCommand extends Command
{
    protected $signature = 'psg:clear-deployments-shifts {--force : Skip confirmation prompt}';

    protected $description = 'Remove all deployments, shifts, and related workflow data so you can test manually';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete all deployments, shifts, and reset deployed guards?', false)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $counts = $this->clear();

        $this->newLine();
        $this->info('Workflow data cleared:');
        foreach ($counts as $label => $count) {
            $this->line(sprintf('  %s: %s removed', $label, number_format($count)));
        }
        $this->newLine();
        $this->info('Guards reset for deployment board testing. Deploy from the board, then allocate shifts.');

        return self::SUCCESS;
    }

    /**
     * @return array<string, int>
     */
    public function clear(): array
    {
        $counts = [];

        DB::transaction(function () use (&$counts): void {
            if (Schema::hasTable('shift_replacements')) {
                $counts['Shift replacements'] = DB::table('shift_replacements')->count();
                DB::table('shift_replacements')->delete();
            }

            if (Schema::hasTable('shifts')) {
                DB::table('shifts')->update(['replaced_shift_id' => null]);
                $counts['Shifts'] = DB::table('shifts')->count();
                DB::table('shifts')->delete();
            }

            if (Schema::hasTable('shift_recurrences')) {
                $counts['Shift recurrences'] = DB::table('shift_recurrences')->count();
                DB::table('shift_recurrences')->delete();
            }

            if (Schema::hasTable('deployment_transfers')) {
                $counts['Deployment transfers'] = DB::table('deployment_transfers')->count();
                DB::table('deployment_transfers')->delete();
            }

            if (Schema::hasTable('deployments')) {
                $counts['Deployments'] = DB::table('deployments')->count();
                DB::table('deployments')->delete();
            }

            $counts['Guards reset'] = Guard::query()
                ->where('employment_status', EmploymentStatus::Active)
                ->where(function ($query): void {
                    $query->whereNotNull('current_site_id')
                        ->orWhereIn('operational_status', [
                            OperationalStatus::OffDuty,
                            OperationalStatus::OnDuty,
                        ]);
                })
                ->update([
                    'current_site_id' => null,
                    'current_supervisor_id' => null,
                    'operational_status' => OperationalStatus::AwaitingDeployment,
                    'updated_at' => now(),
                ]);
        });

        return $counts;
    }
}
