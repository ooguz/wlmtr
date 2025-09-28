<?php

namespace App\Providers;

use App\Services\WikimediaOAuthService;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
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

            // Ensure redirect is an absolute URL; fall back to route if missing
            if (empty($config['redirect'])) {
                $config['redirect'] = URL::route('auth.wikimedia.callback');
            }

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

        // Scheduling is centralized in routes/console.php
    }
}
