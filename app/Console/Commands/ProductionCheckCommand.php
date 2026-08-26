<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionCheckCommand extends Command
{
    protected $signature = 'psg:production-check';

    protected $description = 'Validate production readiness for Platinum Security Group Shifts';

    public function handle(): int
    {
        $failures = 0;

        $checks = [
            'APP_KEY set' => filled(config('app.key')),
            'APP_DEBUG is false in production' => ! (app()->environment('production') && config('app.debug')),
            'Database connection works' => $this->databaseOk(),
            'users table present' => Schema::hasTable('users'),
            'audit_logs table present' => Schema::hasTable('audit_logs'),
            'shifts table present' => Schema::hasTable('shifts'),
            'storage/app is writable' => is_writable(storage_path('app')),
            'storage/logs is writable' => is_writable(storage_path('logs')),
            'Vite build assets exist' => file_exists(public_path('build/manifest.json')),
        ];

        foreach ($checks as $label => $ok) {
            if ($ok) {
                $this->info('[OK] '.$label);
            } else {
                $this->error('[FAIL] '.$label);
                $failures++;
            }
        }

        if (app()->environment('production')) {
            if (config('session.secure') !== true && str_starts_with((string) config('app.url'), 'https://')) {
                $this->warn('[WARN] Prefer SESSION_SECURE_COOKIE=true behind HTTPS.');
            }
            if (config('queue.default') === 'sync') {
                $this->warn('[WARN] QUEUE_CONNECTION=sync is not ideal for production workloads.');
            }
        }

        if ($failures > 0) {
            $this->newLine();
            $this->error("Production check failed with {$failures} issue(s).");

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Production check passed.');

        return self::SUCCESS;
    }

    private function databaseOk(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
