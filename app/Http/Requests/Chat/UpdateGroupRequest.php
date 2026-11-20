<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama grup tidak boleh kosong.',
            'name.min' => 'Nama grup minimal 2 karakter.',
            'description.max' => 'Deskripsi grup maksimal 255 karakter.',
        ];
    }
}
