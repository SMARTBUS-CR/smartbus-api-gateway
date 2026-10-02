<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withToken;

const GPS_TRIP_ID = '01a09332-3457-7315-885e-4ebb218f7262';
const GPS_OTHER_TRIP_ID = '01a09332-3457-7315-885e-4ebb218f7299';

beforeEach(function () {
    config([
        'smartbus.auth.url' => 'https://smartbus-authentication.test',
        'smartbus.gps.url' => 'https://smartbus-gps-tracking.test',
    ]);

    Cache::flush();
});

function gpsTokenMeta(): array
{
    return [
        'meta' => [
            'valid' => true,
            'user_id' => '01a09332-0000-7315-885e-4ebb218f7262',
            'email' => 'driver@smartbus.com',
            'roles' => ['driver'],
            'permissions' => [],
            'expires_at' => now()->addMinutes(10)->toISOString(),
        ],
    ];
}

function gpsLocationResource(string $tripId = GPS_TRIP_ID, string $id = '01a0a1f0-9c2e-7b3a-8f41-2d6e5c7b9a10'): array
{
    return [
        'data' => [
            'type' => 'gps-locations',
            'id' => $id,
            'attributes' => [
                'trip_id' => $tripId,
                'latitude' => 10.4631,
                'longitude' => -83.9921,
                'speed_kmh' => 38.5,
                'recorded_at' => '2026-09-03T15:00:00.000000Z',
            ],
            'relationships' => [
                'trip' => [
                    'data' => ['type' => 'trips', 'id' => $tripId],
                ],
            ],
        ],
        'included' => [
            [
                'type' => 'trips',
                'id' => $tripId,
                'attributes' => [
                    'route_id' => '01a09332-1111-7315-885e-4ebb218f7262',
                    'bus_id' => '01a09332-2222-7315-885e-4ebb218f7262',
                    'driver_id' => '01a09332-3333-7315-885e-4ebb218f7262',
                    'status' => 'in_progress',
                    'started_at' => '2026-09-03T14:00:00.000000Z',
                ],
            ],
        ],
    ];
}

function gpsStorePayload(string $tripId = GPS_TRIP_ID): array
{
    return [
        'data' => [
            'type' => 'gps-locations',
            'attributes' => [
                'trip_id' => $tripId,
                'latitude' => 10.4631,
                'longitude' => -83.9921,
                'speed_kmh' => 38.5,
                'recorded_at' => '2026-09-03T15:00:00Z',
            ],
        ],
    ];
}

describe('GPS Routes Registration', function () {
    it('registers the gps endpoints behind the token middleware', function () {
        $expectedRoutes = [
            'gps.locations.store' => ['POST', 'GPSTrackingController@store'],
            'gps.trips.location' => ['GET', 'GPSTrackingController@latestForTrip'],
            'gps.broadcasting.auth' => [['GET', 'POST'], 'GPSTrackingController@authenticateBroadcast'],
        ];

        foreach ($expectedRoutes as $name => [$methods, $action]) {
            $route = Route::getRoutes()->getByName($name);

            expect($route)->not->toBeNull();

            foreach ((array) $methods as $method) {
                expect($route->methods())->toContain($method);
            }

            expect($route->getActionName())->toEndWith($action)
                ->and($route->gatherMiddleware())->toContain('validate.token');
        }
    });

    it('constrains the tripId parameter to UUIDs', function () {
        $route = Route::getRoutes()->getByName('gps.trips.location');

        expect($route->wheres)->toMatchArray(['tripId' => '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}']);
    });
});

describe('GPS Auth Guard', function () {
    it('rejects unauthenticated requests to every gps endpoint', function ($method, $uri) {
        $response = match ($method) {
            'POST' => postJson($uri),
            'GET' => getJson($uri),
        };

        $response->assertStatus(401)
            ->assertJson(['error' => __('http-statuses.401')]);
    })->with([
        'store locations' => ['POST', '/api/gps/locations'],
        'latest for trip' => ['GET', '/api/gps/trips/'.GPS_TRIP_ID.'/location'],
        'broadcasting auth POST' => ['POST', '/api/gps/broadcasting/auth'],
        'broadcasting auth GET' => ['GET', '/api/gps/broadcasting/auth'],
    ]);

    it('rejects requests with an invalid token without hitting the gps service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response([], 401),
        ]);

        withToken('invalid-token')
            ->postJson(route('gps.locations.store'), gpsStorePayload())
            ->assertStatus(401);

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://smartbus-gps-tracking.test/api/'));
    });
});

describe('POST store', function () {
    it('proxies persisted reading as 201', function () {
        $token = 'driver-token';

        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/locations' => Http::response(gpsLocationResource(GPS_TRIP_ID), 201),
        ]);

        $response = withToken($token)->postJson(route('gps.locations.store'), gpsStorePayload(GPS_TRIP_ID));

        $response->assertStatus(201)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('data.type', 'gps-locations')
            ->assertJsonPath('data.attributes.trip_id', GPS_TRIP_ID)
            ->assertJsonPath('data.relationships.trip.data.id', GPS_TRIP_ID)
            ->assertJsonPath('included.0.type', 'trips');

        Http::assertSent(fn ($request) => $request->url() === 'https://smartbus-gps-tracking.test/api/locations'
            && $request->method() === 'POST'
            && $request['data']['attributes']['trip_id'] === GPS_TRIP_ID
            && ($request->header('Authorization')[0] ?? null) === "Bearer {$token}");
    });

    it('forwards 202 broadcast-only response', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/locations' => Http::response(['meta' => ['persisted' => false]], 202),
        ]);

        withToken('driver-token')
            ->postJson(route('gps.locations.store'), gpsStorePayload())
            ->assertStatus(202)
            ->assertJsonPath('meta.persisted', false)
            ->assertJsonMissingPath('data');
    });

    it('forwards validation errors as 422', function () {
        $errors = ['errors' => [['status' => '422', 'source' => ['pointer' => '/data/attributes/latitude']]]];

        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/locations' => Http::response($errors, 422),
        ]);

        $payload = gpsStorePayload();
        $payload['data']['attributes']['latitude'] = 999;

        withToken('driver-token')
            ->postJson(route('gps.locations.store'), $payload)
            ->assertStatus(422)->assertJson($errors);
    });

    it('propagates service failures', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/locations' => Http::response(['message' => 'Upstream failure'], 500),
        ]);

        withToken('driver-token')
            ->postJson(route('gps.locations.store'), gpsStorePayload())
            ->assertStatus(500);
    });
});

describe('GET latestForTrip', function () {
    it('proxies latest position', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/trips/'.GPS_TRIP_ID.'/location' => Http::response(gpsLocationResource(GPS_TRIP_ID), 200),
        ]);

        withToken('p-token')
            ->getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]))
            ->assertStatus(200)
            ->assertJsonPath('data.attributes.trip_id', GPS_TRIP_ID);

        Http::assertSent(fn ($r) => $r->url() === 'https://smartbus-gps-tracking.test/api/trips/'.GPS_TRIP_ID.'/location');
    });

    it('forwards 404 when no readings', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/trips/'.GPS_OTHER_TRIP_ID.'/location' => Http::response(['message' => 'No location'], 404),
        ]);

        withToken('p-token')
            ->getJson(route('gps.trips.location', ['tripId' => GPS_OTHER_TRIP_ID]))
            ->assertStatus(404);
    });

    it('rejects non-UUID tripId without calling the gps service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
        ]);

        withToken('p-token')->getJson('/api/gps/trips/abc/location')->assertStatus(404);
        withToken('p-token')->getJson('/api/gps/trips/42/location')->assertStatus(404);

        Http::assertNotSent(fn ($r) => str_starts_with($r->url(), 'https://smartbus-gps-tracking.test/api/trips/'));
    });

    it('forwards query string to the gps service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/trips/'.GPS_TRIP_ID.'/location*' => Http::response(gpsLocationResource(GPS_TRIP_ID), 200),
        ]);

        withToken('p-token')
            ->getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]).'?include=trip')
            ->assertStatus(200);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://smartbus-gps-tracking.test/api/trips/'.GPS_TRIP_ID.'/location')
                && str_contains($request->url(), 'include=trip');
        });
    });

    it('rejects unauthenticated requests without calling the gps service', function () {
        getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]))
            ->assertStatus(401)
            ->assertJson(['error' => __('http-statuses.401')]);

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://smartbus-gps-tracking.test/api/'));
    });

    it('rejects invalid tokens without calling the gps service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response([], 401),
        ]);

        withToken('invalid-token')
            ->getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]))
            ->assertStatus(401)
            ->assertJson(['message' => __('Invalid or expired token.')]);

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://smartbus-gps-tracking.test/api/'));
    });

    it('forwards upstream 500 errors from the gps service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/trips/'.GPS_TRIP_ID.'/location' => Http::response(['errors' => [['title' => 'Server Error']]], 500),
        ]);

        withToken('p-token')
            ->getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]))
            ->assertStatus(500);
    });

    it('rejects tokens reported as invalid by the auth service without calling the gps service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response([
                'meta' => ['valid' => false, 'user_id' => 10],
            ], 200),
        ]);

        withToken('stale-token')
            ->getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]))
            ->assertStatus(401)
            ->assertJson(['message' => __('Invalid or expired token.')]);

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://smartbus-gps-tracking.test/api/'));
    });

    it('rejects requests when the auth service is unreachable without calling the gps service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => fn () => throw new RuntimeException('auth down'),
        ]);

        withToken('p-token')
            ->getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]))
            ->assertStatus(401);

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://smartbus-gps-tracking.test/api/'));
    });
});

describe('broadcasting/auth proxy', function () {
    it('proxies POST auth payload and returns the channel signature', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/broadcasting/auth' => Http::response(['auth' => 'KEY:sig'], 200),
        ]);

        withToken('p-token')
            ->postJson(route('gps.broadcasting.auth'), ['socket_id' => '1.1', 'channel_name' => 'private-trip.'.GPS_TRIP_ID])
            ->assertStatus(200)->assertJson(['auth' => 'KEY:sig']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://smartbus-gps-tracking.test/api/broadcasting/auth'
                && $request->method() === 'POST'
                && $request['channel_name'] === 'private-trip.'.GPS_TRIP_ID;
        });
    });

    it('converts the form-urlencoded auth payload (Laravel Echo default) into JSON', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/broadcasting/auth' => Http::response(['auth' => 'KEY:sig'], 200),
        ]);

        withToken('p-token')
            ->post(route('gps.broadcasting.auth'), ['socket_id' => '1.1', 'channel_name' => 'private-trip.'.GPS_TRIP_ID], [
                'Accept' => 'application/json',
            ])
            ->assertStatus(200)->assertJson(['auth' => 'KEY:sig']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://smartbus-gps-tracking.test/api/broadcasting/auth'
                && $request->isJson()
                && $request['socket_id'] === '1.1'
                && $request['channel_name'] === 'private-trip.'.GPS_TRIP_ID
                && ($request->header('Authorization')[0] ?? null) === 'Bearer p-token';
        });
    });

    it('proxies GET auth keeping the query string', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/broadcasting/auth*' => Http::response(['auth' => 'KEY:sig'], 200),
        ]);

        withToken('p-token')
            ->getJson(route('gps.broadcasting.auth', ['socket_id' => '1.1', 'channel_name' => 'private-trip.'.GPS_TRIP_ID]))
            ->assertStatus(200)
            ->assertJson(['auth' => 'KEY:sig']);
    });

    it('forwards 403 channel denials from the gps service', function () {
        Http::fake([
            'https://smartbus-authentication.test/api/token/validate' => Http::response(gpsTokenMeta(), 200),
            'https://smartbus-gps-tracking.test/api/broadcasting/auth' => Http::response(['message' => 'Access denied to channel.'], 403),
        ]);

        withToken('p-token')
            ->postJson(route('gps.broadcasting.auth'), ['socket_id' => '1.1', 'channel_name' => 'private-trip.'.GPS_OTHER_TRIP_ID])
            ->assertStatus(403);
    });

    it('rejects unauthenticated broadcasting/auth without calling the gps service', function () {
        postJson(route('gps.broadcasting.auth'), ['socket_id' => '1.1', 'channel_name' => 'private-trip.'.GPS_TRIP_ID])
            ->assertStatus(401);

        getJson(route('gps.broadcasting.auth'))
            ->assertStatus(401);

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://smartbus-gps-tracking.test/api/'));
    });
});
