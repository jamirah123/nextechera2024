<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$backupSchedule = (string) config('psg.backup.schedule', 'daily');

if (in_array($backupSchedule, ['daily', 'daily_and_weekly'], true)) {
    Schedule::command('psg:backup-database', [
        '--type' => 'scheduled_daily',
    ])->dailyAt('01:30')
        ->withoutOverlapping()
        ->name('psg-backup-daily');
}

if (in_array($backupSchedule, ['weekly', 'daily_and_weekly'], true)) {
    Schedule::command('psg:backup-database', [
        '--type' => 'scheduled_weekly',
    ])->weeklyOn(0, '02:15')
        ->withoutOverlapping()
        ->name('psg-backup-weekly');
}

// Monthly long-retention copy (1st of each month).
Schedule::command('psg:backup-database', [
    '--type' => 'scheduled_monthly',
])->monthlyOn(1, '03:00')
    ->withoutOverlapping()
    ->name('psg-backup-monthly');

// Integrity check of the latest successful backup after the nightly window.
Schedule::command('psg:verify-backup')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->name('psg-backup-verify');

// Non-destructive restore drill weekly (Sunday after weekly backup).
Schedule::command('psg:test-restore-backup')
    ->weeklyOn(0, '04:30')
    ->withoutOverlapping()
    ->name('psg-backup-restore-drill');

// Freshness / missed-backup monitoring.
Schedule::command('psg:backup-health', ['--alert' => true])
    ->hourly()
    ->withoutOverlapping()
    ->name('psg-backup-health');

Schedule::command('psg:sync-leave-status')
    ->dailyAt('00:20')
    ->withoutOverlapping();

Schedule::command('psg:mark-overdue-invoices')
    ->dailyAt('00:15')
    ->withoutOverlapping();

Schedule::command('psg:sync-shift-statuses')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('psg:scan-proactive-alerts')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('psg:export-accounting')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('psg:queue-health')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('psg:release-shift-window-guards')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('psg:release-shift-window-guards')
    ->dailyAt('18:00')
    ->withoutOverlapping();
