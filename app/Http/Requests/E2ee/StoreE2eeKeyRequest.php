<?php

namespace App\Http\Requests\E2ee;

use Illuminate\Foundation\Http\FormRequest;

class StoreE2eeKeyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identity_key' => ['required', 'string'],
            'signed_prekey' => ['required', 'string'],
            'prekey_signature' => ['required', 'string'],
            'one_time_prekeys' => ['nullable', 'array'],
            'one_time_prekeys.*' => ['string'],
        ];
    }
}
