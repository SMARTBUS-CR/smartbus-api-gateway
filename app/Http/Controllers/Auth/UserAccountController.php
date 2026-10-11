<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Services;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\BodyParameter;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\Response as ResponseAttribute;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

#[Group(Services::AUTH_LABEL)]
class UserAccountController extends Controller
{
    /**
     * Update Passenger Profile
     *
     * Updates the authenticated passenger's profile information.
     * The user can update their name and email address.
     * If the email is changed, the email verification status will be reset.
     *
     * @throws ValidationException
     */
    #[BodyParameter('name', type: 'string', description: 'The new name of the passenger.')]
    #[BodyParameter('email', type: 'string', format: 'email', description: 'The new email address of the passenger.')]
    #[BodyParameter('current_password', type: 'string', format: 'password', description: 'The current password of the passenger.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Passenger profile updated successfully.')]
    public function update(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            'auth',
            'user'
        );
    }

    /**
     * Update Passenger Password
     *
     * Updates the authenticated passenger's password.
     * The user must provide their current password to change it.
     * All other active tokens will be revoked upon successful password change.
     *
     * @throws ValidationException
     */
    #[BodyParameter('current_password', required: true, type: 'string', format: 'password', description: 'The current password of the passenger.')]
    #[BodyParameter('password', required: true, type: 'string', format: 'password', description: 'The new password of the passenger.')]
    #[BodyParameter('password_confirmation', required: true, type: 'string', format: 'password', description: 'The confirmation of the new password of the passenger.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Passenger password updated successfully.', type: 'array{meta: array{message: string}}')]
    public function updatePassword(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            'auth',
            'user/password'
        );
    }

    /**
     * Delete Passenger Account
     *
     * Forwards the authenticated passenger's account deletion request.
     * Authentication enforces exclusive passenger access, email verification,
     * current password validation, and explicit deletion confirmation.
     *
     * Successful deletion revokes all account access tokens.
     * Personal data and historical references are retained through soft deletion.
     */
    #[BodyParameter('current_password', required: true, type: 'string', format: 'password', description: 'The authenticated passenger’s current password.')]
    #[BodyParameter('confirm_deletion', required: true, type: 'boolean', description: 'Explicit confirmation of account deletion. Send true.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Passenger account soft-deleted and all access tokens revoked.', type: 'array{meta: array{message: string}}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNAUTHORIZED, description: 'Missing, invalid, expired, or revoked access token.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_FORBIDDEN, description: 'Email verification required or the account is not exclusively a passenger.', type: 'array{errors: list<array{status: string, title: string, detail: string}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_UNPROCESSABLE_ENTITY, description: 'Invalid current password or deletion confirmation.', type: 'array{errors: list<array{status: int, title: string, detail: string, source: array{pointer: string}}>}')]
    #[ResponseAttribute(status: HttpStatus::HTTP_TOO_MANY_REQUESTS, description: 'Gateway or Authentication request limit exceeded.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_INTERNAL_SERVER_ERROR, description: 'Unexpected failure returned by the service.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_SERVICE_UNAVAILABLE, description: 'Authentication service is unavailable.', type: 'array{errors: list<array{status: string, code?: string, title: string, detail: string}>}')]
    public function destroy(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            'auth',
            'user',
        );
    }
}
