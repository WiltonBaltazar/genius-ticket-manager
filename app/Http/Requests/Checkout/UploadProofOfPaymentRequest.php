<?php

namespace App\Http\Requests\Checkout;

use Illuminate\Foundation\Http\FormRequest;

class UploadProofOfPaymentRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:40960'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Selecione um ficheiro para enviar.',
            'file.mimes' => 'O ficheiro deve ser uma imagem (JPG, PNG) ou um PDF.',
            'file.max' => 'O ficheiro não pode exceder 40 MB.',
        ];
    }
}
