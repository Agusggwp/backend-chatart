<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reported_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
            'message_id' => ['nullable', 'integer', 'exists:messages,id'],
            'reason' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan laporan harus diisi.',
        ];
    }
}
