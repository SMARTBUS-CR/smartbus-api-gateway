<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Services;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Auth Service - User Permissions', 'User permission management endpoints in the Authentication microservice.', weight: 7)]
class UserPermissionsController extends Controller
{
    /**
     * List User Permissions
     *
     * List the permissions assigned to a user.
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'User permissions returned successfully.', mediaType: 'application/json', type: 'array{data: array{type: string, id: string, attributes: array{direct: array<int, mixed>, effective: array<int, mixed>}}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'Not found.')]
    public function permissions(Request $request, int $user): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}/permissions");
    }

    /**
     * Update User Permissions
     *
     * Replace all permissions assigned to a user.
     */
    #[BodyParameter('permissions', type: 'array<string>', required: true)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'User permissions synchronized successfully.', mediaType: 'application/json', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'Not found.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'The permission list is invalid.')]
    public function syncPermissions(Request $request, int $user): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}/permissions");
    }

    /**
     * Assign Permission to User
     *
     * Assign a supported permission to a user.
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Permission assigned successfully.', mediaType: 'application/json', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'The requested permission was not found.')]
    public function assignPermission(Request $request, int $user, string $permission): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}/permissions/{$permission}");
    }

    /**
     * Revoke Permission from User
     *
     * Revoke a permission from a user.
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Permission revoked successfully.', mediaType: 'application/json', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'The requested permission was not found.')]
    public function revokePermission(Request $request, int $user, string $permission): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}/permissions/{$permission}");
    }
}
