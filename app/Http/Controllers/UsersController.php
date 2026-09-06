<?php

namespace App\Http\Controllers;

use App\Enums\Services;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Auth Service - Users', 'Administrative user management endpoints in the Authentication microservice.', weight: 3)]
class UsersController extends Controller
{
    /**
     * List
     *
     * Get a paginated list of users with optional filtering, sorting, and inclusion of related roles.
     */
    #[QueryParameter('filter[name]', type: 'string')]
    #[QueryParameter('filter[email]', type: 'string')]
    #[QueryParameter('filter[role]', type: 'string')]
    #[QueryParameter('page[number]', type: 'integer')]
    #[QueryParameter('page[size]', type: 'integer')]
    #[QueryParameter('sort', type: 'string')]
    #[QueryParameter('include', type: 'string')]
    #[QueryParameter('fields[users]', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[ResponseAttribute(status: Response::HTTP_OK, description: 'Paginated users returned successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{type: string, id: string, attributes: array<string, mixed>}>, links: array{first: string|null, last: string|null, prev: string|null, next: string|null}, meta: array<string, mixed>}')]
    #[ResponseAttribute(status: Response::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: Response::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error.')]
    public function index(Request $request): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, 'users');
    }

    /**
     * Create
     *
     * Create a new user and assign the requested roles. The super-admin role can only
     * be assigned by users with the 'roles.manage' permission.
     *
     * @throws ValidationException
     */
    #[QueryParameter('include', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[users]', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[BodyParameter('name', type: 'string', required: true)]
    #[BodyParameter('email', type: 'string', format: 'email', required: true)]
    #[BodyParameter('password', type: 'string', format: 'password', required: true)]
    #[BodyParameter('password_confirmation', type: 'string', format: 'password', required: true)]
    #[BodyParameter('roles', type: 'array<string>')]
    #[ResponseAttribute(status: Response::HTTP_OK, description: 'User created successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array{type: string, id: string, attributes: array<string, mixed>}, included?: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: Response::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: Response::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: Response::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation error.')]
    public function store(Request $request): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, 'users');
    }

    /**
     * Show
     *
     * Get a user with its assigned roles and effective permissions.
     */
    #[QueryParameter('include', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[users]', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'User returned successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array{type: string, id: string, attributes: array<string, mixed>}, included?: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'The requested user was not found.')]
    public function show(Request $request, int $user): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}");
    }

    /**
     * Update
     *
     * Update a user's profile and optionally change its password. If the password is changed,
     * all existing tokens for the user will be revoked.
     */
    #[QueryParameter('include', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[users]', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[BodyParameter('name', type: 'string', required: false)]
    #[BodyParameter('email', type: 'string', format: 'email', required: false)]
    #[BodyParameter('password', type: 'string', format: 'password', required: false)]
    #[BodyParameter('password_confirmation', type: 'string', format: 'password', required: false)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'User updated successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array{type: string, id: string, attributes: array<string, mixed>}, included?: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'Not found.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'The user data is invalid.')]
    public function update(Request $request, int $user): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}");
    }

    /**
     * Delete
     *
     * Delete a user after applying administrator safety rules. A user cannot delete themselves,
     * and the last administrator cannot be deleted.
     */
    #[HeaderParameter('Accept-Language', type: "'en'|'es'", default: 'es')]
    #[ResponseAttribute(status: Response::HTTP_OK, description: 'User deleted successfully.', mediaType: 'application/json', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Authorization error.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_NOT_FOUND, description: 'Not found.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_CONFLICT, description: 'The deletion violates a user safety rule.')]
    public function destroy(Request $request, int $user): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, "users/{$user}");
    }
}
