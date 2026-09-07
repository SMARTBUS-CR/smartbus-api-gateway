<?php

namespace App\Http\Controllers;

use App\Enums\Services;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('GPS Service', 'This group contains endpoints that interact with the GPS microservice. <br>These endpoints receive GPS coordinates from the driver app, expose the latest known position of a trip, and authorize the real-time WebSocket channel, by forwarding requests to the GPS microservice.<br><br>All responses follow the JSON:API standard (`application/vnd.api+json`), except the broadcast authorization endpoint which returns the Pusher protocol payload. The WebSocket connection itself is served by Laravel Reverb and does not go through the gateway.', weight: 8)]
class GpsController extends Controller
{
    /**
     * Health Check
     *
     * Returns a simple status payload from the GPS microservice. Intended for
     * readiness and liveness probes. This endpoint does not require authentication.
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'GPS microservice is operational.', type: 'array{service: string, status: string}')]
    public function ping(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::GPS->value,
            'ping'
        );
    }

    /**
     * Store GPS Location
     *
     * Registers a new GPS coordinate for an in-progress trip. The driver app sends the
     * reading and the GPS microservice persists it and broadcasts a `BusLocationUpdated`
     * event over WebSockets so connected passengers receive the new position.
     *
     * The request body must follow the JSON:API document structure.
     *
     * @authenticate
     *
     * @throws ValidationException
     */
    #[BodyParameter('data.type', description: 'JSON:API resource type. Must be "gps-locations".', type: 'string', example: 'gps-locations', infer: false)]
    #[BodyParameter('data.attributes.trip_id', description: 'Identifier of the trip the coordinate belongs to.', type: 'integer', example: 25, infer: false)]
    #[BodyParameter('data.attributes.latitude', description: 'Latitude in decimal degrees (-90 to 90).', type: 'number', format: 'float', example: 10.4631, infer: false)]
    #[BodyParameter('data.attributes.longitude', description: 'Longitude in decimal degrees (-180 to 180).', type: 'number', format: 'float', example: -83.9921, infer: false)]
    #[BodyParameter('data.attributes.speed_kmh', description: 'Speed in km/h. Optional.', required: false, type: 'number', format: 'float', example: 38.5, infer: false)]
    #[BodyParameter('data.attributes.recorded_at', description: 'ISO-8601 timestamp of the reading, normalized to UTC.', type: 'string', format: 'date-time', example: '2026-09-03T15:00:00Z', infer: false)]
    #[ResponseAttribute(status: HttpStatus::HTTP_CREATED, description: 'Coordinate stored successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', type: 'array{message: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error.', mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string, source: array{pointer: string}}>}')]
    public function storeLocation(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::GPS->value,
            'locations'
        );
    }

    /**
     * Latest Trip Location
     *
     * Returns the most recent GPS coordinate recorded for the given trip. The passenger
     * app calls this to place the bus marker on the map before the first real-time
     * `BusLocationUpdated` event arrives, and again after a lost connection is restored.
     *
     * @authenticate
     *
     * @throws UnauthorizedException
     */
    #[PathParameter('tripId', description: 'Identifier of the trip.', type: 'integer', example: 25)]
    #[QueryParameter('include', description: 'Comma-separated related resources to embed. Supported: "trip".', required: false, type: 'string', example: 'trip', infer: false)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Latest coordinate of the trip.', mediaType: 'application/vnd.api+json', type: 'array{data: array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', type: 'array{message: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'The trip does not exist or has no GPS readings yet.', mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    public function showTripLocation(Request $request, string $tripId): Response
    {
        return $this->proxyTo(
            $request,
            Services::GPS->value,
            "trips/{$tripId}/location"
        );
    }

    /**
     * Authorize Broadcast Channel
     *
     * Authorizes a passenger's subscription to the private `trip.{tripId}` WebSocket
     * channel (Laravel Reverb, Pusher protocol). Laravel Echo calls this automatically
     * on the client. The GPS microservice returns the subscription signature when the
     * authenticated user is allowed to follow the trip and the trip is active.
     *
     * @authenticate
     *
     * @throws UnauthorizedException
     */
    #[BodyParameter('channel_name', description: 'Name of the private channel, e.g. "private-trip.25".', type: 'string', example: 'private-trip.25', infer: false)]
    #[BodyParameter('socket_id', description: 'Socket id assigned by Reverb on connection.', type: 'string', example: '123456.789', infer: false)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Subscription authorized. Returns the Pusher auth signature.', type: 'array{auth: string}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'The user may not follow this trip, or the trip is not active.', mediaType: 'application/vnd.api+json', type: 'array{errors: array<int, array{status: string, title: string, detail: string}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', type: 'array{message: string}')]
    public function authorizeChannel(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::GPS->value,
            'broadcasting/auth'
        );
    }
}
