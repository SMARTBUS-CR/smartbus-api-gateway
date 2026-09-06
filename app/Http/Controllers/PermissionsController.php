<?php

namespace App\Http\Controllers;

use App\Enums\Services;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('Auth Service - Permissions', 'Endpoints for managing the permission catalog in the Authentication microservice.', weight: 5)]
class PermissionsController extends Controller
{
    /**
     * List Permissions
     *
     * Lists the permissions available to the application.
     *
     * @authenticate
     */
    #[QueryParameter('fields[permissions]', type: 'array<string>', infer: false)]
    #[ResponseAttribute(status: Response::HTTP_OK, description: 'Available permissions returned successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: Response::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: Response::HTTP_FORBIDDEN, description: 'Authorization error.')]
    public function index(Request $request): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, 'permissions');
    }
}
