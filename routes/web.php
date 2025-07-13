<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MonumentController;

Route::get('/', function () {
    return redirect()->route('monuments.map');
});

// Monument routes
Route::prefix('monuments')->name('monuments.')->group(function () {
    Route::get('/map', [MonumentController::class, 'map'])->name('map');
    Route::get('/list', [MonumentController::class, 'list'])->name('list');
    Route::get('/{monument}', [MonumentController::class, 'show'])->name('show');
});

// API routes
Route::prefix('api')->name('api.')->group(function () {
    Route::get('/monuments/map-markers', [MonumentController::class, 'apiMapMarkers'])->name('monuments.map-markers');
    Route::get('/monuments/search', [MonumentController::class, 'apiSearch'])->name('monuments.search');
    Route::get('/monuments/{monument}', [MonumentController::class, 'apiShow'])->name('monuments.show');
    Route::get('/monuments/filters', [MonumentController::class, 'apiFilters'])->name('monuments.filters');
});
