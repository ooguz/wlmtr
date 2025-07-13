<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = $this->app->make(Schedule::class);
            
            // Sync monuments from Wikidata every 15 minutes
            $schedule->command('monuments:sync-from-wikidata')
                    ->everyFifteenMinutes()
                    ->withoutOverlapping()
                    ->runInBackground();
        });
    }
}
