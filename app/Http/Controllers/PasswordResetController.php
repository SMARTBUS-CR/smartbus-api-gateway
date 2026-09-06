<?php

namespace App\Http\Controllers;

use App\Enums\Services;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(name: 'Auth Service - Password Reset', description: 'Endpoints for password reset functionality in the Authentication microservice.', weight: 2)]
class PasswordResetController extends Controller
{
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
    #[BodyParameter('email', type: 'string', format: 'email', required: true)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Reset code sent successfully.', mediaType: 'application/json', type: 'array{meta: array{message: string}}')]
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
    #[BodyParameter('email', type: 'string', format: 'email', required: true)]
    #[BodyParameter('code', description: 'Six-digit reset code.', type: 'string', required: true)]
    #[BodyParameter('password', type: 'string', format: 'password', required: true)]
    #[BodyParameter('password_confirmation', type: 'string', format: 'password', required: true)]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Password reset successfully.', mediaType: 'application/json', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_BAD_REQUEST, description: 'Invalid or expired reset code.', mediaType: 'application/json', type: 'array{errors: array{status: string, title: string, detail: string}}')]
    public function resetPassword(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'password/reset'
        );
    }
}
