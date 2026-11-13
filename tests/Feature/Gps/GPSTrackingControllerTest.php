<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\withToken;

const GPS_TRIP_ID = '01a09332-3457-7315-885e-4ebb218f7262';
const GPS_OTHER_TRIP_ID = '01a09332-3457-7315-885e-4ebb218f7299';

beforeEach(function () {
    config([
        'smartbus.auth.url' => AUTH_SERVICE_URL,
        'smartbus.gps.url' => GPS_SERVICE_URL,
    ]);

    Cache::flush();
});

function gpsPayload(string $tripId = GPS_TRIP_ID): array
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

function gpsResponse(string $tripId = GPS_TRIP_ID): array
{
    return [
        'data' => [
            'type' => 'gps-locations',
            'id' => '1',
            'attributes' => ['trip_id' => $tripId],
            'relationships' => ['trip' => ['data' => ['type' => 'trips', 'id' => (string) $tripId]]],
        ],
    ];
}

describe('GPS Route Registration', function () {
    it('registers GPS routes with UUID trip constraints and token validation', function () {
        $route = Route::getRoutes()->getByName('gps.trips.location');

        expect($route)->not->toBeNull()
            ->and($route->wheres)->toMatchArray(['tripId' => '[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}'])
            ->and($route->gatherMiddleware())->toContain('validate.token');
    });
});

describe('GPS Authentication', function () {
    it('returns 401 for every GPS endpoint without a bearer token', function (string $method, string $routeName, array $parameters) {
        $this->call($method, route($routeName, $parameters))
            ->assertUnauthorized()
            ->assertJson(['error' => __('http-statuses.401')]);
    })->with([
        'store' => ['POST', 'gps.locations.store', []],
        'latest location' => ['GET', 'gps.trips.location', ['tripId' => GPS_TRIP_ID]],
        'broadcast GET' => ['GET', 'gps.broadcasting.auth', []],
        'broadcast POST' => ['POST', 'gps.broadcasting.auth', []],
    ]);
});

describe('GPS Location Proxy', function () {
    it('proxies a persisted GPS location as 201', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(['roles' => ['driver']]), 200),
            GPS_SERVICE_URL.'/api/locations' => Http::response(gpsResponse(), 201),
        ]);

        withToken('driver-token')->postJson(route('gps.locations.store'), gpsPayload())
            ->assertCreated()
            ->assertJsonPath('data.type', 'gps-locations')
            ->assertJsonPath('data.attributes.trip_id', GPS_TRIP_ID);

        Http::assertSent(fn ($request) => $request->url() === GPS_SERVICE_URL.'/api/locations'
            && $request->method() === 'POST'
            && $request['data']['attributes']['trip_id'] === GPS_TRIP_ID);
    });

    it('forwards GPS accepted and error responses', function (int $status, array $body) {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            GPS_SERVICE_URL.'/api/locations' => Http::response($body, $status),
        ]);

        withToken('driver-token')->postJson(route('gps.locations.store'), gpsPayload())
            ->assertStatus($status)
            ->assertJson($body);
    })->with([
        'broadcast-only' => [202, ['meta' => ['persisted' => false]]],
        'validation failure' => [422, ['errors' => [['status' => '422']]]],
        'service failure' => [500, ['message' => 'Upstream failure']],
    ]);
});

describe('Latest Location Proxy', function () {
    it('proxies the latest trip location and query string', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            GPS_SERVICE_URL.'/api/trips/'.GPS_TRIP_ID.'/location*' => Http::response(gpsResponse(), 200),
        ]);

        withToken('driver-token')->getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]).'?include=trip')
            ->assertOk()
            ->assertJsonPath('data.attributes.trip_id', GPS_TRIP_ID);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/trips/'.GPS_TRIP_ID.'/location?include=trip'));
    });

    it('returns 404 for non-UUID trip IDs without calling GPS', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
        ]);

        withToken('driver-token')->getJson('/api/gps/trips/abc/location')->assertNotFound();
        withToken('driver-token')->getJson('/api/gps/trips/42/location')->assertNotFound();
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), GPS_SERVICE_URL.'/api/'));
    });

    it('returns 401 when auth validation fails or is unavailable without calling GPS', function (callable $fake) {
        Http::fake($fake);

        withToken('driver-token')->getJson(route('gps.trips.location', ['tripId' => GPS_TRIP_ID]))
            ->assertUnauthorized();

        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), GPS_SERVICE_URL.'/api/'));
    })->with([
        'invalid response' => [fn () => Http::response([], 401)],
        'invalid metadata' => [fn () => Http::response(['meta' => ['valid' => false]], 200)],
        'unavailable service' => [fn () => throw new RuntimeException('auth down')],
    ]);
});

describe('Broadcasting Authentication Proxy', function () {
    it('proxies both broadcasting authentication methods and channel denials', function (string $method, array $payload, int $status) {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            GPS_SERVICE_URL.'/api/broadcasting/auth*' => Http::response(['auth' => 'KEY:sig'], $status),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer driver-token',
            'Accept' => 'application/json',
        ])->json($method, route('gps.broadcasting.auth'), $payload);
        $response->assertStatus($status);
    })->with([
        'POST success' => ['POST', ['socket_id' => '1.1', 'channel_name' => 'private-trip.'.GPS_TRIP_ID], 200],
        'GET success' => ['GET', [], 200],
        'POST denied' => ['POST', ['socket_id' => '1.1', 'channel_name' => 'private-trip.'.GPS_OTHER_TRIP_ID], 403],
    ]);

    it('converts form-urlencoded auth payloads into JSON for the GPS service', function () {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            GPS_SERVICE_URL.'/api/broadcasting/auth' => Http::response(['auth' => 'KEY:sig'], 200),
        ]);

        withToken('driver-token')
            ->post(route('gps.broadcasting.auth'), [
                'socket_id' => '1.1',
                'channel_name' => 'private-trip.'.GPS_TRIP_ID,
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJson(['auth' => 'KEY:sig']);

        Http::assertSent(fn ($request) => $request->url() === GPS_SERVICE_URL.'/api/broadcasting/auth'
            && $request->isJson()
            && $request['socket_id'] === '1.1'
            && $request['channel_name'] === 'private-trip.'.GPS_TRIP_ID
            && ($request->header('Authorization')[0] ?? null) === 'Bearer driver-token');
    });
});
