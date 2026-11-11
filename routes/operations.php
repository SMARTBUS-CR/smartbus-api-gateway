<?php

use App\Enums\Services;
use App\Http\Controllers\Operations\OperationsController;
use Illuminate\Support\Facades\Route;

/**
 * Define API routes for the SmartBus Operational Transport API.
 * These routes act as a reverse proxy to the Dashboard microservice,
 * applying rate limiting, perimeter validation, and forwarding to the operational domain.
 */
Route::prefix(Services::OPERATIONS->value)->controller(OperationsController::class)->group(function (): void {
    // ==========================================
    // Public Routes Catalogue & Planning
    // ==========================================
    Route::get('routes', 'routesIndex')->name('ops.routes.index');
    Route::get('routes/{id}', 'routesShow')->whereUuid('id')->name('ops.routes.show');
    Route::get('routes/{id}/patterns', 'routesPatterns')->whereUuid('id')->name('ops.routes.patterns');
    Route::get('routes/{id}/fares', 'routesFares')->whereUuid('id')->name('ops.routes.fares');

    Route::get('route-patterns/{id}', 'routePatternsShow')->whereUuid('id')->name('ops.route-patterns.show');
    Route::get('route-patterns/{id}/schedules', 'routePatternsSchedules')->whereUuid('id')->name('ops.route-patterns.schedules');

    Route::get('stops', 'stopsIndex')->name('ops.stops.index');
    Route::get('stops/{id}', 'stopsShow')->whereUuid('id')->name('ops.stops.show');
    Route::get('stops/{id}/departures', 'stopsDepartures')->whereUuid('id')->name('ops.stops.departures');

    Route::get('journeys', 'journeysIndex')->name('ops.journeys.index');

    Route::get('route-patterns/{id}/trips', 'ongoingTrips')->whereUuid('id')->name('ops.route-patterns.trips');
    Route::get('public/trips/{id}', 'publicTripShow')->whereUuid('id')->name('ops.public-trips.show');

    // Telemetry Tracking Context (for GPS Service)
    Route::get('trips/{id}/tracking-context', 'trackingContext')->whereUuid('id')->name('ops.trips.tracking-context');

    // ==========================================
    // Protected Routes (Token Validation Required)
    // ==========================================
    Route::middleware('validate.token')->group(function (): void {
        // Driver Assignments & Trip Execution
        Route::get('driver/profiles', 'driverProfiles')->name('ops.driver.profiles');
        Route::get('driver/trips', 'driverTrips')->name('ops.driver.trips');
        Route::get('trips/{id}', 'tripsShow')->whereUuid('id')->name('ops.trips.show');
        Route::post('trips/{id}/start', 'tripsStart')->whereUuid('id')->name('ops.trips.start');
        Route::post('trips/{id}/complete', 'tripsComplete')->whereUuid('id')->name('ops.trips.complete');

        // Passenger Favorite Routes
        Route::get('favorites', 'favoritesIndex')->name('ops.favorites.index');
        Route::post('favorites', 'favoritesStore')->name('ops.favorites.store');
        Route::patch('favorites/{route_id}', 'favoritesUpdate')->whereUuid('route_id')->name('ops.favorites.update');
        Route::delete('favorites/{route_id}', 'favoritesDestroy')->whereUuid('route_id')->name('ops.favorites.destroy');
    });
});
