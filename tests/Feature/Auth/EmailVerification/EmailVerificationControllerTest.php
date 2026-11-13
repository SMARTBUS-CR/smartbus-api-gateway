<?php

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
});

describe('Email Verification', function () {
    it('proxies email verification requests', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/email/verify' => Http::response(['message' => 'Email verified.'], 200),
        ]);

        postJson(route('auth.email.verify'), [
            'email' => TEST_EMAIL,
            'code' => '000000',
        ])->assertOk()->assertJson(['message' => 'Email verified.']);
    });

    it('proxies verification code resend requests', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/email/resend' => Http::response([
                'meta' => ['message' => 'Code sent.'],
            ], 200),
        ]);

        postJson(route('auth.email.resend'), ['email' => TEST_EMAIL])
            ->assertOk()
            ->assertJsonPath('meta.message', 'Code sent.');
    });
});
