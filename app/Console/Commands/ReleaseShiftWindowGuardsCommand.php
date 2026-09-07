<?php

namespace App\Console\Commands;

use App\Services\AbsenceService;
use App\Services\DeploymentService;
use App\Services\Shifts\ShiftLifecycleService;
use Illuminate\Console\Command;

class ReleaseShiftWindowGuardsCommand extends Command
{
    protected $signature = 'psg:release-shift-window-guards';

    protected $description = 'Return day/night posted guards to the deploy board when their shift window ends';

    public function handle(AbsenceService $absences, DeploymentService $deployments, ShiftLifecycleService $lifecycle): int
    {
        $lifecycle->sync();
        $releasedAbsent = $absences->releaseEligibleAbsentGuards();
        $releasedDeployments = $deployments->releaseGuardsAfterShiftWindow();

        $this->info("Shift window release complete: {$releasedDeployments} deployment(s) ended, {$releasedAbsent} absent guard(s) released.");

        return self::SUCCESS;
    }
}
