<?php

namespace App\Actions\Auth;

use App\Models\Attendee;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegisterAttendeeAction
{
    /**
     * Create-or-claim per data-model.md §attendees (FR-004/FR-005): update an existing
     * passwordless (guest-checkout) row in place — preserving its id and orders — or
     * insert a new row. Either way, dispatch the queued verification notification (FR-006).
     *
     * @param  array{name: string, email: string, phone: ?string, password: string}  $data
     */
    public function handle(array $data): Attendee
    {
        $attendee = DB::transaction(function () use ($data) {
            $existing = Attendee::where('email', $data['email'])->whereNull('password')->first();

            if ($existing) {
                $existing->update([
                    'name' => $data['name'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                ]);

                return $existing;
            }

            try {
                return Attendee::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'] ?? null,
                    'password' => $data['password'],
                ]);
            } catch (QueryException $exception) {
                // Race: another request claimed this email between the check above and
                // this insert. The email_active unique index (feature 001) is the real
                // safety net here — surface it as a normal validation error, not a 500.
                if (str_contains($exception->getMessage(), 'attendees_email_active_unique')) {
                    throw ValidationException::withMessages([
                        'email' => 'This email address is already registered.',
                    ]);
                }

                throw $exception;
            }
        });

        $attendee->sendEmailVerificationNotification();

        return $attendee;
    }
}
