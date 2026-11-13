<?php

use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
});

it('preserves the token validation response', function (int $status, array $payload): void {
    Http::fake([
        AUTH_SERVICE_URL.'/api/token/validate' => Http::response(
            $payload,
            $status
        ),
    ]);

    $response = $this->withToken(TEST_TOKEN)
        ->postJson(route('auth.token.validate'));

    $response
        ->assertStatus($status)
        ->assertExactJson($payload);

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === AUTH_SERVICE_URL.'/api/token/validate'
        && $request->hasHeader('Authorization', 'Bearer '.TEST_TOKEN)
    );

    Http::assertSentCount(1);
})->with([
    'valid token' => [
        200,
        ['meta' => [
            'valid' => true,
            'expires_at' => '2026-10-03T18:00:00+00:00',
        ]],
    ],
    'token without expiration' => [
        200,
        ['meta' => ['valid' => true, 'expires_at' => null]],
    ],
    'invalid token' => [
        401,
        ['errors' => [[
            'status' => '401',
            'title' => 'Unauthorized',
            'detail' => 'Authentication is required.',
        ]]],
    ],
    'unverified email' => [
        403,
        ['errors' => [[
            'status' => '403',
            'title' => 'Forbidden',
            'detail' => 'Your email address is not verified.',
        ]]],
    ],
    'upstream unavailable' => [
        503,
        ['errors' => [[
            'status' => '503',
            'title' => 'Service Unavailable',
            'detail' => 'Authentication service is unavailable.',
        ]]],
    ],
]);

it('returns service unavailable when the connection fails', function (): void {
    Http::fake([
        AUTH_SERVICE_URL.'/api/token/validate' => Http::failedConnection(),
    ]);

    $this->withToken(TEST_TOKEN)
        ->postJson(route('auth.token.validate'))
        ->assertServiceUnavailable()
        ->assertJsonPath('errors.0.status', '503')
        ->assertJsonPath(
            'errors.0.code',
            'authentication_service_unavailable'
        );
});
