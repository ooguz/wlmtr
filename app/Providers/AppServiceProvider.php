<?php

namespace App\Providers;

use App\Services\WikimediaOAuthService;
use GuzzleHttp\Client;
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

            $provider = Socialite::buildProvider(WikimediaOAuthService::class, $config);

            $userAgent = $config['user_agent'] ?? 'WLM-TR/1.0 (+https://meta.wikimedia.org/wiki/User_talk:Magurale)';

            $provider->setHttpClient(new Client([
                'headers' => [
                    'User-Agent' => $userAgent,
                    'Accept' => 'application/json',
                ],
                'http_errors' => true,
                'timeout' => 15,
            ]));

            return $provider;
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
