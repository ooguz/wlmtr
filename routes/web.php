<?php

use App\Http\Controllers\Auth\WikimediaAuthController;
use App\Http\Controllers\MonumentController;
use App\Http\Controllers\PhotoUploadController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('monuments.map');
})->name('home');

// Monument routes
Route::prefix('monuments')->name('monuments.')->middleware('mobile.safari.auth')->group(function () {
    Route::get('/map', [MonumentController::class, 'map'])->name('map');
    Route::get('/list', [MonumentController::class, 'list'])->name('list');
    Route::get('/{monument}', [MonumentController::class, 'show'])->name('show');
});

// Authentication routes
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('/login', [WikimediaAuthController::class, 'showLogin'])->name('login');
    Route::get('/wikimedia', [WikimediaAuthController::class, 'redirectToWikimedia'])->name('wikimedia.redirect');
    Route::get('/wikimedia/callback', [WikimediaAuthController::class, 'handleWikimediaCallback'])->name('wikimedia.callback');
    Route::post('/logout', [WikimediaAuthController::class, 'logout'])->name('logout');
    
    // Debug route for mobile Safari session testing
    Route::get('/debug', function () {
        return response()->json([
            'authenticated' => auth()->check(),
            'user_id' => auth()->id(),
            'user' => auth()->user()?->only(['id', 'username', 'wikimedia_username']),
            'session_id' => session()->getId(),
            'session_data' => [
                'wikimedia_access_token' => session()->has('wikimedia_access_token'),
                'user_agent' => session()->get('user_agent'),
                'oauth_started' => session()->get('oauth_started_at'),
            ],
            'user_agent' => request()->userAgent(),
            'is_mobile_safari' => preg_match('/Mobile\/.*Safari/', request()->userAgent()) && 
                                 !preg_match('/CriOS|FxiOS|EdgiOS/', request()->userAgent()),
            'auth_token_present' => request()->has('auth_token'),
            'auth_token' => request()->input('auth_token'),
        ]);
    })->middleware('mobile.safari.auth')->name('debug');

    // Protected routes
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [WikimediaAuthController::class, 'profile'])->name('profile');
        Route::post('/profile/sync-wikimedia', [WikimediaAuthController::class, 'syncWikimediaData'])->name('profile.sync-wikimedia');
    });
});

// Photo Upload routes
Route::middleware('auth')->prefix('photos')->name('photos.')->group(function () {
    Route::post('/upload', [PhotoUploadController::class, 'upload'])->name('upload');
});

// API routes
Route::prefix('api')->name('api.')->group(function () {
    // CSRF token endpoint for Safari compatibility
    Route::get('/csrf-token', function () {
        return response()->json([
            'token' => csrf_token()
        ]);
    })->name('csrf-token');
    
    Route::get('/monuments/map-markers', [MonumentController::class, 'apiMapMarkers'])->name('monuments.map-markers');
    Route::get('/monuments/search', [MonumentController::class, 'apiSearch'])->name('monuments.search');
    Route::get('/monuments/filters', [MonumentController::class, 'apiFilters'])->name('monuments.filters');
    Route::get('/monuments/{monument}', [MonumentController::class, 'apiShow'])->name('monuments.show');
    Route::get('/monuments/test/images', [MonumentController::class, 'apiTestImages'])->name('monuments.test-images');
    Route::get('/wikidata/label/{qcode}', [MonumentController::class, 'apiWikidataLabel'])->name('wikidata.label');

    // Cache warm endpoint (secured via static token header or query parameter)
    Route::post('/cache/warm/monuments/turkey', [MonumentController::class, 'apiWarmTurkeyMarkers'])
        ->name('cache.warm.turkey');

    // Serve OpenAPI spec
    Route::get('/docs/openapi', function () {
        $path = public_path('openapi.yaml');
        abort_unless(File::exists($path), 404);

        return response(File::get($path), 200, ['Content-Type' => 'application/yaml']);
    })->name('docs.openapi');
});
