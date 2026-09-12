<?php

namespace App\Http\Controllers;

use App\Enums\Services;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Auth Service - User Roles', 'User role management endpoints in the Authentication microservice.', weight: 6)]
class UserRolesController extends Controller
{
    /**
     * List User Roles
     *
     * List the roles assigned to a user.
     */
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'User roles returned successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'Not found.')]
    public function roles(Request $request, int $user): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}/roles");
    }

    /**
     * Update User Roles
     *
     * Replace all roles assigned to a user.
     */
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[BodyParameter('roles', type: 'array<string>', required: true)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'User roles synchronized successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'Not found.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'The role list is invalid.')]
    public function syncRoles(Request $request, int $user): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}/roles");
    }

    /**
     * Assign Role to User
     *
     * Assign a supported role to a user.
     */
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Role assigned successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'Not found.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'The requested role is invalid.')]
    public function assignRole(Request $request, int $user, string $role): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}/roles/{$role}");
    }

    /**
     * Revoke Role from User
     *
     * Revoke a role from a user.
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Role revoked successfully.', mediaType: 'application/json', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'Not found.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_CONFLICT, description: 'The revocation violates an administrator safety rule.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error.')]
    public function revokeRole(Request $request, int $user, string $role): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}/roles/{$role}");
    }
}
