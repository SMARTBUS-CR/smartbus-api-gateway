<?php

use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
});

describe('Security Headers', function () {
    it('adds the security headers to API responses', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/login' => Http::response([], 200),
        ]);

        postJson(route('auth.login'), ['email' => TEST_EMAIL, 'password' => 'password123'])
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-XSS-Protection', '1; mode=block')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
            ->assertHeader('Content-Security-Policy', "default-src 'none';");
    });
});

describe('CORS Policy', function () {
    it('allows configured CORS origins', function () {
        config(['cors.allowed_origins' => ['https://admin.smartbus.com']]);
        Http::fake([AUTH_SERVICE_URL.'/api/login' => Http::response([], 200)]);

        $this->withHeaders(['Origin' => 'https://admin.smartbus.com'])
            ->postJson(route('auth.login'), ['email' => TEST_EMAIL, 'password' => 'password123'])
            ->assertHeader('Access-Control-Allow-Origin', 'https://admin.smartbus.com');
    });

    it('does not allow an unauthorized CORS preflight origin', function () {
        config(['cors.allowed_origins' => ['https://admin.smartbus.com']]);

        $response = $this->call('OPTIONS', route('auth.login'), [], [], [], [
            'HTTP_ORIGIN' => 'https://malicious-site.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ]);

        expect($response->headers->get('Access-Control-Allow-Origin'))
            ->not->toBe('https://malicious-site.com');
    });
});

describe('API Rate Limiting', function () {
    it('returns 429 after the API throttle limit is exceeded', function () {
        Http::fake([AUTH_SERVICE_URL.'/api/login' => Http::response([], 200)]);

        for ($requestNumber = 1; $requestNumber <= 60; $requestNumber++) {
            postJson(route('auth.login'), ['email' => TEST_EMAIL, 'password' => 'password123'])
                ->assertOk();
        }

        postJson(route('auth.login'), ['email' => TEST_EMAIL, 'password' => 'password123'])
            ->assertTooManyRequests();
    });
});
