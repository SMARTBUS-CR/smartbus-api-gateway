<?php

use App\Enums\Services;
use App\Http\Controllers\GpsController;
use Illuminate\Support\Facades\Route;

/**
 * Gateway routes for the GPS microservice. These routes act as a reverse proxy:
 * they validate the auth token and forward the request to the GPS service.
 */
Route::prefix(Services::GPS->value)->controller(GpsController::class)->group(function () {
    // Unauthenticated route for the GPS service
    Route::get('ping', 'ping')->name('gps.ping');

    // Authenticated routes for the GPS service
    Route::middleware('validate.token')->group(function () {
        Route::post('locations', 'storeLocation')->name('gps.locations.store');
        Route::get('trips/{tripId}/location', 'showTripLocation')->name('gps.trips.location');
        Route::post('broadcasting/auth', 'authorizeChannel')->name('gps.broadcasting.auth');
    });
});
