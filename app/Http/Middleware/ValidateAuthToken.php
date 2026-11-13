<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateAuthToken
{
    /**
     * Validate the Bearer token against Authentication on every protected request.
     *
     * Positive validation results are cached with a short TTL to optimize
     * high-frequency telemetry and requests, bounded by token lifetime.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('auth.token.validate')) {
            return $next($request);
        }

        $token = $request->bearerToken();

        if (empty($token)) {
            return response()->json(
                ['error' => __('http-statuses.401')],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $cacheKey = 'gateway:token_valid:'.hash('sha256', $token);

        if (Cache::get($cacheKey) === true) {
            return $next($request);
        }

        try {
            $response = Http::baseUrl(config('smartbus.auth.url'))
                ->withToken($token)
                ->withHeaders([
                    'Accept-Language' => $request->header(
                        'Accept-Language',
                        (string) config('app.locale', 'en'),
                    ),
                ])
                ->acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->when(app()->isLocal(), fn ($http) => $http->withoutVerifying())
                ->post('/api/token/validate');

            Log::info('Token validation response received', [
                'status' => $response->status(),
            ]);

            if (! $response->successful()) {
                return response(
                    $response->body(),
                    $response->status(),
                )->header(
                    'Content-Type',
                    $response->header('Content-Type') ?: 'application/json',
                );
            }

            $data = $response->json('meta');

            $isValid = $response->successful()
                && is_array($data)
                && ($data['valid'] ?? null) === true
                && (
                    ! isset($data['expires_at'])
                    || Carbon::parse($data['expires_at'])->isFuture()
                );

            if ($isValid) {
                $ttl = (int) config('smartbus.auth.token_cache_ttl', 30);
                if (isset($data['expires_at'])) {
                    $ttl = min($ttl, max(0, (int) now()->diffInSeconds(Carbon::parse($data['expires_at']), false)));
                }
                if ($ttl > 0) {
                    Cache::put($cacheKey, true, $ttl);
                }
            }
        } catch (ConnectionException $exception) {
            Log::warning('Authentication service is unreachable.', [
                'exception_type' => $exception::class,
            ]);

            return response()->json([
                'errors' => [[
                    'status' => '503',
                    'code' => 'authentication_service_unavailable',
                    'title' => __('Service Unavailable'),
                    'detail' => __('Authentication service is temporarily unavailable.'),
                ]],
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        } catch (Exception $exception) {
            Log::error('Authentication token validation failed', [
                'exception_type' => $exception::class,
            ]);

            $isValid = false;
        }

        if (! $isValid) {
            return response()->json([
                'message' => __('Invalid or expired token.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
