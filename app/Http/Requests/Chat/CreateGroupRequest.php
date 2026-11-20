<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if (is_string($this->name)) {
            $data['name'] = trim($this->name);
        }
        if ($this->has('description')) {
            $data['description'] = is_string($this->description) && trim($this->description) !== ''
                ? trim($this->description)
                : null;
        }
        if ($data !== []) {
            $this->merge($data);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'member_ids' => ['required', 'array', 'min:1', 'max:100'],
            'member_ids.*' => [
                'required',
                'integer',
                'distinct',
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
            'name.required' => 'Nama grup wajib diisi.',
            'name.min' => 'Nama grup minimal 2 karakter.',
            'member_ids.required' => 'Pilih minimal 1 anggota grup.',
            'member_ids.min' => 'Pilih minimal 1 anggota grup.',
            'member_ids.*.exists' => 'Salah satu pengguna tidak ditemukan.',
            'member_ids.*.not_in' => 'Pembuat grup otomatis menjadi admin dan tidak perlu dimasukkan di member_ids.',
        ];
    }
}
