<?php

namespace App\Providers;

use App\Services\WikimediaOAuthService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;

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
        Socialite::extend('wikimedia', function ($app) {
            $config = config('services.wikimedia');

            return Socialite::buildProvider(WikimediaOAuthService::class, $config);
        });

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
