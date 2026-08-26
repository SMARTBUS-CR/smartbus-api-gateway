<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

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

        $response = postJson('/api/auth/login', [
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

        $response = postJson('/api/auth/register/passenger', [
            'name' => 'Passenger Test',
            'email' => 'passenger@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.name', 'Passenger Test');
    });
});

describe('Protected Routes and Auth Middleware', function () {
    it('rejects protected route requests without bearer token', function () {
        $response = getJson('/api/auth/user');

        $response->assertStatus(401)
            ->assertJson(['error' => __('http-statuses.401')]);
    });

    it('rejects protected routes when auth service returns invalid token', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/tokens/validate' => Http::response([], 401),
        ]);

        $response = withToken('invalid-token')
            ->getJson('/api/auth/user');

        $response->assertStatus(401)
            ->assertJson(['message' => __('Invalid or expired token.')]);
    });

    it('authenticates and proxies valid token to auth service /user endpoint', function () {
        $token = 'valid-sanctum-token';

        Http::fake([
            // Token introspection
            'https://smartbus-authentication.test/api/tokens/validate' => Http::response([
                'valid' => true,
                'user_id' => 10,
                'email' => 'admin@smartbus.com',
                'roles' => ['admin'],
                'permissions' => ['manage-users'],
            ], 200),
            // Auth service /user endpoint response
            'https://smartbus-authentication.test/api/user' => Http::response([
                'user' => ['id' => 10, 'email' => 'admin@smartbus.com'],
            ], 200),
        ]);

        $response = withToken($token)
            ->getJson('/api/auth/user');

        $response->assertStatus(200)
            ->assertJsonPath('user.id', 10);
    });
});

describe('Gateway Cache and Logout Handling', function () {
    it('caches valid token introspection to avoid duplicate calls to auth service', function () {
        $token = 'cached-token-abc';

        Http::fake([
            'https://smartbus-authentication.test/api/tokens/validate' => Http::response([
                'valid' => true,
                'user_id' => 99,
                'email' => 'cached@example.com',
                'roles' => ['passenger'],
                'permissions' => [],
            ], 200),
            'https://smartbus-authentication.test/api/user' => Http::response([], 200),
        ]);

        // First request: Should call the auth service to validate the token
        withToken($token)->getJson('/api/auth/user');

        // Second request: Should use the cached result and not call the auth service again
        withToken($token)->getJson('/api/auth/user');

        // Assert: Only one call to the auth service for token validation was made
        Http::assertSent(function ($request) {
            return $request->url() === 'https://smartbus-authentication.test/api/tokens/validate';
        }, 1);
    });

    it('clears token cache when user logs out', function () {
        $token = 'token-to-logout';
        $cacheKey = 'token_valid:'.hash('sha256', $token);

        // Force the token to be cached first
        Cache::put($cacheKey, ['user_id' => 1], now()->addMinutes(10));
        expect(Cache::has($cacheKey))->toBeTrue();

        Http::fake([
            'https://smartbus-authentication.test/api/tokens/validate' => Http::response([
                'valid' => true,
                'user_id' => 1,
                'email' => 'test@example.com',
                'roles' => [],
                'permissions' => [],
            ], 200),
            'https://smartbus-authentication.test/api/logout' => Http::response([
                'message' => __('Session closed successfully.'),
            ], 200),
        ]);

        $response = withToken($token)->postJson('/api/auth/logout');

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

        $response = postJson('/api/auth/login', [
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
        ])->postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertHeader('Access-Control-Allow-Origin', 'https://admin.smartbus.com');
    });

    it('rejects preflight requests from unauthorized origins', function () {
        // Desactivamos el comodín global del .env para este test específico
        config(['cors.allowed_origins' => ['https://admin.smartbus.com']]);

        $response = $this->flushHeaders()->call('OPTIONS', '/api/auth/login', [], [], [], [
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
            postJson('/api/auth/login', [
                'email' => 'user@example.com',
                'password' => 'password123',
            ])->assertStatus(200);
        }

        // Request number 61 should be blocked by the Rate Limiter
        $response = postJson('/api/auth/login', [
            'email' => 'user@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(429); // 429 Too Many Requests
    });
});
