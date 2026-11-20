<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->body)) {
            $this->merge([
                'body' => trim($this->body),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without:attachment'],
            'attachment' => ['nullable', 'file', 'mimes:jpeg,png,jpg,webp,gif,m4a,mp3,wav,aac,ogg,opus,webm,mp4,bin,pdf,doc,docx,xls,xlsx,ppt,pptx,zip,rar,txt,csv', 'max:51200'],
            'file_name' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'integer', 'min:1'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required_without' => 'Pesan atau lampiran harus diisi.',
            'body.max' => 'Pesan maksimal 5000 karakter.',
            'attachment.max' => 'Ukuran lampiran maksimal 50 MB.',
            'attachment.uploaded' => 'File lampiran gagal diunggah. Pastikan ukuran file tidak melebihi 50 MB.',
            'reply_to_id.exists' => 'Pesan yang dibalas tidak ditemukan.',
        ];
    }
}
