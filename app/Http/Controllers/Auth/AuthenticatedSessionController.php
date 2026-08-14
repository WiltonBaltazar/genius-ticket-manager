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
    /**
     * No client-accessible "current session" endpoint existed before checkout
     * needed one (feature 002 only ever returned attendee info from the login
     * response itself) — added here since it belongs with the other session
     * concerns, not because checkout owns it.
     */
    public function show(Request $request): JsonResponse
    {
        $attendee = $request->user('web');

        return response()->json([
            'attendee' => $attendee ? [
                'id' => $attendee->id,
                'name' => $attendee->name,
                'email' => $attendee->email,
            ] : null,
        ]);
    }

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
