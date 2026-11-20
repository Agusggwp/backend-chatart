<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartConversationRequest extends FormRequest
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
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$this->user()?->id]),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'ID pengguna tujuan wajib diisi.',
            'user_id.exists' => 'Pengguna tujuan tidak ditemukan.',
            'user_id.not_in' => 'Anda tidak dapat memulai percakapan dengan diri sendiri.',
        ];
    }
}
