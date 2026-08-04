<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Attendee;
use App\Notifications\Auth\VerifyAttendeeEmail;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmailVerificationController extends Controller
{
    /**
     * Signed-link handler (research.md §7). Deliberately does NOT use Laravel's stock
     * EmailVerificationRequest — that class resolves the target user via the *currently
     * authenticated* session (Breeze/Jetstream's auto-login-then-verify pattern), but FR-008
     * prevents login before verification, so there is no session yet when this link is
     * visited. The target Attendee is instead resolved directly from the signed {id}
     * route parameter; the `signed` middleware (registered alongside this route) already
     * rejects a tampered/expired (>24h) URL with an InvalidSignatureException before this
     * method ever runs — see bootstrap/app.php for the redirect handling of that exception.
     */
    public function verify(Request $request, string $id, string $hash): RedirectResponse
    {
        $attendee = Attendee::findOrFail($id);

        if (! hash_equals(sha1($attendee->getEmailForVerification()), $hash)) {
            return redirect(url('/auth/login').'?verification=failed');
        }

        if (! $attendee->hasVerifiedEmail()) {
            $attendee->markEmailAsVerified();

            event(new Verified($attendee));
        }

        return redirect(url('/auth/login').'?verified=1');
    }

    /**
     * POST /email/verification-notification (FR-007): identical response whether the
     * email is unverified, already verified, or matches no account — no disclosure either way.
     */
    public function resend(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'email' => ['required', 'email'],
        ])->validate();

        $attendee = Attendee::where('email', $validated['email'])->first();

        if ($attendee && ! $attendee->hasVerifiedEmail()) {
            $attendee->notify(new VerifyAttendeeEmail);
        }

        return response()->json([
            'message' => 'If that account needs verification, a new link has been sent.',
        ]);
    }
}
