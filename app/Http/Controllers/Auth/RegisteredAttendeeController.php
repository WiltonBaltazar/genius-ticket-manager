<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterAttendeeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterAttendeeRequest;
use Illuminate\Http\JsonResponse;

class RegisteredAttendeeController extends Controller
{
    public function store(RegisterAttendeeRequest $request, RegisterAttendeeAction $action): JsonResponse
    {
        $action->handle($request->validated());

        return response()->json([
            'message' => 'Registration successful. Check your email to verify your account.',
        ], 201);
    }
}
