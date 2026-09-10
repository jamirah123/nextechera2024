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
        '--keep' => config('psg.backup.keep_days', 14),
    ])->dailyAt('01:30')
        ->withoutOverlapping()
        ->name('psg-backup-daily');
}

if (in_array($backupSchedule, ['weekly', 'daily_and_weekly'], true)) {
    Schedule::command('psg:backup-database', [
        '--type' => 'scheduled_weekly',
        '--keep' => config('psg.backup.keep_days', 14),
    ])->weeklyOn(0, '02:15')
        ->withoutOverlapping()
        ->name('psg-backup-weekly');
}

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
