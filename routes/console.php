<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('psg:backup-database', [
    '--keep' => config('psg.backup.keep_days', 14),
    '--path' => config('psg.backup.path', 'backups'),
])->dailyAt('01:30')
    ->withoutOverlapping();

Schedule::command('psg:mark-overdue-invoices')
    ->dailyAt('00:15')
    ->withoutOverlapping();

Schedule::command('psg:sync-shift-statuses')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('psg:release-shift-window-guards')
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command('psg:release-shift-window-guards')
    ->dailyAt('18:00')
    ->withoutOverlapping();
