<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->has('name') && is_string($this->name)) {
            $data['name'] = trim($this->name);
        }

        if ($this->has('username') && is_string($this->username)) {
            $data['username'] = strtolower(trim($this->username));
        }

        if ($this->has('bio')) {
            $data['bio'] = is_string($this->bio) && trim($this->bio) !== '' ? trim($this->bio) : null;
        }

        if ($this->has('phone')) {
            $data['phone'] = is_string($this->phone) && trim($this->phone) !== '' ? trim($this->phone) : null;
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
        $userId = $this->user()?->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[a-z0-9._]+$/',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:25', 'regex:/^\+?[0-9\s\-]{8,20}$/'],
            'email' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap tidak boleh kosong.',
            'username.required' => 'Username tidak boleh kosong.',
            'username.regex' => 'Username hanya boleh berisi huruf kecil, angka, titik, dan garis bawah.',
            'username.unique' => 'Username sudah digunakan pengguna lain.',
            'bio.max' => 'Bio maksimal 255 karakter.',
            'phone.regex' => 'Format nomor telepon tidak valid.',
            'email.prohibited' => 'Email tidak dapat diubah tanpa verifikasi.',
        ];
    }
}
