<?php

namespace App\Console\Commands;

use App\Services\LeaveService;
use Illuminate\Console\Command;

class SyncLeaveStatusCommand extends Command
{
    protected $signature = 'psg:sync-leave-status';

    protected $description = 'Start leave that is due and complete leave that has ended';

    public function handle(LeaveService $leaves): int
    {
        $leaves->syncDue();
        $this->info('Leave statuses synced.');

        return self::SUCCESS;
    }
}
