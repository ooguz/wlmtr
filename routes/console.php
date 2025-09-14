<?php

use App\Jobs\SyncAllMonumentData;
use App\Jobs\SyncMonumentDescriptions;
use App\Jobs\SyncMonumentLocations;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule monument data synchronization jobs
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
