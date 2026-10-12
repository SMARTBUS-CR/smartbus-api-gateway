<?php

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\withToken;

beforeEach(function () {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
});

describe('User Account', function () {
    it('proxies profile updates with the expected payload', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            AUTH_SERVICE_URL.'/api/user' => Http::response(
                '{"data":{"type":"users","id":"10","attributes":{"name":"Updated Passenger"}}}',
                200,
                ['Content-Type' => 'application/vnd.api+json'],
            ),
        ]);

        withToken(TEST_TOKEN)->patchJson(route('auth.user.update'), [
            'name' => 'Updated Passenger',
            'email' => TEST_EMAIL,
            'current_password' => 'CurrentPassword!2026',
        ])->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.api+json');

        Http::assertSent(fn ($request) => $request->method() === 'PATCH'
            && $request->url() === AUTH_SERVICE_URL.'/api/user'
            && $request['name'] === 'Updated Passenger'
            && $request['email'] === TEST_EMAIL
            && $request['current_password'] === 'CurrentPassword!2026');
    });

    it('proxies password updates to the password endpoint', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            AUTH_SERVICE_URL.'/api/user/password' => Http::response([
                'meta' => ['message' => 'Password updated.'],
            ], 200),
        ]);

        withToken(TEST_TOKEN)->putJson(route('auth.user.password.update'), [
            'current_password' => 'CurrentPassword!2026',
            'password' => 'NewPassword!2026',
            'password_confirmation' => 'NewPassword!2026',
        ])->assertOk()
            ->assertJsonPath('meta.message', 'Password updated.');

        Http::assertSent(fn ($request) => $request->method() === 'PUT'
            && $request->url() === AUTH_SERVICE_URL.'/api/user/password'
            && $request['current_password'] === 'CurrentPassword!2026'
            && $request['password'] === 'NewPassword!2026'
            && $request['password_confirmation'] === 'NewPassword!2026');
    });
});
