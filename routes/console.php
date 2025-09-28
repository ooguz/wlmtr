<?php

use App\Jobs\SyncAllMonumentData;
use App\Jobs\SyncMonumentDescriptions;
use App\Jobs\SyncMonumentLocations;
use App\Jobs\SyncMonumentsUnifiedJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule monument data synchronization jobs
/*
Schedule::job(new SyncAllMonumentData())
    ->hourly()
    ->withoutOverlapping()
    ->name('sync-all-monument-data');

Schedule::job(new SyncMonumentLocations())
    ->everyTwoHours()
    ->withoutOverlapping()
    ->name('sync-monument-locations');

Schedule::job(new SyncMonumentDescriptions())
    ->everyThreeHours()
    ->withoutOverlapping()
    ->name('sync-monument-descriptions');
*/

Schedule::job(new SyncMonumentsUnifiedJob)
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('sync-monuments-unified');

// Warm Turkey-wide markers cache every 10 minutes
Schedule::command('cache:warm-turkey-markers')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->name('warm-turkey-markers');
