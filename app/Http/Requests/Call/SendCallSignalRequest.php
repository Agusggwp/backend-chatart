<?php

namespace App\Http\Requests\Call;

use Illuminate\Foundation\Http\FormRequest;

class SendCallSignalRequest extends FormRequest
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
            'signal_type' => ['required', 'string', 'in:offer,answer,ice-candidate'],
            'signal_data' => ['required'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'signal_type.required' => 'Tipe sinyal WebRTC wajib diisi.',
            'signal_type.in' => 'Tipe sinyal harus offer, answer, atau ice-candidate.',
            'signal_data.required' => 'Data sinyal WebRTC wajib dikirim.',
        ];
    }
}
