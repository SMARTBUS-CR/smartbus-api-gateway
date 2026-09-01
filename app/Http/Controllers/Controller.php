<?php

namespace App\Http\Controllers;

use App\Enums\Services;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use function array_slice;

abstract class Controller
{
    /**
     * Proxy the incoming request to the specified microservice.
     *
     * This method forwards the request to the appropriate microservice based on the provided service name and path.
     * It collects the necessary headers, sends the request, and returns the response from the microservice.
     *
     * @param  Request  $request  The incoming HTTP request to be proxied.
     * @param  mixed  $service  The name of the microservice to which the request should be proxied. If not provided, it will be inferred from the request URL.
     * @param  mixed  $path  The specific path within the microservice to which the request should be proxied. If not provided, it will be inferred from the request URL.
     * @param  callable|null  $callback  An optional callback function to be executed with the response from the microservice before returning it to the client.
     * @return Response The HTTP response from the microservice, returned to the client.
     *
     * @throws \InvalidArgumentException If the specified service is not recognized or configured in the application.
     */
    protected function proxyTo(Request $request, ?string $service = null, ?string $path = null, ?callable $callback = null): Response
    {
        Log::info("Proxying request to service: {$service}, path: {$path}");

        if (! $service) {
            $service = $request->segment(2); // e.g. 'auth' in 'api/auth/login'
            $path = implode('/', array_slice($request->segments(), 2)); // e.g. 'login' or 'logout'
        }

        $serviceUrl = rtrim($this->inferServiceUrl($service).$path, '/');

        Log::info("Service URL inferred: $serviceUrl");

        $client = Http::withHeaders($this->collectHeaders($request))
            ->when(app()->isLocal(), fn ($http) => $http->withoutVerifying());

        $response = $client->send($request->method(), $serviceUrl, [
            'query' => $request->query(),
            'json' => $request->json()->all(),
        ]);

        Log::info('Response received from service', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($callback) {
            $callback($response);
        }

        return response(
            $response->body(),
            $response->status(),
        )->header('Content-Type', 'application/json');
    }

    /**
     * Infer the base URL of the specified microservice based on its name.
     *
     * This method retrieves the base URL of a microservice from the application's configuration based on the provided service name.
     * If the service name is not recognized, an InvalidArgumentException is thrown.
     *
     * @param  string|null  $service  The name of the microservice for which to infer the base URL.
     * @return string The base URL of the specified microservice.
     *
     * @throws \InvalidArgumentException If the specified service is not recognized or configured in the application.
     */
    private function inferServiceUrl(?string $service = null): string
    {
        $url = match ($service) {
            Services::AUTH->value => config('services.auth.url'),
            default => throw new \InvalidArgumentException(__('Service Not Found').": {$service}"),
        };

        return "$url/api/";
    }

    /**
     * Collect headers from the incoming request, excluding certain headers that should not be forwarded.
     *
     * This method retrieves all headers from the incoming request and removes headers that are not relevant for proxying,
     * such as 'host', 'content-length', and 'accept-encoding'. The remaining headers are returned as an associative array.
     *
     * @param  Request  $request  The incoming HTTP request from which to collect headers.
     * @return array An associative array of headers to be forwarded to the microservice.
     */
    private function collectHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        unset(
            $headers['host'],
            $headers['content-length'],
            $headers['accept-encoding']
        );

        return array_map(fn ($values) => $values[0], $headers);
    }
}
