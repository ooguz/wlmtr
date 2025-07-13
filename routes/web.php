<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MonumentController;
use App\Http\Controllers\Auth\WikimediaAuthController;

Route::get('/', function () {
    return redirect()->route('monuments.map');
});

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

// API routes
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/monuments/map-markers', [MonumentController::class, 'apiMapMarkers'])->name('monuments.map-markers');
    Route::get('/monuments/search', [MonumentController::class, 'apiSearch'])->name('monuments.search');
    Route::get('/monuments/{monument}', [MonumentController::class, 'apiShow'])->name('monuments.show');
    Route::get('/monuments/filters', [MonumentController::class, 'apiFilters'])->name('monuments.filters');
});
