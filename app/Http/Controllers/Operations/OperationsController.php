<?php

namespace App\Http\Controllers\Operations;

use App\Enums\Services;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[Group(Services::OPERATIONS_LABEL, Services::OPERATIONS_DESCRIPTION, weight: Services::OPERATIONS_WEIGHT)]
class OperationsController extends Controller
{
    // ==========================================
    // Public Routes Catalogue & Planning
    // ==========================================

    /**
     * List Transport Routes
     *
     * Retrieves a paginated list of operational transport routes from the public catalogue.
     * Supports filtering by route code, route name, and operating company UUID, as well as sorting.
     *
     * For more information about the routes catalogue, see the
     * [List Transport Routes](https://smartbus-dashboard.onrender.com/docs/api#tag/operations/GET/routes)
     * section in the Dashboard microservice documentation.
     *
     * @unauthenticated
     */
    #[QueryParameter('filter[code]', type: 'string', required: false, description: 'Filter routes by exact route code.', example: 'PV-RF')]
    #[QueryParameter('filter[name]', type: 'string', required: false, description: 'Filter routes by partial route name.', example: 'Puerto Viejo')]
    #[QueryParameter('filter[company_id]', type: 'string', format: 'uuid', required: false, description: 'Filter routes by operating company UUID.')]
    #[QueryParameter('sort', type: 'string', required: false, description: 'Sort field: code, name, created_at (prefix with - for descending).', example: 'code')]
    #[QueryParameter('include', type: 'string', required: false, description: 'Include related resources: company, patterns.', example: 'company,patterns')]
    #[QueryParameter('page[size]', type: 'int', required: false, description: 'Number of results per page (1-100).', example: 20)]
    #[QueryParameter('page[number]', type: 'int', required: false, description: 'Page number to retrieve.', example: 1)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{code: string, name: string, description: string|null, is_active: bool}, relationships: array<string, mixed>}>, links: array<string, string|null>, meta: array<string, mixed>}')]
    public function routesIndex(Request $request): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, 'routes');
    }

    /**
     * Get Route Details
     *
     * Retrieves specific details for an operational transport route including its operating company and patterns.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{code: string, name: string, description: string|null, is_active: bool}, relationships: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function routesShow(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "routes/{$id}");
    }

    /**
     * List Route Patterns
     *
     * Retrieves the route patterns associated with a specific transport route.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{code: string, name: string, direction: string, geometry: array<string, mixed>|null}, relationships: array<string, mixed>}}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function routesPatterns(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "routes/{$id}/patterns");
    }

    /**
     * List Route Fares
     *
     * Retrieves effective fares for a specific transport route within valid date ranges.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[QueryParameter('date', type: 'string', format: 'date', required: false, description: 'Filter fares valid on a specific date (YYYY-MM-DD).', example: '2026-11-10')]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{amount: float, currency: string, valid_from: string|null, valid_until: string|null}}}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function routesFares(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "routes/{$id}/fares");
    }

    /**
     * Get Route Pattern Details
     *
     * Retrieves the geometry, ordered sequence of stops, and operational details for a route pattern.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{code: string, name: string, direction: string, geometry: array<string, mixed>|null}, relationships: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function routePatternsShow(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "route-patterns/{$id}");
    }

    /**
     * List Route Pattern Schedules
     *
     * Retrieves the weekly scheduled departure times for a specific route pattern.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{day_of_week: int, departure_time: string, valid_from: string|null, valid_until: string|null}}}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function routePatternsSchedules(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "route-patterns/{$id}/schedules");
    }

    /**
     * List Bus Stops
     *
     * Retrieves a list of georeferenced bus stops from the public catalogue with optional filtering by name or company.
     *
     * @unauthenticated
     */
    #[QueryParameter('filter[company_id]', type: 'string', format: 'uuid', required: false, description: 'Filter stops by associated company UUID.')]
    #[QueryParameter('filter[name]', type: 'string', required: false, description: 'Filter stops by partial stop name.')]
    #[QueryParameter('page[size]', type: 'int', required: false, description: 'Number of results per page (1-100).', example: 20)]
    #[QueryParameter('page[number]', type: 'int', required: false, description: 'Page number to retrieve.', example: 1)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{name: string, code: string|null, latitude: float, longitude: float}}>, links: array<string, string|null>, meta: array<string, mixed>}')]
    public function stopsIndex(Request $request): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, 'stops');
    }

    /**
     * Get Bus Stop Details
     *
     * Retrieves details and geographic coordinates for a specific bus stop.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{name: string, code: string|null, latitude: float, longitude: float}}}]')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function stopsShow(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "stops/{$id}");
    }

    /**
     * List Planned Stop Departures
     *
     * Retrieves upcoming scheduled departures for a specific bus stop within a designated time window.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[QueryParameter('departure_at', type: 'string', format: 'date-time', required: false, description: 'Reference departure timestamp (ISO 8601).', example: '2026-11-10T12:00:00Z')]
    #[QueryParameter('window_minutes', type: 'int', required: false, description: 'Time window in minutes to search ahead (default 120).', example: 120)]
    #[QueryParameter('page[size]', type: 'int', required: false, example: 20)]
    #[QueryParameter('page[number]', type: 'int', required: false, example: 1)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{scheduled_departure_at: string, headsign: string, route_code: string}}>, links: array<string, string|null>, meta: array<string, mixed>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function stopsDepartures(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "stops/{$id}/departures");
    }

    /**
     * Direct Journey Search
     *
     * Searches for direct transport options between origin and destination coordinates without requiring transfers.
     *
     * @unauthenticated
     *
     * @throws ValidationException
     */
    #[QueryParameter('origin_lat', type: 'float', required: true, description: 'Latitude coordinate of origin (-90 to 90).', example: 9.9333)]
    #[QueryParameter('origin_lng', type: 'float', required: true, description: 'Longitude coordinate of origin (-180 to 180).', example: -84.0833)]
    #[QueryParameter('destination_lat', type: 'float', required: true, description: 'Latitude coordinate of destination (-90 to 90).', example: 10.4631)]
    #[QueryParameter('destination_lng', type: 'float', required: true, description: 'Longitude coordinate of destination (-180 to 180).', example: -83.9921)]
    #[QueryParameter('departure_at', type: 'string', format: 'date-time', required: false, description: 'Desired departure time (ISO 8601).')]
    #[QueryParameter('walking_radius_m', type: 'int', required: false, description: 'Max walking distance in meters (50-2000, default 800).', example: 800)]
    #[QueryParameter('page[size]', type: 'int', required: false, example: 20)]
    #[QueryParameter('page[number]', type: 'int', required: false, example: 1)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{pattern_id: string, headsign: string, boarding_stop: array<string, mixed>, alighting_stop: array<string, mixed>, scheduled_departure_at: string}}>, meta: array<string, mixed>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string, source: array<string, string>}>}')]
    public function journeysIndex(Request $request): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, 'journeys');
    }

    /**
     * List Ongoing Trips for Route Pattern
     *
     * Retrieves a public, read-only projection of ongoing in-progress trips for a route pattern without exposing employee information.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[QueryParameter('page[size]', type: 'int', required: false, example: 20)]
    #[QueryParameter('page[number]', type: 'int', required: false, example: 1)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{status: string, scheduled_departure_at: string, actual_departure_at: string|null, headsign: string|null, bus: array{id: string, plate_number: string, unit_number: string}}}>, links: array<string, string|null>, meta: array<string, mixed>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function ongoingTrips(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "route-patterns/{$id}/trips");
    }

    /**
     * Get Public Trip Details
     *
     * Retrieves public progress details of an ongoing trip without exposing employee information.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{status: string, scheduled_departure_at: string, actual_departure_at: string|null, headsign: string|null, bus: array{id: string, plate_number: string, unit_number: string}}}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function publicTripShow(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "public/trips/{$id}");
    }

    // ==========================================
    // Protected Driver & Trip Execution Routes
    // ==========================================

    /**
     * List Driver Profiles
     *
     * Retrieves driver employment profiles for the authenticated user.
     *
     * @throws UnauthorizedException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{company_id: string, license: string, status: string}}}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    public function driverProfiles(Request $request): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, 'driver/profiles');
    }

    /**
     * List Assigned Driver Trips
     *
     * Retrieves operational trip assignments for the authenticated driver.
     *
     * @throws UnauthorizedException
     */
    #[QueryParameter('filter[status]', type: 'string', required: false, description: 'Filter by trip status (scheduled, in_progress, completed, cancelled).', example: 'scheduled')]
    #[QueryParameter('filter[service_date]', type: 'string', format: 'date', required: false, description: 'Filter by service date (YYYY-MM-DD).', example: '2026-11-10')]
    #[QueryParameter('page[size]', type: 'int', required: false, example: 15)]
    #[QueryParameter('page[number]', type: 'int', required: false, example: 1)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{status: string, service_date: string, scheduled_departure_at: string}}>, links: array<string, string|null>, meta: array<string, mixed>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    public function driverTrips(Request $request): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, 'driver/trips');
    }

    /**
     * Get Trip Details
     *
     * Retrieves private details of a specific trip assignment.
     *
     * @throws UnauthorizedException
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{status: string, service_date: string, scheduled_departure_at: string, scheduled_arrival_at: string, actual_departure_at: string|null, actual_arrival_at: string|null}}}]')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function tripsShow(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "trips/{$id}");
    }

    /**
     * Start Trip Assignment
     *
     * Initiates execution of an assigned trip by the authorized driver.
     *
     * @throws UnauthorizedException
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{status: string, actual_departure_at: string}}}]')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_CONFLICT, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function tripsStart(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "trips/{$id}/start");
    }

    /**
     * Complete Trip Assignment
     *
     * Concludes execution of an ongoing trip.
     *
     * @throws UnauthorizedException
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{status: string, actual_arrival_at: string}}}]')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_CONFLICT, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function tripsComplete(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "trips/{$id}/complete");
    }

    // ==========================================
    // Protected Passenger Favorite Routes
    // ==========================================

    /**
     * List Passenger Favorite Routes
     *
     * Retrieves the authenticated passenger's bookmarked favorite routes including route and operating company relationships.
     *
     * @throws UnauthorizedException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{id: string, type: string, attributes: array{route_id: string, label: string|null, created_at: string}, relationships: array<string, mixed>}}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    public function favoritesIndex(Request $request): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, 'favorites');
    }

    /**
     * Bookmark Route as Favorite
     *
     * Adds an active operational route to the authenticated passenger's favorites with an optional label.
     *
     * @throws UnauthorizedException
     * @throws ValidationException
     */
    #[BodyParameter('route_id', type: 'string', format: 'uuid', required: true, description: 'UUID of an active operational route to bookmark.', example: '01a1b819-73fc-704b-8d4a-280797b0c3f0')]
    #[BodyParameter('label', type: 'string', required: false, description: 'Custom passenger label/alias (max 50 chars).', example: 'Work Commute')]
    #[ResponseAttribute(status: HttpStatus::HTTP_CREATED, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{route_id: string, label: string|null, created_at: string}, relationships: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_CONFLICT, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function favoritesStore(Request $request): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, 'favorites');
    }

    /**
     * Update Favorite Route Label
     *
     * Updates the custom label of an existing favorite route bookmark.
     *
     * @throws UnauthorizedException
     * @throws NotFoundHttpException
     * @throws ValidationException
     */
    #[BodyParameter('label', type: 'string', required: false, description: 'Updated custom label (max 50 chars).', example: 'Home Commute')]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{route_id: string, label: string|null, updated_at: string}, relationships: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function favoritesUpdate(Request $request, string $routeId): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "favorites/{$routeId}");
    }

    /**
     * Remove Route from Favorites
     *
     * Deletes a route from the authenticated passenger's favorites.
     *
     * @throws UnauthorizedException
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_NO_CONTENT, description: 'Route bookmark successfully deleted.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{error: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function favoritesDestroy(Request $request, string $routeId): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "favorites/{$routeId}");
    }

    // ==========================================
    // Telemetry Tracking Context (GPS Service)
    // ==========================================

    /**
     * Get Trip Tracking Context
     *
     * Retrieves trusted operational status and planned route snapshot for GPS tracking telemetry.
     *
     * @unauthenticated
     *
     * @throws NotFoundHttpException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{id: string, type: string, attributes: array{trip_id: string, status: string, planned_route_snapshot: array<string, mixed>}}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function trackingContext(Request $request, string $id): Response
    {
        return $this->proxyTo($request, Services::OPERATIONS->value, "trips/{$id}/tracking-context");
    }
}
