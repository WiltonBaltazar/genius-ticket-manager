<?php

namespace App\Actions\Auth;

use App\Exceptions\Auth\UnverifiedEmailLoginException;
use App\Models\Attendee;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateAttendeeAction
{
    /**
     * @throws ValidationException on incorrect/nonexistent credentials (generic, FR-010)
     * @throws UnverifiedEmailLoginException when credentials are correct but unverified (FR-009)
     */
    public function handle(Request $request, string $email, string $password): Attendee
    {
        $attendee = Attendee::where('email', $email)->first();

        // A null password means either no account or an unclaimed guest-checkout row (no
        // credentials ever set) — both are indistinguishable "invalid credentials" cases.
        if (! $attendee || ! $attendee->password || ! Hash::check($password, $attendee->password)) {
            if ($attendee) {
                $this->logFailure($request, $attendee, 'invalid_credentials');
            }

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $attendee->hasVerifiedEmail()) {
            $this->logFailure($request, $attendee, 'unverified_email');

            throw new UnverifiedEmailLoginException;
        }

        $request->session()->regenerate();

        Auth::guard('web')->login($attendee);

        AuditLog::create([
            'staff_id' => null,
            'action' => 'attendee_login',
            'auditable_type' => Attendee::class,
            'auditable_id' => $attendee->id,
            'ip_address' => $request->ip(),
            'changes' => null,
        ]);

        return $attendee;
    }

    private function logFailure(Request $request, Attendee $attendee, string $reason): void
    {
        AuditLog::create([
            'staff_id' => null,
            'action' => 'attendee_login_failed',
            'auditable_type' => Attendee::class,
            'auditable_id' => $attendee->id,
            'ip_address' => $request->ip(),
            'changes' => ['reason' => $reason],
        ]);
    }
}
