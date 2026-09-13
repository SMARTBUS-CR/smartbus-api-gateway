<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Services;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Authentication Service', 'This group contains endpoints that interact with the Authentication microservice. <br>These endpoints handle user registration, login, and logout operations by forwarding requests to the Auth microservice.<br><br>For more information about the Auth microservice, see the [SmartBus Authentication](https://smartbus-authentication.onrender.com/) documentation.', weight: 1)]
class AuthController extends Controller
{
    /**
     * Register Passenger
     *
     * Registers a new passenger user in the system.
     * The user will be assigned the `passenger` role and an access token will be generated for them.
     *
     * For more information about the registration endpoint, see the
     * [Register Passenger](https://smartbus-authentication.onrender.com/docs/api#tag/authentication/POST/register/passenger)
     * section in the Auth microservice documentation.
     *
     * @unauthenticated
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
    #[ResponseAttribute(status: HttpStatus::HTTP_CREATED, mediaType: 'application/vnd.api+json', type: 'array{data: array{type: string, id: string, attributes: array<string, mixed>}, included?: array<int, array{type: string, id: string, attributes: array<string, mixed>}}, meta: array{access_token: string, token_type: string, expires_at: string}}')]
    public function registerPassenger(Request $request): Response
    {
        /**
         * @status 201
         *
         * @body array{
         *     data: array{
         *         id: string,
         *         type: string,
         *         attributes: array<string, string>,
         *     },
         *     meta: array{
         *         access_token: string,
         *         token_type: string,
         *         expires_at: string,
         *     }
         * }
         */
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'register/passenger'
        );
    }

    /**
     * Login
     *
     * Authenticates a user and generates a new access token.
     * All previous tokens for the user will be revoked.
     *
     * For more information about the login endpoint, see the
     * [Login](https://smartbus-authentication.onrender.com/docs/api#tag/authentication/POST/login)
     * section in the Auth microservice documentation.
     *
     * @unauthenticated
     *
     * @throws ValidationException
     */
    #[QueryParameter('include', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[users]', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[BodyParameter('email', type: 'string', format: 'email', required: true)]
    #[BodyParameter('password', type: 'string', required: true)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{type: string, id: string, attributes: array<string, mixed>}, included?: array<int, array{type: string, id: string, attributes: array<string, mixed>}}, meta: array{access_token: string, token_type: string, expires_at: string}}')]
    public function login(Request $request): Response
    {
        Log::info('Login request received', ['request' => $request->all()]);

        /**
         * @body array{
         *     data: array{
         *         id: string,
         *         type: string,
         *         attributes: array<string, string>,
         *     },
         *     meta: array{
         *         access_token: string,
         *         token_type: string,
         *         expires_at: string,
         *     }
         * }
         */
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'login'
        );
    }

    /**
     * Validate Token
     *
     * Validates the current access token for the authenticated user.
     * Returns a JSON response indicating whether the token is valid or not.
     *
     * For more information about the validate token endpoint, see the
     * [Validate Token](https://smartbus-authentication.onrender.com/docs/api#tag/authentication/POST/token/validate)
     * section in the Auth microservice documentation.
     *
     * @throws UnauthorizedException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/json', type: 'array{meta: array{valid: bool, expires_at: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', mediaType: 'application/json', type: 'array{errors: array{status: string, title: string, detail: string}}')]
    public function validateToken(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'token/validate'
        );
    }

    /**
     * Logout
     *
     * Revokes the current access token for the authenticated user,
     * effectively logging them out.
     *
     * For more information about the logout endpoint, see the
     * [Logout](https://smartbus-authentication.onrender.com/docs/api#tag/authentication/POST/logout)
     * section in the Auth microservice documentation.
     *
     * @throws UnauthorizedException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Successfully logged out.', mediaType: 'application/json', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', mediaType: 'application/json', type: 'array{message: string}')]
    public function logout(Request $request): Response
    {
        $token = $request->bearerToken();
        $clearCacheBeforeLogout = fn ($response) => $response->status() === HttpStatus::HTTP_OK
            ? Cache::forget('token_valid:'.hash('sha256', $token))
            : null;

        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'logout',
            $clearCacheBeforeLogout
        );
    }

    /**
     * User Information
     *
     * Returns the authenticated user's information along with their roles.
     *
     * For more information about the user endpoint, see the
     * [Get Authenticated User](https://smartbus-authentication.onrender.com/docs/api#tag/authentication/GET/user)
     * section in the Auth microservice documentation.
     *
     * @throws UnauthorizedException
     */
    #[QueryParameter('include', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[users]', type: 'array<string>', infer: false)]
    #[QueryParameter('fields[roles]', type: 'array<string>', infer: false)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, mediaType: 'application/vnd.api+json', type: 'array{data: array{type: string, id: string, attributes: array<string, mixed>, relationships?: array<string, mixed>}, included?: array<int, array{type: string, id: string, attributes: array<string, mixed>}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', mediaType: 'application/json', type: 'array{message: string}')]
    public function user(Request $request): Response
    {
        /**
         * @status 200
         *
         * @body array{
         *     data: array{
         *         id: string,
         *         type: string,
         *         attributes: array<string, string>,
         *         relationships: array<string, string>,
         *     },
         *     included: array{
         *         id: string,
         *         type: string,
         *         attributes: array<string, string>,
         *     }
         * }
         */
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'user'
        );
    }
}
