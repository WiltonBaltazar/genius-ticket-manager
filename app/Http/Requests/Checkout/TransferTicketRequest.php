<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class TransferTicketRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            // Required, same as guest checkout (SubmitOrderRequest) — the new holder
            // gets no account either, so this is the same "guest" shape both times.
            'phone' => ['required', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Indique o nome de quem vai receber o bilhete.',
            'email.required' => 'Indique o e-mail de quem vai receber o bilhete.',
            'email.email' => 'Indique um e-mail válido.',
            'phone.required' => 'Indique o número de telefone de quem vai receber o bilhete.',
        ];
    }
}
