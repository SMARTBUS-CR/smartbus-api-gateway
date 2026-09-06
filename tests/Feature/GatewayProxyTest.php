<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    config([
        'services.auth.url' => 'https://smartbus-authentication.test',
        'services.trips.url' => 'https://smartbus-trips-dev.onrender.com',
    ]);
});

describe('Public Auth Routes', function () {
    it('allows login request to pass through directly to auth service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/login' => Http::response([
                'access_token' => 'fake-token-123',
                'token_type' => 'Bearer',
            ], 200),
        ]);

        $response = postJson(route('auth.login'), [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['access_token' => 'fake-token-123']);

        Http::assertSent(
            fn ($request) => $request->url() === 'https://smartbus-authentication.test/api/login' &&
            $request['email'] === 'user@example.com'
        );
    });

    it('allows passenger registration to pass through', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/register/passenger' => Http::response([
                'access_token' => 'new-token-456',
                'user' => ['id' => 1, 'name' => 'Passenger Test'],
            ], 201),
        ]);

        $response = postJson(route('auth.register.passenger'), [
            'name' => 'Passenger Test',
            'email' => 'passenger@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.name', 'Passenger Test');
    });
});

describe('Administrative Auth Routes', function () {
    it('registers the administrative endpoints under dedicated controllers', function () {
        $expectedRoutes = [
            'auth.permissions.index' => ['GET', 'PermissionsController@index'],
            'auth.roles.index' => ['GET', 'RolesController@index'],
            'auth.users.index' => ['GET', 'UsersController@index'],
            'auth.users.store' => ['POST', 'UsersController@store'],
            'auth.users.show' => ['GET', 'UsersController@show'],
            'auth.users.update' => ['PATCH', 'UsersController@update'],
            'auth.users.destroy' => ['DELETE', 'UsersController@destroy'],
            'auth.users.roles.index' => ['GET', 'UserRolesController@roles'],
            'auth.users.roles.update' => ['PUT', 'UserRolesController@syncRoles'],
            'auth.users.roles.store' => ['POST', 'UserRolesController@assignRole'],
            'auth.users.roles.destroy' => ['DELETE', 'UserRolesController@revokeRole'],
            'auth.users.permissions.index' => ['GET', 'UserPermissionsController@permissions'],
            'auth.users.permissions.update' => ['PUT', 'UserPermissionsController@syncPermissions'],
            'auth.users.permissions.store' => ['POST', 'UserPermissionsController@assignPermission'],
            'auth.users.permissions.destroy' => ['DELETE', 'UserPermissionsController@revokePermission'],
        ];

        foreach ($expectedRoutes as $name => [$method, $action]) {
            $route = Route::getRoutes()->getByName($name);

            expect($route)->not->toBeNull()
                ->and($route->methods())->toContain($method)
                ->and($route->getActionName())->toEndWith($action)
                ->and($route->gatherMiddleware())->toContain('validate.token');
        }
    });
});

describe('Protected Routes and Auth Middleware', function () {
    it('rejects protected route requests without bearer token', function () {
        $response = getJson(route('auth.user'));

        $response->assertStatus(401)
            ->assertJson(['error' => __('http-statuses.401')]);
    });

    it('rejects protected routes when auth service returns invalid token', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response([], 401),
        ]);

        $response = withToken('invalid-token')
            ->getJson(route('auth.user'));

        $response->assertStatus(401)
            ->assertJson(['message' => __('Invalid or expired token.')]);
    });

    it('authenticates and proxies valid token to auth service /user endpoint', function () {
        $token = 'valid-sanctum-token';

        Http::fake([
            // Token introspection
            'https://smartbus-authentication.test/api/token/validate' => Http::response([
                'meta' => [
                    'valid' => true,
                    'user_id' => 10,
                    'email' => 'admin@smartbus.com',
                    'roles' => ['admin'],
                    'permissions' => ['manage-users'],
                    'expires_at' => now()->addMinutes(10)->toISOString(),
                ],
            ], 200),
            // Auth service /user endpoint response
            'https://smartbus-authentication.test/api/user' => Http::response([
                'user' => ['id' => 10, 'email' => 'admin@smartbus.com'],
            ], 200),
        ]);

        $response = withToken($token)
            ->getJson(route('auth.user'));

        $response->assertStatus(200)
            ->assertJsonPath('user.id', 10);
    });
});

describe('Gateway Cache and Logout Handling', function () {
    it('caches valid token introspection to avoid duplicate calls to auth service', function () {
        $token = 'cached-token-abc';

        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response([
                'meta' => [
                    'valid' => true,
                    'user_id' => 99,
                    'email' => 'cached@example.com',
                    'roles' => ['passenger'],
                    'permissions' => [],
                    'expires_at' => now()->addMinutes(10)->toISOString(),
                ],
            ], 200),
            'https://smartbus-authentication.test/api/user' => Http::response([], 200),
        ]);

        // First request: Should call the auth service to validate the token
        withToken($token)->getJson(route('auth.user'));

        // Second request: Should use the cached result and not call the auth service again
        withToken($token)->getJson(route('auth.user'));

        // Assert: Only one call to the auth service for token validation was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://smartbus-authentication.test/api/token/validate';
        }, 1);
    });

    it('clears token cache when user logs out', function () {
        $token = 'token-to-logout';
        $cacheKey = 'token_valid:'.hash('sha256', $token);

        // Force the token to be cached first
        Cache::put($cacheKey, ['user_id' => 1], now()->addMinutes(10));
        expect(Cache::has($cacheKey))->toBeTrue();

        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response([
                'meta' => [
                    'valid' => true,
                    'user_id' => 1,
                    'email' => 'test@example.com',
                    'roles' => [],
                    'permissions' => [],
                    'expires_at' => now()->addMinutes(10)->toISOString(),
                ],
            ], 200),
            'https://smartbus-authentication.test/api/logout' => Http::response([
                'message' => __('Session closed successfully.'),
            ], 200),
        ]);

        $response = withToken($token)->postJson(route('auth.logout'));

        $response->assertStatus(200);

        // Assert: The token cache should be cleared after logout
        expect(Cache::has($cacheKey))->toBeFalse();
    });
});

describe('Security Headers Middleware', function () {
    it('applies security headers to every response', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/login' => Http::response([], 200),
        ]);

        $response = postJson(route('auth.login'), [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-XSS-Protection', '1; mode=block')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Content-Security-Policy', "default-src 'none';");
    });
});

describe('CORS Restrictions', function () {
    it('allows requests from configured origins', function () {
        config(['cors.allowed_origins' => ['https://admin.smartbus.com']]);

        Http::fake([
            'https://smartbus-authentication.test/api/login' => Http::response([], 200),
        ]);

        $response = $this->withHeaders([
            'Origin' => 'https://admin.smartbus.com',
        ])->postJson(route('auth.login'), [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', 'https://admin.smartbus.com');
    });

    it('rejects preflight requests from unauthorized origins', function () {
        // Desactivamos el comodín global del .env para este test específico
        config(['cors.allowed_origins' => ['https://admin.smartbus.com']]);

        $response = $this->flushHeaders()->call('OPTIONS', route('auth.login'), [], [], [], [
            'HTTP_ORIGIN' => 'https://malicious-site.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        // Un preflight no permitido NO debe responder con la URL del atacante ni con '*'
        $allowedOrigin = $response->headers->get('Access-Control-Allow-Origin');
        expect($allowedOrigin)->not->toBe('https://malicious-site.com');
    });
});

describe('Rate Limiting', function () {
    it('limits requests after exceeding the threshold of 60 requests per minute', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/login' => Http::response([], 200),
        ]);

        // Make 60 requests to the login endpoint
        for ($i = 0; $i < 60; $i++) {
            postJson(route('auth.login'), [
                'email' => 'user@example.com',
                'password' => 'password123',
            ])->assertStatus(200);
        }

        // Request number 61 should be blocked by the Rate Limiter
        $response = postJson(route('auth.login'), [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(429); // 429 Too Many Requests
    });
});
