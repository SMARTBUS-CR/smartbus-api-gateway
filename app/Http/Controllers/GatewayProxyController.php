<?php

namespace App\Http\Controllers;

use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

#[Group(name: 'API Gateway Proxy', description: 'This group contains endpoints that act as a reverse proxy to various microservices. These endpoints validate authentication tokens and forward requests to the appropriate microservice, injecting necessary headers for user context.')]
class GatewayProxyController extends Controller
{
    /**
     * Get authenticated user profile.
     *
     * This endpoint retrieves the profile of the currently authenticated user
     * by forwarding the request to the Auth microservice.
     *
     * For more information about the profile endpoint, see the Auth microservice
     * documentation (https://smartbus-authentication.onrender.com/).
     */
    #[ResponseAttribute(status: 200, description: 'User Profile Data', type: 'array{id: int, email: string, name: string}')]
    public function getAuthUser(Request $request): Response
    {
        return $this->proxyTo($request, 'auth', 'user');
    }

    /**
     * Invalidate current user token.
     *
     * This endpoint logs out the user by invalidating the current authentication token
     * in the cache and forwarding the logout request to the Auth microservice.
     *
     * For more information about the logout endpoint, see the Auth microservice
     * documentation (https://smartbus-authentication.onrender.com/).
     */
    #[ResponseAttribute(status: 200, description: 'Successfully logged out')]
    public function postAuthLogout(Request $request): Response
    {
        return $this->proxyTo($request, 'auth', 'logout');
    }

    /**
     * Proxy requests handler
     *
     * This method acts as a dynamic reverse proxy and is called by all the routes listed in the API Gateway.
     * It determines the target microservice based on the first segment of the path and forwards the request accordingly.
     *
     * This method validates the authentication
     * token and injects context headers (`X-User-Id`, `X-User-Roles`) to the target microservice.
     *
     * This endpoint accepts any HTTP method (GET, POST, PUT, DELETE, etc.)
     *
     * @param  string  $service  Microservice Identifier (auth, trips, etc.)
     * @param  string|null  $path  Relative path to the microservice endpoint (e.g., 'user', 'admin/active').
     */
    #[ResponseAttribute(status: 200, description: 'Successful Response from Microservice', type: 'array{status: string, data: array}')]
    #[ResponseAttribute(status: 401, description: 'Invalid or Missing Token', type: 'array{message: "Unauthenticated"}')]
    #[ResponseAttribute(status: 502, description: 'Service Not Found', type: 'array{message: "Service Not Found"}')]
    public function proxyTo(Request $request, ?string $service = null, ?string $path = null): Response
    {
        // Determine service and path from request segments if not provided
        if (! $service) {
            $service = $request->segment(2); // e.g. 'auth' in /api/auth/user
            $path = implode('/', array_slice($request->segments(), 2)); // e.g. 'user' or 'admin/active'
        }

        // Check if the service is defined in the configuration
        $serviceUrl = "services.{$service}.url";
        $baseUrl = config($serviceUrl);

        // If the service is not found, return a 502 Bad Gateway response
        if (! $baseUrl) {
            return response()->json([
                'message' => __('Service Not Found'),
            ], Response::HTTP_BAD_GATEWAY);
        }

        $targetUrl = rtrim("{$baseUrl}/api/{$path}", '/');

        if ($path === 'logout' && $token = $request->bearerToken()) {
            // Invalidate the token in the cache
            Cache::forget('token_valid:'.hash('sha256', $token));
        }

        // Forward the request to the target microservice
        $headers = collect($request->headers->all())
            ->except(['host', 'content-length'])
            ->map(fn ($values) => $values[0])
            ->toArray();

        $client = Http::withHeaders($headers);
        if (app()->isLocal()) {
            $client->withoutVerifying();
        }

        $response = $client->send($request->method(), $targetUrl, [
            'query' => $request->query(),
            'json' => $request->json()->all(),
        ]);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }
}
