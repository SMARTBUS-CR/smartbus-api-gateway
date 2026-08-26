<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

use function in_array;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Read the 'Accept-Language' header from the request
        $locale = $request->header('Accept-Language');
        $supportedLocales = ['es', 'en'];

        // If the requested language is supported, we apply it
        if ($locale && in_array($locale, $supportedLocales)) {
            App::setLocale($locale);
        } else {
            // Otherwise, we use the default language (en)
            App::setLocale(config('app.fallback_locale'));
        }

        return $next($request);
    }
}
