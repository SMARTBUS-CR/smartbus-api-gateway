<?php

use App\Enums\Services;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

/**
 * Define API routes for the SmartBus API Gateway. These routes act as a reverse proxy to various microservices,
 * validating authentication tokens and forwarding requests to the appropriate service.
 */
Route::prefix(Services::AUTH->value)->controller(AuthController::class)->group(function () {
    // Unauthenticated routes for the Authentication service
    Route::post('login', 'login')->name('auth.login');
    Route::post('register/passenger', 'registerPassenger')->name('auth.register.passenger');

    // Authenticated routes for the Authentication service
    Route::middleware('validate.token')->group(function () {
        Route::post('logout', 'logout')->name('auth.logout');
        Route::get('user', 'user')->name('auth.user');
        Route::post('token/validate', 'validateToken')->name('auth.token.validate');
    });

    // Password management routes for the Authentication service
    Route::prefix('password')->group(function () {
        Route::post('forgot', 'sendResetCode')->name('auth.password.forgot');
        Route::post('reset', 'resetPassword')->name('auth.password.reset');
    });
});
