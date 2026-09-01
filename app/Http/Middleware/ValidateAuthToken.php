<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateAuthToken
{
    /**
     * Handle an incoming request.
     *
     * This middleware validates the Bearer token provided in the request's Authorization header.
     * It checks the token's validity by consulting the cache first. If the token is not found
     * in the cache, it makes a request to the Authentication service to validate the token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::info("Validating auth token for request to {$request->path()}");
        $token = $request->bearerToken();

        if (empty($token)) {
            Log::error("No Bearer Token Found in request to {$request->path()}");

            return response()->json(
                ['error' => __('http-statuses.401')],
                Response::HTTP_UNAUTHORIZED
            );
        }

        $cacheKey = 'token_valid:'.hash('sha256', $token);

        $userData = Cache::remember($cacheKey, 600, function () use ($token) {
            Log::info('Token not found in cache, validating with Auth service');

            try {
                $response = Http::baseUrl(config('services.auth.url'))
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout(5)
                    ->when(app()->isLocal(), fn ($http) => $http->withoutVerifying())
                    ->post('/api/token/validate');

                Log::info('Token validation response: ', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                if (! $response->successful()) {
                    return null;
                }

                $data = $response->json('meta');

                if (empty($data['valid']) || $data['valid'] !== true) {
                    return null;
                }

                Log::info('Token is valid, caching user data');

                $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : now()->addMinutes(10);
                $ttl = min(now()->diffInSeconds($expiresAt, false), 600);

                return $ttl > 0 ? $data : null;

            } catch (\Exception $e) {
                Log::error('Error validating token: '.$e->getMessage());

                return null;
            }
        });

        if (! $userData) {
            Log::error('Token is invalid or expired for request to '.$request->path());

            return response()->json([
                'message' => __('Invalid or expired token.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
