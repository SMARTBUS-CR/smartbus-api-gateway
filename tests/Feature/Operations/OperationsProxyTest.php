<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\withToken;

const OPS_ROUTE_ID = '01a1b819-73fc-704b-8d4a-280797b0c3f0';
const OPS_PATTERN_ID = '01a1b819-7886-7185-a381-ed5832b85492';
const OPS_STOP_ID = '01a1b819-7ca6-73bd-9bd0-c0278fd3cae4';
const OPS_TRIP_ID = '01a09332-3457-7315-885e-4ebb218f7262';

beforeEach(function (): void {
    config([
        'smartbus.auth.url' => AUTH_SERVICE_URL,
        'smartbus.operations.url' => OPERATIONS_SERVICE_URL,
    ]);

    Cache::flush();
});

describe('Operations Route Registration', function (): void {
    it('registers public operations routes without token validation middleware', function (string $routeName, array $params = []): void {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->not->toContain('validate.token');
    })->with([
        'routes.index' => ['ops.routes.index'],
        'routes.show' => ['ops.routes.show', ['id' => OPS_ROUTE_ID]],
        'routes.patterns' => ['ops.routes.patterns', ['id' => OPS_ROUTE_ID]],
        'routes.fares' => ['ops.routes.fares', ['id' => OPS_ROUTE_ID]],
        'route-patterns.show' => ['ops.route-patterns.show', ['id' => OPS_PATTERN_ID]],
        'route-patterns.schedules' => ['ops.route-patterns.schedules', ['id' => OPS_PATTERN_ID]],
        'stops.index' => ['ops.stops.index'],
        'stops.show' => ['ops.stops.show', ['id' => OPS_STOP_ID]],
        'stops.departures' => ['ops.stops.departures', ['id' => OPS_STOP_ID]],
        'journeys.index' => ['ops.journeys.index'],
        'route-patterns.trips' => ['ops.route-patterns.trips', ['id' => OPS_PATTERN_ID]],
        'public-trips.show' => ['ops.public-trips.show', ['id' => OPS_TRIP_ID]],
        'trips.tracking-context' => ['ops.trips.tracking-context', ['id' => OPS_TRIP_ID]],
    ]);

    it('registers protected operations routes with validate.token middleware', function (string $routeName, array $params = []): void {
        $route = Route::getRoutes()->getByName($routeName);

        expect($route)->not->toBeNull()
            ->and($route->gatherMiddleware())->toContain('validate.token');
    })->with([
        'driver.profiles' => ['ops.driver.profiles'],
        'driver.trips' => ['ops.driver.trips'],
        'trips.show' => ['ops.trips.show', ['id' => OPS_TRIP_ID]],
        'trips.start' => ['ops.trips.start', ['id' => OPS_TRIP_ID]],
        'trips.complete' => ['ops.trips.complete', ['id' => OPS_TRIP_ID]],
        'favorites.index' => ['ops.favorites.index'],
        'favorites.store' => ['ops.favorites.store'],
        'favorites.update' => ['ops.favorites.update', ['route_id' => OPS_ROUTE_ID]],
        'favorites.destroy' => ['ops.favorites.destroy', ['route_id' => OPS_ROUTE_ID]],
    ]);

    it('enforces UUID route constraints on operations identifiers', function (string $uri): void {
        $this->getJson($uri)->assertNotFound();
    })->with([
        '/api/ops/routes/invalid-id',
        '/api/ops/route-patterns/invalid-id',
        '/api/ops/stops/invalid-id',
        '/api/ops/public/trips/invalid-id',
        '/api/ops/trips/invalid-id',
    ]);
});

describe('Public Catalogue Proxy', function (): void {
    it('proxies public routes list and forwards query parameters', function (): void {
        Http::fake([
            OPERATIONS_SERVICE_URL.'/api/v1/operations/routes*' => Http::response([
                'data' => [
                    [
                        'id' => OPS_ROUTE_ID,
                        'type' => 'routes',
                        'attributes' => ['code' => 'PV-RF', 'name' => 'Puerto Viejo - Rio Frio'],
                    ],
                ],
            ], 200),
        ]);

        $this->getJson(route('ops.routes.index').'?filter[code]=PV-RF&page[size]=10')
            ->assertOk()
            ->assertJsonPath('data.0.id', OPS_ROUTE_ID)
            ->assertJsonPath('data.0.attributes.code', 'PV-RF');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/v1/operations/routes?filter%5Bcode%5D=PV-RF&page%5Bsize%5D=10')
            && $request->method() === 'GET');
    });

    it('proxies single route details', function (): void {
        Http::fake([
            OPERATIONS_SERVICE_URL.'/api/v1/operations/routes/'.OPS_ROUTE_ID => Http::response([
                'data' => [
                    'id' => OPS_ROUTE_ID,
                    'type' => 'routes',
                    'attributes' => ['name' => 'Route Detail'],
                ],
            ], 200),
        ]);

        $this->getJson(route('ops.routes.show', ['id' => OPS_ROUTE_ID]))
            ->assertOk()
            ->assertJsonPath('data.id', OPS_ROUTE_ID);
    });

    it('proxies journey search query', function (): void {
        Http::fake([
            OPERATIONS_SERVICE_URL.'/api/v1/operations/journeys*' => Http::response([
                'data' => [],
                'meta' => ['results_count' => 0],
            ], 200),
        ]);

        $this->getJson(route('ops.journeys.index').'?origin_lat=10.0&origin_lng=-84.0&dest_lat=10.1&dest_lng=-84.1')
            ->assertOk()
            ->assertJsonPath('meta.results_count', 0);
    });

    it('proxies public ongoing trips for a route pattern', function (): void {
        Http::fake([
            OPERATIONS_SERVICE_URL.'/api/v1/operations/route-patterns/'.OPS_PATTERN_ID.'/trips*' => Http::response([
                'data' => [
                    [
                        'id' => OPS_TRIP_ID,
                        'type' => 'trips',
                        'attributes' => ['status' => 'in_progress'],
                    ],
                ],
            ], 200),
        ]);

        $this->getJson(route('ops.route-patterns.trips', ['id' => OPS_PATTERN_ID]))
            ->assertOk()
            ->assertJsonPath('data.0.id', OPS_TRIP_ID);
    });

    it('proxies public trip details', function (): void {
        Http::fake([
            OPERATIONS_SERVICE_URL.'/api/v1/operations/public/trips/'.OPS_TRIP_ID => Http::response([
                'data' => [
                    'id' => OPS_TRIP_ID,
                    'type' => 'trips',
                    'attributes' => ['status' => 'in_progress', 'headsign' => 'Central Station'],
                ],
            ], 200),
        ]);

        $this->getJson(route('ops.public-trips.show', ['id' => OPS_TRIP_ID]))
            ->assertOk()
            ->assertJsonPath('data.attributes.headsign', 'Central Station');
    });
});

describe('Protected Operations Proxy Authentication', function (): void {
    it('returns 401 for every protected operations endpoint without a bearer token', function (string $method, string $routeName, array $parameters): void {
        $this->call($method, route($routeName, $parameters))
            ->assertUnauthorized()
            ->assertJson(['error' => __('http-statuses.401')]);
    })->with([
        'favorites.index' => ['GET', 'ops.favorites.index', []],
        'favorites.store' => ['POST', 'ops.favorites.store', []],
        'favorites.update' => ['PATCH', 'ops.favorites.update', ['route_id' => OPS_ROUTE_ID]],
        'favorites.destroy' => ['DELETE', 'ops.favorites.destroy', ['route_id' => OPS_ROUTE_ID]],
        'driver.profiles' => ['GET', 'ops.driver.profiles', []],
        'driver.trips' => ['GET', 'ops.driver.trips', []],
        'trips.show' => ['GET', 'ops.trips.show', ['id' => OPS_TRIP_ID]],
        'trips.start' => ['POST', 'ops.trips.start', ['id' => OPS_TRIP_ID]],
        'trips.complete' => ['POST', 'ops.trips.complete', ['id' => OPS_TRIP_ID]],
    ]);

    it('rejects protected operations requests with an invalid bearer token', function (): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(['message' => 'Unauthenticated.'], 401),
        ]);

        withToken('invalid-token')->getJson(route('ops.favorites.index'))
            ->assertUnauthorized();
    });
});

describe('Passenger Favorites Proxy', function (): void {
    it('proxies passenger favorite routes listing when authenticated', function (): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(['roles' => ['passenger']]), 200),
            OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites' => Http::response([
                'data' => [
                    [
                        'id' => '01a1c900-0000-7000-8000-000000000001',
                        'type' => 'favorite-routes',
                        'attributes' => ['label' => 'Work Commute'],
                    ],
                ],
            ], 200),
        ]);

        withToken('passenger-token')->getJson(route('ops.favorites.index'))
            ->assertOk()
            ->assertJsonPath('data.0.attributes.label', 'Work Commute');

        Http::assertSent(fn ($request): bool => $request->url() === OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites'
            && $request->header('Authorization')[0] === 'Bearer passenger-token');
    });

    it('proxies adding a favorite route with request payload', function (): void {
        $payload = ['route_id' => OPS_ROUTE_ID, 'label' => 'Gym'];

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(['roles' => ['passenger']]), 200),
            OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites' => Http::response([
                'data' => [
                    'id' => '01a1c900-0000-7000-8000-000000000002',
                    'type' => 'favorite-routes',
                    'attributes' => $payload,
                ],
            ], 201),
        ]);

        withToken('passenger-token')->postJson(route('ops.favorites.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('data.attributes.label', 'Gym');

        Http::assertSent(fn ($request): bool => $request->url() === OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites'
            && $request->method() === 'POST'
            && $request['route_id'] === OPS_ROUTE_ID);
    });

    it('proxies updating favorite route label via PATCH', function (): void {
        $payload = ['label' => 'Home Commute'];

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(['roles' => ['passenger']]), 200),
            OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites/'.OPS_ROUTE_ID => Http::response([
                'data' => [
                    'id' => '01a1c900-0000-7000-8000-000000000002',
                    'type' => 'favorite-routes',
                    'attributes' => ['route_id' => OPS_ROUTE_ID, 'label' => 'Home Commute'],
                ],
            ], 200),
        ]);

        withToken('passenger-token')->patchJson(route('ops.favorites.update', ['route_id' => OPS_ROUTE_ID]), $payload)
            ->assertOk()
            ->assertJsonPath('data.attributes.label', 'Home Commute');

        Http::assertSent(fn ($request): bool => $request->url() === OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites/'.OPS_ROUTE_ID
            && $request->method() === 'PATCH'
            && $request['label'] === 'Home Commute');
    });

    it('proxies deleting a favorite route returning 204 No Content', function (): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(['roles' => ['passenger']]), 200),
            OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites/'.OPS_ROUTE_ID => Http::response(null, 204),
        ]);

        withToken('passenger-token')->deleteJson(route('ops.favorites.destroy', ['route_id' => OPS_ROUTE_ID]))
            ->assertNoContent();

        Http::assertSent(fn ($request): bool => $request->url() === OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites/'.OPS_ROUTE_ID
            && $request->method() === 'DELETE');
    });
});

describe('Driver and Trip Execution Proxy', function (): void {
    it('proxies driver trip start with authorization token', function (): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(['roles' => ['driver']]), 200),
            OPERATIONS_SERVICE_URL.'/api/v1/operations/trips/'.OPS_TRIP_ID.'/start' => Http::response([
                'data' => [
                    'id' => OPS_TRIP_ID,
                    'type' => 'trips',
                    'attributes' => ['status' => 'in_progress'],
                ],
            ], 200),
        ]);

        withToken('driver-token')->postJson(route('ops.trips.start', ['id' => OPS_TRIP_ID]))
            ->assertOk()
            ->assertJsonPath('data.attributes.status', 'in_progress');

        Http::assertSent(fn ($request): bool => $request->url() === OPERATIONS_SERVICE_URL.'/api/v1/operations/trips/'.OPS_TRIP_ID.'/start'
            && $request->method() === 'POST');
    });
});

describe('Upstream Error Forwarding', function (): void {
    it('forwards upstream operational error status codes and JSON payloads', function (int $status, array $body): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            OPERATIONS_SERVICE_URL.'/api/v1/operations/favorites' => Http::response($body, $status),
        ]);

        withToken('passenger-token')->postJson(route('ops.favorites.store'), ['route_id' => OPS_ROUTE_ID])
            ->assertStatus($status)
            ->assertJson($body);
    })->with([
        'conflict duplicate' => [409, ['errors' => [['status' => '409', 'title' => 'Conflict', 'detail' => 'Already exists']]]],
        'unprocessable route' => [422, ['errors' => [['status' => '422', 'title' => 'Unprocessable', 'detail' => 'Inactive route']]]],
        'not found' => [404, ['errors' => [['status' => '404', 'title' => 'Not Found', 'detail' => 'Route not found']]]],
    ]);
});
