<?php

namespace App\Http\Controllers;

use App\Enums\Services;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('Auth Service - Roles', 'Endpoints for managing the role catalog in the Authentication microservice.', weight: 4)]
class RolesController extends Controller
{
    /**
     * List Roles
     *
     * Lists the roles supported by the application.
     *
     * @authenticate
     */
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[ResponseAttribute(status: Response::HTTP_OK, description: 'Available roles returned successfully.', mediaType: 'application/vnd.api+json', type: 'array{data: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: Response::HTTP_UNAUTHORIZED, description: 'Unauthenticated.')]
    #[ResponseAttribute(status: Response::HTTP_FORBIDDEN, description: 'Authorization error.')]
    public function index(Request $request): Response
    {
        return $this->proxyTo($request, Services::AUTH->value, 'roles');
    }
}
