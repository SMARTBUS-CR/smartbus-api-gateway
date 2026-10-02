<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\postJson;

beforeEach(function () {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
});

describe('Locale Selection', function () {
    it('uses a supported Accept-Language header for the request locale', function () {
        Http::fake([AUTH_SERVICE_URL.'/api/login' => Http::response([], 200)]);

        $this->withHeaders(['Accept-Language' => 'es'])
            ->postJson(route('auth.login'), ['email' => TEST_EMAIL, 'password' => 'password123'])
            ->assertOk();

        expect(App::getLocale())->toBe('es');
    });

    it('falls back to the configured locale for an unsupported language', function () {
        config(['app.fallback_locale' => 'en']);
        Http::fake([AUTH_SERVICE_URL.'/api/login' => Http::response([], 200)]);

        postJson(route('auth.login'), ['email' => TEST_EMAIL, 'password' => 'password123'])
            ->assertOk();

        expect(App::getLocale())->toBe('en');
    });
});
