<?php

namespace App\Http\Requests\Auth;

use App\Models\Attendee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class RegisterAttendeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,dns'],
            'phone' => ['nullable', 'string', 'max:255'],
            'password' => [
                'required',
                'confirmed',
                'max:72',
                Password::min(12)->mixedCase()->numbers()->symbols(),
            ],
        ];
    }

    /**
     * FR-004/FR-005: an active record with a password already set is a genuine duplicate;
     * an active passwordless (guest-checkout) record is claimable, not a violation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasPassword = Attendee::where('email', $this->input('email'))
                ->whereNotNull('password')
                ->exists();

            if ($hasPassword) {
                $validator->errors()->add('email', 'This email address is already registered.');
            }
        });
    }
}
