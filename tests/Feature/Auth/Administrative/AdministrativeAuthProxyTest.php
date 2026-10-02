<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['smartbus.auth.url' => AUTH_SERVICE_URL]);
    Cache::flush();
});

describe('Administrative Proxy', function () {
    it('proxies every administrative auth endpoint after token validation', function (string $method, string $routeName, string $upstreamPath, array $parameters, array $payload) {
        $token = 'admin-token-'.str_replace('.', '-', $routeName);
        $upstreamUrl = AUTH_SERVICE_URL.'/api/'.$upstreamPath;

        Http::fake([
            AUTH_SERVICE_URL.'/api/token/validate' => Http::response(authTokenMeta(), 200),
            $upstreamUrl => Http::response(['meta' => ['message' => 'ok']], 200),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->json($method, route($routeName, $parameters), $payload);

        $response->assertOk();
        Http::assertSent(fn ($request) => $request->url() === $upstreamUrl
            && $request->method() === $method);
    })->with([
        'permissions' => ['GET', 'auth.permissions.index', 'permissions', [], []],
        'roles' => ['GET', 'auth.roles.index', 'roles', [], []],
        'list users' => ['GET', 'auth.users.index', 'users', [], []],
        'create user' => ['POST', 'auth.users.store', 'users', [], ['name' => 'Test User']],
        'show user' => ['GET', 'auth.users.show', 'users/10', ['user' => 10], []],
        'update user' => ['PATCH', 'auth.users.update', 'users/10', ['user' => 10], ['name' => 'Updated User']],
        'delete user' => ['DELETE', 'auth.users.destroy', 'users/10', ['user' => 10], []],
        'list user roles' => ['GET', 'auth.users.roles.index', 'users/10/roles', ['user' => 10], []],
        'sync user roles' => ['PUT', 'auth.users.roles.update', 'users/10/roles', ['user' => 10], ['roles' => ['driver']]],
        'assign user role' => ['POST', 'auth.users.roles.store', 'users/10/roles/driver', ['user' => 10, 'role' => 'driver'], []],
        'revoke user role' => ['DELETE', 'auth.users.roles.destroy', 'users/10/roles/driver', ['user' => 10, 'role' => 'driver'], []],
        'list user permissions' => ['GET', 'auth.users.permissions.index', 'users/10/permissions', ['user' => 10], []],
        'sync user permissions' => ['PUT', 'auth.users.permissions.update', 'users/10/permissions', ['user' => 10], ['permissions' => ['manage-users']]],
        'assign user permission' => ['POST', 'auth.users.permissions.store', 'users/10/permissions/manage-users', ['user' => 10, 'permission' => 'manage-users'], []],
        'revoke user permission' => ['DELETE', 'auth.users.permissions.destroy', 'users/10/permissions/manage-users', ['user' => 10, 'permission' => 'manage-users'], []],
    ]);
});

describe('Administrative Authentication', function () {
    it('returns 401 for administrative endpoints without a bearer token', function () {
        $this->getJson(route('auth.users.index'))
            ->assertUnauthorized()
            ->assertJson(['error' => __('http-statuses.401')]);

        Http::assertNothingSent();
    });
});
