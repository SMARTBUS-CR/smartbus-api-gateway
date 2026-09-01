<?php

namespace App\Http\Controllers;

use App\Enums\Services;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\UnauthorizedException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group('Authentication Service', 'This group contains endpoints that interact with the Authentication microservice. <br>These endpoints handle user registration, login, and logout operations by forwarding requests to the Auth microservice.<br><br>For more information about the Auth microservice, see the [SmartBus Authentication](https://smartbus-authentication.onrender.com/) documentation.')]
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
     * @throws ValidationException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_CREATED, description: 'User registered successfully.')]
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
     * @throws ValidationException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'User authenticated successfully.')]
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
     * @authenticate
     *
     * @throws UnauthorizedException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Token is valid.', type: 'array{meta: array{valid: bool, expires_at: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', type: 'array{errors: array{status: string, title: string, detail: string}}')]
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
     * @authenticate
     *
     * @throws UnauthorizedException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Successfully logged out.', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', type: 'array{message: string}')]
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
     * @authenticate
     *
     * @throws UnauthorizedException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Authenticated user retrieved successfully.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Token is invalid or expired.', type: 'array{message: string}')]
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

    /**
     * Send Reset Code
     *
     * Sends a 6-digit password reset code to the user's email.
     * The code is valid for 15 minutes.
     *
     * For more information about the forgot password endpoint, see the
     * [Forgot Password](https://smartbus-authentication.onrender.com/docs/api#tag/password-reset/POST/password/forgot)
     * section in the Auth microservice documentation.
     *
     * @throws ValidationException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Reset code sent successfully.', type: 'array{meta: array{message: string}}')]
    public function sendResetCode(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'password/forgot'
        );
    }

    /**
     * Reset Password
     *
     * Resets the user's password using the provided reset code.
     * The code must match the one sent to the user's email and must not be expired.
     *
     * For more information about the reset password endpoint, see the
     * [Reset Password](https://smartbus-authentication.onrender.com/docs/api#tag/password-reset/POST/password/reset)
     *
     * @throws ValidationException
     */
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Password reset successfully.', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_BAD_REQUEST, description: 'Invalid or expired reset code.', type: 'array{errors: array{status: string, title: string, detail: string}}')]
    public function resetPassword(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'password/reset'
        );
    }
}
