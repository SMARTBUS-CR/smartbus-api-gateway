<?php

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
});

describe('Password Reset', function () {
    it('proxies password reset code requests', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/password/forgot' => Http::response([
                'meta' => ['message' => 'Code sent.'],
            ], 200),
        ]);

        postJson(route('auth.password.forgot'), ['email' => TEST_EMAIL])
            ->assertOk()
            ->assertJsonPath('meta.message', 'Code sent.');
    });

    it('proxies password reset submissions', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/password/reset' => Http::response([
                'meta' => ['message' => 'Password reset.'],
            ], 200),
        ]);

        postJson(route('auth.password.reset'), [
            'email' => TEST_EMAIL,
            'code' => '123456',
            'password' => 'SmartBusResetPassword!2026',
            'password_confirmation' => 'SmartBusResetPassword!2026',
        ])->assertOk()->assertJsonPath('meta.message', 'Password reset.');
    });
});
