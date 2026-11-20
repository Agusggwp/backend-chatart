<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;

class InitiateCallRequest extends FormRequest
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
            'receiver_id' => ['required', 'integer', 'exists:users,id', 'different:user_id'],
            'type' => ['nullable', 'string', 'in:audio,video'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'receiver_id.required' => 'Penerima panggilan wajib ditentukan.',
            'receiver_id.exists' => 'Pengguna tidak ditemukan.',
            'receiver_id.different' => 'Tidak dapat melakukan panggilan ke diri sendiri.',
        ];
    }
}
