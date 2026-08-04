<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetAttendeePasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    /**
     * POST /forgot-password (FR-016, FR-017): always 200, never discloses whether the
     * email has an account.
     */
    public function store(ForgotPasswordRequest $request): JsonResponse
    {
        Password::broker('attendees')->sendResetLink($request->validated());

        return response()->json([
            'message' => 'If that email is registered, a reset link has been sent.',
        ]);
    }

    /**
     * POST /reset-password (FR-018 through FR-020).
     */
    public function update(ResetPasswordRequest $request, ResetAttendeePasswordAction $action): JsonResponse
    {
        $status = $action->handle($request->validated());

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'token' => ['This password reset token is invalid or has expired.'],
            ]);
        }

        return response()->json([
            'message' => 'Password reset successfully. Please log in again.',
        ]);
    }
}
