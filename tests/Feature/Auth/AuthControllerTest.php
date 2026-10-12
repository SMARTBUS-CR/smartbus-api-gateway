<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

beforeEach(function () {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
    Cache::flush();
});

describe('Public Authentication', function () {
    it('proxies login to the auth service', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/login' => Http::response(
                '{"access_token":"fake-token-123"}',
                200,
                ['Content-Type' => 'application/vnd.api+json'],
            ),
        ]);

        $response = postJson(route('auth.login'), [
            'email' => TEST_EMAIL,
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json')
            ->assertJson(['access_token' => 'fake-token-123']);
        Http::assertSent(fn ($request) => $request->url() === AUTH_SERVICE_URL.'/api/login'
            && $request['email'] === TEST_EMAIL);
    });

    it('proxies passenger registration to the auth service', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/register/passenger' => Http::response([
                'access_token' => 'new-token-456',
                'user' => ['id' => 1, 'name' => 'Passenger Test'],
            ], 201),
        ]);

        postJson(route('auth.register.passenger'), [
            'name' => 'Passenger Test',
            'email' => 'passenger@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated()->assertJsonPath('user.name', 'Passenger Test');
    });
});

describe('Authenticated Authentication', function () {
    it('returns 401 when no bearer token is provided', function () {
        getJson(route('auth.user'))
            ->assertUnauthorized()
            ->assertJson(['error' => __('http-statuses.401')]);
    });

    it('returns 401 when token validation fails', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response([], 401),
        ]);

        withToken('invalid-token')->getJson(route('auth.user'))
            ->assertUnauthorized()
            ->assertJson(['message' => __('Invalid or expired token.')]);
    });

    it('proxies token validation only once', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
        ]);

        withToken(TEST_TOKEN)->postJson(route('auth.token.validate'))
            ->assertOk();

        expect(Http::recorded(fn ($request) => $request->url() === AUTH_SERVICE_URL.'/api/token/validate'))
            ->toHaveCount(1);
    });

    it('proxies the authenticated user after validating the token', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            AUTH_SERVICE_URL.'/api/user' => Http::response(['user' => ['id' => 10]], 200),
        ]);

        withToken(TEST_TOKEN)->getJson(route('auth.user'))
            ->assertOk()
            ->assertJsonPath('user.id', 10);
    });

    it('caches token validation between requests', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            AUTH_SERVICE_URL.'/api/user' => Http::response([], 200),
        ]);

        withToken(TEST_TOKEN)->getJson(route('auth.user'));
        withToken(TEST_TOKEN)->getJson(route('auth.user'));

        expect(Http::recorded(fn ($request) => $request->url() === AUTH_SERVICE_URL.'/api/token/validate'))
            ->toHaveCount(1);
    });

    it('clears cached token validation after a successful logout', function () {
        $cacheKey = 'token_valid:'.hash('sha256', TEST_TOKEN);
        Cache::put($cacheKey, ['user_id' => 1], now()->addMinutes(10));

        Http::fake([
            AUTH_SERVICE_URL.'/api/logout' => Http::response(['message' => 'Session closed successfully.'], 200),
        ]);

        withToken(TEST_TOKEN)->postJson(route('auth.logout'))->assertOk();

        expect(Cache::has($cacheKey))->toBeFalse();
    });
});
