<?php

namespace App\Console\Commands;

use App\Services\Shifts\ShiftLifecycleService;
use Illuminate\Console\Command;

class SyncShiftStatusesCommand extends Command
{
    protected $signature = 'psg:sync-shift-statuses';

    protected $description = 'Promote active shifts to in progress and mark elapsed shifts as missed';

    public function handle(ShiftLifecycleService $lifecycle): int
    {
        $result = $lifecycle->sync();

        $this->info("Shift sync complete: {$result['started']} started, {$result['missed']} marked missed.");

        return self::SUCCESS;
    }
}
