<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('health:scheduler-heartbeat')->everyMinute();

$backupTime = trim((string) env('DB_BACKUP_TIME', '03:15'));
$backupRetentionDays = max(1, (int) env('DB_BACKUP_RETENTION_DAYS', 14));

Schedule::command("backup:database --retention-days={$backupRetentionDays}")
    ->dailyAt($backupTime !== '' ? $backupTime : '03:15')
    ->withoutOverlapping();
