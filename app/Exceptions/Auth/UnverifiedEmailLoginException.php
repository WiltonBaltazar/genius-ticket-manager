<?php

namespace App\Exceptions\Auth;

use Exception;
use Illuminate\Http\JsonResponse;

class UnverifiedEmailLoginException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Please verify your email address before logging in.',
            'resend_available' => true,
        ], 423);
    }
}
