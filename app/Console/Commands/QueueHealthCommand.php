<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QueueHealthCommand extends Command
{
    protected $signature = 'psg:queue-health {--alert-threshold=5 : Warn when failed jobs exceed this count}';

    protected $description = 'Report queue backlog and failed job counts';

    public function handle(): int
    {
        if (config('queue.default') === 'sync') {
            $this->warn('[WARN] QUEUE_CONNECTION=sync — jobs run inline and no worker is required.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('jobs')) {
            $this->error('[FAIL] jobs table is missing. Run php artisan migrate.');

            return self::FAILURE;
        }

        $pending = (int) DB::table('jobs')->count();
        $failed = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : 0;

        $threshold = max(1, (int) $this->option('alert-threshold'));

        $this->info("[OK] Pending jobs: {$pending}");
        $this->info("[OK] Failed jobs: {$failed}");

        if ($failed >= $threshold) {
            $this->error("[FAIL] Failed jobs ({$failed}) exceed threshold ({$threshold}). Run php artisan queue:failed");

            return self::FAILURE;
        }

        if ($pending > 0) {
            $this->warn("[WARN] {$pending} job(s) waiting. Ensure a worker is running: php artisan queue:work");
        }

        return self::SUCCESS;
    }
}
