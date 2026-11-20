<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class DeleteMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delete_type' => ['required', 'string', 'in:for_everyone,for_me'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'delete_type.required' => 'Tipe penghapusan harus dipilih.',
            'delete_type.in' => 'Tipe penghapusan hanya boleh for_everyone atau for_me.',
        ];
    }
}
