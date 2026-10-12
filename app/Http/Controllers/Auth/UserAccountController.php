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
}
