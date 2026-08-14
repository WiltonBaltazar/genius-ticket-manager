<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitOrderRequest extends FormRequest
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
        $authenticated = $this->user('web') !== null;

        return [
            'transaction_hash' => ['required', 'string', 'max:255'],
            'event_id' => ['required', 'uuid', Rule::exists('events', 'id')->whereNull('deleted_at')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.ticket_type_id' => [
                'required',
                'uuid',
                Rule::exists('ticket_types', 'id')
                    ->where('event_id', $this->input('event_id'))
                    ->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'name' => [Rule::requiredIf(! $authenticated), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf(! $authenticated), 'nullable', 'email:rfc'],
            'phone' => [Rule::requiredIf(! $authenticated), 'nullable', 'string', 'max:30'],
        ];
    }
}
