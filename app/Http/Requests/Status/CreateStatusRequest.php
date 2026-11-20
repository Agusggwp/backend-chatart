<?php

namespace App\Http\Requests\Status;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class CreateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'string', 'in:text,image'],
            'content' => ['nullable', 'string', 'max:1000'],
            'media' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,gif', 'max:10240'],
            'background_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'font_style' => ['nullable', 'string', 'in:default,serif,sans,cursive'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Tipe status hanya boleh text atau image.',
            'media.mimes' => 'Format file gambar harus jpeg, png, jpg, webp, atau gif.',
            'media.max' => 'Ukuran gambar maksimal adalah 10 MB.',
            'background_color.regex' => 'Format kode warna latar belakang tidak valid.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validasi pembuatan status gagal.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }
}
