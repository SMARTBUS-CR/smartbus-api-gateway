<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    it('proxies login without logging sensitive data', function (): void {
        Log::spy();

        $password = 'SensitivePassword!2026';
        $accessToken = 'sensitive-issued-access-token';

        $responseBody = [
            'data' => [
                'type' => 'users',
                'id' => 'passenger-id',
                'attributes' => [
                    'email' => TEST_EMAIL,
                ],
            ],
            'meta' => [
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
            ],
        ];

        Http::fake([
            AUTH_SERVICE_URL.'/api/login' => Http::response($responseBody, 200),
        ]);

        postJson(route('auth.login'), [
            'email' => TEST_EMAIL,
            'password' => $password,
        ])
            ->assertOk()
            ->assertExactJson($responseBody);

        $sensitiveValues = [$password, $accessToken, TEST_EMAIL];

        foreach ([
            'debug',
            'info',
            'notice',
            'warning',
            'error',
            'critical',
            'alert',
            'emergency',
            'log',
        ] as $level) {
            Log::shouldNotHaveReceived(
                $level,
                function (...$arguments) use ($sensitiveValues): bool {
                    $loggedData = json_encode($arguments, JSON_THROW_ON_ERROR);

                    foreach ($sensitiveValues as $value) {
                        if (str_contains($loggedData, $value)) {
                            return true;
                        }
                    }

                    return false;
                },
            );
        }
    });
});

describe('Authenticated Authentication', function () {
    it('returns 401 when no bearer token is provided', function () {
        getJson(route('auth.user'))
            ->assertUnauthorized()
            ->assertJson(['error' => __('http-statuses.401')]);
    });

    it('returns 401 when token validation fails', function (): void {
        $responseBody = [
            'errors' => [[
                'status' => '401',
                'title' => 'Unauthorized',
                'detail' => 'Authentication is required.',
            ]],
        ];

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(
                $responseBody,
                401,
            ),
        ]);

        withToken('invalid-token')
            ->getJson(route('auth.user'))
            ->assertUnauthorized()
            ->assertExactJson($responseBody);

        Http::assertSentCount(1);
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

    it('validates the token against authentication on every request', function (): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            AUTH_SERVICE_URL.'/api/user' => Http::response([], 200),
        ]);

        withToken(TEST_TOKEN)->getJson(route('auth.user'))->assertOk();
        withToken(TEST_TOKEN)->getJson(route('auth.user'))->assertOk();

        expect(Http::recorded(
            fn ($request): bool => $request->url() === AUTH_SERVICE_URL.'/api/token/validate',
        ))->toHaveCount(2);
    });

    it('validates the token before forwarding logout', function (): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            AUTH_SERVICE_URL.'/api/logout' => Http::response([
                'meta' => ['message' => 'Session closed successfully.'],
            ], 200),
        ]);

        withToken(TEST_TOKEN)
            ->postJson(route('auth.logout'))
            ->assertOk()
            ->assertJsonPath('meta.message', 'Session closed successfully.');

        Http::assertSent(fn ($request): bool => $request->url() === AUTH_SERVICE_URL.'/api/token/validate'
        );

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === AUTH_SERVICE_URL.'/api/logout'
        );

        Http::assertSentCount(2);
    });

    it('rejects a revoked token even when a positive validation is cached', function (): void {
        config(['smartbus.gps.url' => GPS_SERVICE_URL]);

        Cache::put(
            'token_valid:'.hash('sha256', TEST_TOKEN),
            authTokenMeta()['meta'],
            now()->addMinutes(10),
        );

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response([
                'errors' => [[
                    'status' => '401',
                    'title' => 'Unauthorized',
                    'detail' => 'Authentication is required.',
                ]],
            ], 401),
            GPS_SERVICE_URL.'/api/*' => Http::response([
                'data' => ['latitude' => 10.45, 'longitude' => -84.01],
            ], 200),
        ]);

        withToken(TEST_TOKEN)
            ->getJson(route('gps.trips.location', [
                'tripId' => '01a11d61-c541-731e-aa08-bd8c5adb6863',
            ]))
            ->assertUnauthorized();

        Http::assertSent(fn ($request): bool => $request->url() === AUTH_SERVICE_URL.'/api/token/validate'
            && $request->hasHeader('Authorization', 'Bearer '.TEST_TOKEN)
        );

        Http::assertNotSent(fn ($request): bool => str_starts_with($request->url(), GPS_SERVICE_URL.'/')
        );

        Http::assertSentCount(1);
    });
});
