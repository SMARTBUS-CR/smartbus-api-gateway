<?php

use App\Enums\Services;
use App\Http\Controllers\GPS\GPSTrackingController;
use Illuminate\Support\Facades\Route;

/**
 * Define API routes for the SmartBus GPS Tracking API.
 * These routes act as a reverse proxy to the GPS Microservice,
 * validating authentication tokens and forwarding requests to the appropriate endpoint.
 */
Route::prefix(Services::GPS->value)->group(function () {
    Route::middleware('validate.token')->group(function () {
        Route::controller(GPSTrackingController::class)->group(function () {
            // RESTful API Routes for the GPS Tracking service
            Route::post('locations', 'store')->name('gps.locations.store');
            Route::get('trips/{tripId}/location', 'latestForTrip')
                ->whereNumber('tripId') // TODO: Verify if this is the correct constraint for tripId
                ->name('gps.trips.location');

            // Authentication Route for WebSockets / Reverb Broadcast
            Route::match(['get', 'post'], 'broadcasting/auth', 'authenticateBroadcast')
                ->name('gps.broadcasting.auth');
        });
    });
});
