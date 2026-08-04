<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateAttendeeAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request, AuthenticateAttendeeAction $action): JsonResponse
    {
        $validated = $request->validated();

        $attendee = $action->handle($request, $validated['email'], $validated['password']);

        return response()->json([
            'attendee' => [
                'id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
            ],
        ]);
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }
}
