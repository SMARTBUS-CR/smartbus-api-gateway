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

    it('proxies passenger account deletion with its JSON payload and headers', function (): void {
        $payload = [
            'current_password' => 'CurrentPassword!2026',
            'confirm_deletion' => true,
        ];

        $responseBody = [
            'meta' => [
                'message' => 'Tu cuenta ha sido eliminada correctamente.',
            ],
        ];

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(
                authTokenMeta([
                    'roles' => ['passenger'],
                    'permissions' => [],
                ]),
                200,
            ),
            AUTH_SERVICE_URL.'/api/user' => Http::response(
                $responseBody,
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        withToken(TEST_TOKEN)
            ->deleteJson('/api/auth/user', $payload, [
                'Accept-Language' => 'es',
            ])
            ->assertOk()
            ->assertExactJson($responseBody)
            ->assertHeader('Content-Type', 'application/json');

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === AUTH_SERVICE_URL.'/api/user'
            && $request->data() === $payload
            && $request->hasHeader('Authorization', 'Bearer '.TEST_TOKEN)
            && $request->hasHeader('Accept-Language', 'es')
        );

        Http::assertSentCount(2);
    });

    it('preserves account deletion errors returned by authentication', function (int $status): void {
        $payload = [
            'current_password' => 'CurrentPassword!2026',
            'confirm_deletion' => true,
        ];

        $error = [
            'status' => $status === 422 ? 422 : (string) $status,
            'title' => 'Authentication Error',
            'detail' => 'La solicitud no pudo completarse.',
        ];

        if ($status === 422) {
            $error['source'] = [
                'pointer' => '/data/attributes/current_password',
            ];
        }

        $responseBody = ['errors' => [$error]];

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(
                authTokenMeta([
                    'roles' => ['passenger'],
                    'permissions' => [],
                ]),
                200,
            ),
            AUTH_SERVICE_URL.'/api/user' => Http::response(
                $responseBody,
                $status,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        withToken(TEST_TOKEN)
            ->deleteJson('/api/auth/user', $payload, [
                'Accept-Language' => 'es',
            ])
            ->assertStatus($status)
            ->assertExactJson($responseBody)
            ->assertHeader('Content-Type', 'application/json');

        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE'
            && $request->url() === AUTH_SERVICE_URL.'/api/user'
            && $request->data() === $payload
        );

        Http::assertSentCount(2);
    })->with([
        'revoked token rejected by authentication' => [401],
        'account deletion forbidden' => [403],
        'invalid password or confirmation' => [422],
        'deletion attempt limit exceeded' => [429],
        'account deletion failed' => [500],
    ]);

    it('preserves token validation failures before account deletion', function (int $status): void {
        $responseBody = [
            'errors' => [[
                'status' => (string) $status,
                'title' => $status === 403 ? 'Forbidden' : 'Service Unavailable',
                'detail' => $status === 403
                    ? 'Your email address is not verified.'
                    : 'Authentication service is unavailable.',
            ]],
        ];

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(
                $responseBody,
                $status,
            ),
            AUTH_SERVICE_URL.'/api/user' => Http::response([
                'meta' => ['message' => 'Account deleted.'],
            ], 200),
        ]);

        withToken(TEST_TOKEN)
            ->deleteJson('/api/auth/user', [
                'current_password' => 'CurrentPassword!2026',
                'confirm_deletion' => true,
            ])
            ->assertStatus($status)
            ->assertExactJson($responseBody);

        Http::assertSent(fn ($request): bool => $request->url() === AUTH_SERVICE_URL.'/api/token/validate'
        );

        Http::assertNotSent(fn ($request): bool => $request->method() === 'DELETE'
        );

        Http::assertSentCount(1);
    })->with([
        'unverified email' => [403],
        'authentication unavailable' => [503],
    ]);

    it('returns service unavailable when token validation cannot connect', function (): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::failedConnection(),
        ]);

        withToken(TEST_TOKEN)
            ->deleteJson('/api/auth/user', [
                'current_password' => 'CurrentPassword!2026',
                'confirm_deletion' => true,
            ])
            ->assertServiceUnavailable()
            ->assertJsonPath('errors.0.status', '503')
            ->assertJsonPath(
                'errors.0.code',
                'authentication_service_unavailable',
            );

        Http::assertNotSent(fn ($request): bool => $request->method() === 'DELETE'
        );
    });

    it('forwards the requested language when validating the deletion token', function (): void {
        $responseBody = [
            'errors' => [[
                'status' => '403',
                'title' => 'Prohibido',
                'detail' => 'Debes verificar tu correo electrónico.',
            ]],
        ];

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(
                $responseBody,
                403,
            ),
        ]);

        withToken(TEST_TOKEN)
            ->deleteJson('/api/auth/user', [
                'current_password' => 'CurrentPassword!2026',
                'confirm_deletion' => true,
            ], [
                'Accept-Language' => 'es',
            ])
            ->assertForbidden()
            ->assertExactJson($responseBody);

        Http::assertSent(fn ($request): bool => $request->url() === AUTH_SERVICE_URL.'/api/token/validate'
            && $request->hasHeader('Accept-Language', 'es')
        );

        Http::assertSentCount(1);
    });
});
