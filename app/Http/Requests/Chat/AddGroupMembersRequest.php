<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class AddGroupMembersRequest extends FormRequest
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
            'member_ids' => ['required', 'array', 'min:1', 'max:50'],
            'member_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'member_ids.required' => 'Pilih minimal 1 pengguna untuk ditambahkan.',
            'member_ids.*.exists' => 'Pengguna tidak ditemukan.',
        ];
    }
}
