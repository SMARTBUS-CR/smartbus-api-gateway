<?php

use App\Enums\Services;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\UserPermissionsController;
use App\Http\Controllers\UserRolesController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

/**
 * Define API routes for the SmartBus API Gateway.
 * These routes act as a reverse proxy to the Authentication Microservice,
 * validating authentication tokens and forwarding requests to the appropriate endpoint.
 */
Route::prefix(Services::AUTH->value)->group(function () {
    // Auth Routes for the Authentication service
    Route::controller(AuthController::class)->group(function () {
        Route::post('register/passenger', 'registerPassenger')->name('auth.register.passenger');
        Route::post('login', 'login')->name('auth.login');
    });

    // Password Management routes for the Authentication service
    Route::prefix('password')->controller(PasswordResetController::class)->group(function () {
        Route::post('forgot', 'sendResetCode')->name('auth.password.forgot');
        Route::post('reset', 'resetPassword')->name('auth.password.reset');
    });

    // Administrative Routes for the Authentication service
    Route::middleware('validate.token')->group(function () {
        // Authenticated routes for the Authentication service
        Route::controller(AuthController::class)->group(function () {
            Route::post('token/validate', 'validateToken')->name('auth.token.validate');
            Route::post('logout', 'logout')->name('auth.logout');
            Route::get('user', 'user')->name('auth.user');
        });

        // User Management Routes for the Authentication service
        Route::controller(UsersController::class)->group(function () {
            Route::get('users', 'index')->name('auth.users.index');
            Route::post('users', 'store')->name('auth.users.store');
            Route::get('users/{user}', 'show')->name('auth.users.show');
            Route::patch('users/{user}', 'update')->name('auth.users.update');
            Route::delete('users/{user}', 'destroy')->name('auth.users.destroy');
        });

        // Roles and Permissions Routes for the Authentication service
        Route::get('roles', [RolesController::class, 'index'])->name('auth.roles.index');
        Route::get('permissions', [PermissionsController::class, 'index'])->name('auth.permissions.index');

        // User Roles Routes for the Authentication service
        Route::controller(UserRolesController::class)->group(function () {
            Route::get('users/{user}/roles', 'roles')->name('auth.users.roles.index');
            Route::put('users/{user}/roles', 'syncRoles')->name('auth.users.roles.update');
            Route::post('users/{user}/roles/{role}', 'assignRole')->name('auth.users.roles.store');
            Route::delete('users/{user}/roles/{role}', 'revokeRole')->name('auth.users.roles.destroy');
        });

        // User Permissions Routes for the Authentication service
        Route::controller(UserPermissionsController::class)->group(function () {
            Route::get('users/{user}/permissions', 'permissions')->name('auth.users.permissions.index');
            Route::put('users/{user}/permissions', 'syncPermissions')->name('auth.users.permissions.update');
            Route::post('users/{user}/permissions/{permission}', 'assignPermission')->name('auth.users.permissions.store');
            Route::delete('users/{user}/permissions/{permission}', 'revokePermission')->name('auth.users.permissions.destroy');
        });
    });
});
