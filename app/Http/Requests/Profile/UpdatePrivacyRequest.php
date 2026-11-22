<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrivacyRequest extends FormRequest
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
            'privacy_last_seen' => ['nullable', 'string', 'in:everyone,contacts,nobody'],
            'privacy_status' => ['nullable', 'string', 'in:everyone,contacts,nobody'],
            'privacy_read_receipts' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'privacy_last_seen.in' => 'Pengaturan terakhir dilihat harus bernilai everyone, contacts, atau nobody.',
            'privacy_status.in' => 'Pengaturan status harus bernilai everyone, contacts, atau nobody.',
        ];
    }
}
