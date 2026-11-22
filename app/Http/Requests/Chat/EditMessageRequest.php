<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class EditMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Pesan tidak boleh kosong.',
            'body.max' => 'Pesan maksimal 5000 karakter.',
        ];
    }
}
