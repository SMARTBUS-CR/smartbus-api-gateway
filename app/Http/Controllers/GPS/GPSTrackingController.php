<?php

namespace App\Http\Controllers\GPS;

use App\Enums\Services;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[Group('GPS Tracking Service', 'This group contains endpoints that interact with the GPS microservice. <br>These endpoints handle GPS location data and related operations by forwarding requests to the GPS microservice.<br><br>For more information about the GPS microservice, see the [SmartBus GPS Tracking](https://smartbus-gps-tracking.onrender.com/) documentation.', weight: 7)]
class GPSTrackingController extends Controller
{
    /**
     * Store GPS Location
     *
     * Stores a new GPS location data point in the system.
     * The data will be forwarded to the GPS microservice for processing and storage.
     *
     * For more information about the GPS location endpoint, see the
     * [Store GPS Location](https://smartbus-gps-tracking.onrender.com/docs/api#tag/gps/POST/locations)
     * section in the GPS microservice documentation.
     *
     * @throws ValidationException A validation exception is thrown if the request data does
     *                             not meet the required format or constraints.
     */
    #[BodyParameter('data', type: 'array', required: true, description: 'The GPS location data to be stored.')]
    #[BodyParameter('data.type', type: 'string', required: true, description: 'The type of the GPS location data.')]
    #[BodyParameter('data.attributes', type: 'object', required: true, description: 'The attributes of the GPS location data.')]
    #[BodyParameter('data.attributes.trip_id', type: 'int', required: true, description: 'The ID of the trip associated with the GPS location data.', example: 42)]
    #[BodyParameter('data.attributes.latitude', type: 'numeric', required: true, description: 'The latitude of the GPS location data.', example: 42.123456)]
    #[BodyParameter('data.attributes.longitude', type: 'numeric', required: true, description: 'The longitude of the GPS location data.', example: -71.123456)]
    #[BodyParameter('data.attributes.speed_kmh', type: 'numeric', required: false, description: 'Registered speed in km/h (0 - 400).', example: 45.5)]
    #[BodyParameter('data.attributes.recorded_at', type: 'string', required: true, description: 'The timestamp of when the GPS location data was recorded (ISO 8601 Format).', example: '2026-09-12T23:30:00Z')]
    #[ResponseAttribute(status: HttpStatus::HTTP_CREATED, type: 'array{data: array{id: string, type: string, attributes: array{trip_id: int, latitude: float, longitude: float, speed_kmh: float|null, recorded_at: string}, relationships: array{trip: array{data: array{id: string, type: string}}}}, included: array<int, array{id: string, type: string, attributes: array{route_id: int, bus_id: int, driver_id: string, status: string, started_at: string|null}}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_ACCEPTED, type: 'array{meta: array{persisted: bool}}')]
    public function store(Request $request): Response
    {
        /** @status 201 */
        return $this->proxyTo(
            $request,
            Services::GPS->value,
            'locations'
        );
    }

    /**
     * Get Latest Location for Trip
     *
     * Retrieves the most recent GPS location entry associated with a specific trip.
     *
     * For more information about the latest trip location endpoint, see the
     * [Get Latest Location](https://smartbus-gps-tracking.onrender.com/docs/api#tag/gps-locations/GET/trips/{tripId}/location)
     * section in the GPS microservice documentation.
     *
     * @throws ModelNotFoundException A ModelNotFoundException is thrown if no GPS location data is found for the specified trip ID.
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, type: 'array{message: string}')]
    public function latestForTrip(string $tripId): Response
    {
        /**
         * @status 200
         *
         * @body array{
         *     data: array{
         *         id: string,
         *         type: string,
         *         attributes: array{
         *             trip_id: int,
         *             latitude: float,
         *             longitude: float,
         *             speed_kmh: float|null,
         *             recorded_at: string
         *         },
         *         relationships: array{
         *             trip: array{
         *                 data: array{
         *                     id: string,
         *                     type: string
         *                 }
         *             }
         *         }
         *     },
         *     included: array<int, array{
         *         id: string,
         *         type: string,
         *         attributes: array{
         *             route_id: int,
         *             bus_id: int,
         *             driver_id: string,
         *             status: string,
         *             started_at: string|null
         *         }
         *     }>
         * }
         */
        return $this->proxyTo(
            request(),
            Services::GPS->value,
            "trips/{$tripId}/location"
        );
    }

    /**
     * Reverb / WebSocket Private Channel Authentication
     *
     * Validates the user's authentication and authorizes access to private channels for WebSocket connections (e.g., Reverb or Pusher).
     * This endpoint is used to ensure that only authenticated users can subscribe to private channels for real-time updates.
     *
     * For more information about the broadcasting authentication endpoint, see the
     * [Broadcasting Authentication](https://smartbus-gps-tracking.onrender.com/docs/api#tag/gps-broadcasting/POST/broadcasting/auth)
     * section in the GPS microservice documentation.
     *
     * @unauthenticated
     *
     * @throws ValidationException A validation exception is thrown if the request data does not meet the required format or constraints.
     * @throws ModelNotFoundException A ModelNotFoundException is thrown if the user or channel cannot be found or if the user is not authorized to access the specified channel.
     * @throws AuthenticationException An AuthenticationException is thrown if the user is not authenticated or if the provided credentials are invalid.
     * @throws AuthorizationException An AuthorizationException is thrown if the user is authenticated but does not have permission to access the specified channel.
     * @throws HttpException An HttpException is thrown for any other HTTP-related errors that may occur during the request processing.
     */
    #[BodyParameter('socket_id', type: 'string', required: true, description: 'The socket ID for the WebSocket (Reverb/Pusher) connection.', example: '12345.67890')]
    #[BodyParameter('channel_name', type: 'string', required: true, description: 'The name of the private channel to which the user is subscribing.', example: 'private-trip.1')]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, type: 'array{auth: string}', examples: ['{"auth": "REVERB_APP_KEY:df89a1b2c3d4e5f6..."}'])]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, type: 'array{message: string}', examples: ['{"message": "Unauthenticated."}'])]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, type: 'array{message: string}', examples: ['{"message": "Access denied to channel."}'])]
    public function authenticateBroadcast(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::GPS->value,
            'broadcasting/auth'
        );
    }
}
