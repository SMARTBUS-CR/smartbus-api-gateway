<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use function Pest\Laravel\withToken;

beforeEach(function (): void {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
});

describe('Administrative Authentication Proxy Retirement', function (): void {
    it('does not expose or forward the retired endpoint', function (string $method, string $path): void {
        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(
                authTokenMeta([
                    'roles' => ['super-admin'],
                ]),
                200,
            ),
            AUTH_SERVICE_URL.'/api/*' => Http::response([
                'meta' => ['message' => 'Administrative request forwarded.'],
            ], 200),
        ]);

        $path = str_replace('{user}', (string) Str::uuid(), $path);

        withToken(TEST_TOKEN)
            ->json($method, $path, [])
            ->assertNotFound();

        Http::assertNothingSent();
    })->with([
        'list users' => ['GET', '/api/auth/users'],
        'create user' => ['POST', '/api/auth/users'],
        'show user' => ['GET', '/api/auth/users/{user}'],
        'update user' => ['PATCH', '/api/auth/users/{user}'],
        'delete user' => ['DELETE', '/api/auth/users/{user}'],

        'list roles' => ['GET', '/api/auth/roles'],
        'list permissions' => ['GET', '/api/auth/permissions'],

        'list user roles' => ['GET', '/api/auth/users/{user}/roles'],
        'replace user roles' => ['PUT', '/api/auth/users/{user}/roles'],
        'assign user role' => ['POST', '/api/auth/users/{user}/roles/driver'],
        'revoke user role' => ['DELETE', '/api/auth/users/{user}/roles/driver'],

        'list user permissions' => ['GET', '/api/auth/users/{user}/permissions'],
        'replace user permissions' => ['PUT', '/api/auth/users/{user}/permissions'],
        'assign user permission' => ['POST', '/api/auth/users/{user}/permissions/Update:User'],
        'revoke user permission' => ['DELETE', '/api/auth/users/{user}/permissions/Update:User'],
    ]);
});
