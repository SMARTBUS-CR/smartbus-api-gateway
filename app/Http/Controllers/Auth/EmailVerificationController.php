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
class EmailVerificationController extends Controller
{
    /**
     * Verify Email
     *
     * Verifies the user's email address using the 6-digit OTP code sent to their email.
     * On success, the user's email is marked as verified and an access token is returned.
     *
     * @unauthenticated
     *
     * @throws ValidationException
     */
    #[BodyParameter('email', type: 'string', format: 'email', required: true, description: 'The email address of the user to verify.')]
    #[BodyParameter('code', type: 'string', required: true, description: 'The 6-digit OTP code sent to the user\'s email for verification.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Email verified successfully.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_BAD_REQUEST, description: 'Invalid, expired, or too many attempts.', type: 'array{errors: array{status: string, title: string, detail: string}}')]
    public function verify(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'email/verify'
        );
    }

    /**
     * Resend Verification Code
     *
     * Resends the 6-digit email verification code to the given email address.
     * For security, always returns 200 even if the email is not found or already verified.
     *
     * @unauthenticated
     *
     * @throws ValidationException
     */
    #[BodyParameter('email', type: 'string', format: 'email', required: true, description: 'The email address to which the verification code should be resent.')]
    #[ResponseAttribute(status: HttpStatus::HTTP_OK, description: 'Verification code sent (or silently skipped).', type: 'array{meta: array{message: string}}')]
    public function resend(Request $request): Response
    {
        return $this->proxyTo(
            $request,
            Services::AUTH->value,
            'email/resend'
        );
    }
}
