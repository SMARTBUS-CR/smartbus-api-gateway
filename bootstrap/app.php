<?php

use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ValidateAuthToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: [
            __DIR__.'/../routes/auth.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(append: [
            SetLocale::class,
            'throttle:api',
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'validate.token' => ValidateAuthToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $errorStatus = static function (Throwable $exception): int {
            return match (true) {
                $exception instanceof ValidationException => HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                $exception instanceof AuthenticationException => HttpResponse::HTTP_UNAUTHORIZED,
                $exception instanceof AuthorizationException => HttpResponse::HTTP_FORBIDDEN,
                $exception instanceof ModelNotFoundException => HttpResponse::HTTP_NOT_FOUND,
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                default => HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
            };
        };

        $errorTitle = static function (int $status): string {
            return match ($status) {
                HttpResponse::HTTP_UNAUTHORIZED => 'Unauthorized',
                HttpResponse::HTTP_FORBIDDEN => 'Forbidden',
                HttpResponse::HTTP_NOT_FOUND => 'Not Found',
                HttpResponse::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
                HttpResponse::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY => 'Validation Error',
                default => $status >= 500 ? 'Server Error' : 'Request Error',
            };
        };

        $errorDetail = static function (Throwable $exception, int $status): string {
            if ($exception instanceof HttpExceptionInterface && $status < HttpResponse::HTTP_INTERNAL_SERVER_ERROR && $exception->getMessage() !== '') {
                return $exception->getMessage();
            }

            return match ($status) {
                HttpResponse::HTTP_UNAUTHORIZED => 'Authentication is required.',
                HttpResponse::HTTP_FORBIDDEN => 'You are not authorized to perform this action.',
                HttpResponse::HTTP_NOT_FOUND => 'The requested resource was not found.',
                HttpResponse::HTTP_METHOD_NOT_ALLOWED => 'The requested method is not allowed.',
                HttpResponse::HTTP_TOO_MANY_REQUESTS => 'Too many requests.',
                HttpResponse::HTTP_UNPROCESSABLE_ENTITY => 'The given data was invalid.',
                default => 'An unexpected error occurred.',
            };
        };

        $errorObjects = static function (Throwable $exception) use ($errorStatus, $errorTitle, $errorDetail): array {
            if ($exception instanceof ValidationException) {
                return collect($exception->errors())
                    ->flatMap(fn (array $messages, string $field): array => array_map(
                        fn (string $message): array => [
                            'status' => '422',
                            'title' => 'Validation Error',
                            'detail' => $message,
                            'source' => ['pointer' => "/data/attributes/{$field}"],
                        ],
                        $messages,
                    ))
                    ->values()
                    ->all();
            }

            $status = $errorStatus($exception);

            return [[
                'status' => (string) $status,
                'title' => $errorTitle($status),
                'detail' => $errorDetail($exception, $status),
            ]];
        };

        $exceptions->render(function (Throwable $exception, Request $request) use ($errorObjects, $errorStatus) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'errors' => $errorObjects($exception),
            ], $errorStatus($exception));
        });
    })->create();
