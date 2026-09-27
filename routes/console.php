<?php

use App\Services\BackupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('gemdesk:backup', function (BackupService $backups) {
    $path = $backups->snapshot('command');
    $this->info('Backup saved: '.$path);
})->purpose('Create a verified GemDesk business-data backup');

Schedule::command('gemdesk:backup')->dailyAt('18:00')->withoutOverlapping();
