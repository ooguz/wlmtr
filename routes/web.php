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
Route::prefix('monuments')->name('monuments.')->group(function () {
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
