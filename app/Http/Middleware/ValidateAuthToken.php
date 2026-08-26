<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class ValidateAuthToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (empty($token)) {
            return response()->json([
                'error' => __('http-statuses.401'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Usar sha256 en lugar de md5 por mayor seguridad
        $cacheKey = 'token_valid:'.hash('sha256', $token);
        $userData = Cache::get($cacheKey);

        if (! $userData) {
            try {
                $response = Http::baseUrl(config('services.auth.url'))
                    ->withToken($token)
                    ->acceptJson()
                    ->timeout(5) // Evitar que el Gateway se cuelgue si el servicio de Auth está lento
                    ->get('/api/tokens/validate');

                if ($response->successful()) {
                    $data = $response->json();

                    // Verificar explícitamente el campo 'valid'
                    if (isset($data['valid']) && $data['valid'] === true) {
                        $userData = $data;

                        // Calcular el tiempo de caché basado en 'expires_at' (máximo 10 minutos para detectar revocaciones)
                        $expiresAt = isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : now()->addMinutes(10);
                        $ttl = min(now()->diffInSeconds($expiresAt, false), 600);

                        if ($ttl > 0) {
                            Cache::put($cacheKey, $userData, $ttl);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Manejar fallos de conexión sin exponer detalles
                $userData = null;
            }
        }

        if (! $userData) {
            return response()->json([
                'message' => __('Invalid or expired token.'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $locale = $request->header('Accept-Language');
        $request->headers->set('Accept-Language', $locale);

        return $next($request);
    }
}
