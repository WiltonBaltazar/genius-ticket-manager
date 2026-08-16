<?php

namespace App\Http\Requests\Checkout;

use App\Models\Event;
use Illuminate\Contracts\Validation\Validator;
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
            // Format only here — whether a date is even allowed (event spans >1 day)
            // and whether it falls in range needs the event's own dates, so that
            // check lives in withValidator() below instead.
            'items.*.event_date' => ['nullable', 'date_format:Y-m-d'],
            'name' => [Rule::requiredIf(! $authenticated), 'nullable', 'string', 'max:255'],
            'email' => [Rule::requiredIf(! $authenticated), 'nullable', 'email:rfc'],
            'phone' => [Rule::requiredIf(! $authenticated), 'nullable', 'string', 'max:30'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $event = Event::find($this->input('event_id'));

            // event_id's own exists rule already fails the request in this case —
            // nothing more to check here.
            if (! $event) {
                return;
            }

            $daysCount = $event->daysCount();
            $firstDay = $event->start_date->toDateString();
            $lastDay = $event->end_date->toDateString();

            foreach ($this->input('items', []) as $index => $item) {
                $eventDate = $item['event_date'] ?? null;
                if ($eventDate === null) {
                    continue;
                }

                if ($daysCount <= 1) {
                    $validator->errors()->add(
                        "items.{$index}.event_date",
                        'Este evento não tem múltiplos dias.'
                    );
                } elseif ($eventDate < $firstDay || $eventDate > $lastDay) {
                    $validator->errors()->add(
                        "items.{$index}.event_date",
                        'Data fora do período do evento.'
                    );
                }
            }
        });
    }

    /**
     * The attendee-facing checkout form is in Portuguese (this app's public
     * site is Mozambique-only), so its validation errors need to match —
     * scoped to this request rather than the app's global locale, since the
     * staff admin panel stays in English.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório.',
            'email.required' => 'O email é obrigatório.',
            'email.email' => 'Introduza um endereço de email válido.',
            'phone.required' => 'O número de telefone é obrigatório.',
            'items.required' => 'Adicione pelo menos um bilhete ao carrinho.',
            'items.min' => 'Adicione pelo menos um bilhete ao carrinho.',
            'items.*.event_date.date_format' => 'Data inválida.',
        ];
    }
}
