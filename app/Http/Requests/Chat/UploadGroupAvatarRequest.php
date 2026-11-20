<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class UploadGroupAvatarRequest extends FormRequest
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
            'avatar' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'File foto grup wajib diunggah.',
            'avatar.image' => 'File harus berupa gambar yang valid.',
            'avatar.mimes' => 'Format foto harus JPG, JPEG, PNG, atau WEBP.',
            'avatar.max' => 'Ukuran foto maksimal 2 MB.',
        ];
    }
}
