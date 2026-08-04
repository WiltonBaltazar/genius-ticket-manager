<?php

namespace App\Actions\Auth;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ResetAttendeePasswordAction
{
    /**
     * @param  array{email: string, token: string, password: string, password_confirmation: string}  $credentials
     * @return string one of the Password broker's status constants (e.g. PasswordBroker::PASSWORD_RESET)
     */
    public function handle(array $credentials): string
    {
        return Password::broker('attendees')->reset(
            $credentials,
            function ($attendee, string $password): void {
                $attendee->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                // FR-020: every existing authenticated session for this attendee dies with
                // the reset — including sessions on other devices, not just the requester's.
                DB::table('sessions')->where('user_id', $attendee->id)->delete();
            }
        );
    }
}
