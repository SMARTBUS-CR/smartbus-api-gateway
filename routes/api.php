<?php

use App\Enums\Services;
use App\Http\Controllers\GatewayProxyController;
use Illuminate\Support\Facades\Route;

/**
 * Define API routes for the SmartBus API Gateway. These routes act as a reverse proxy to various microservices, 
 * validating authentication tokens and forwarding requests to the appropriate service.
 */
Route::middleware('validate.token')->group(function () {
    Route::prefix(Services::AUTH->value)->group(function () {
        Route::get('user', [GatewayProxyController::class, 'getAuthUser']);
        Route::post('logout', [GatewayProxyController::class, 'postAuthLogout']);
    });
});

/**
 * Proxy all other requests to the appropriate microservice based on the first segment of the path.
 * This route acts as a catch-all for any requests that do not match the explicitly defined routes above.
 * It will forward the request to the corresponding microservice, injecting necessary headers for user context.
 */
Route::any('/{service}/{path?}', [GatewayProxyController::class, 'proxyTo'])
    ->where('path', '.*');
