<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'signing_secret' => trim((string) $this->input('signing_secret')),
            'active' => $this->boolean('active'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'signing_secret' => ['nullable', 'string', 'max:4096'],
            'active' => ['boolean'],
            'destination_ids' => ['array'],
            'destination_ids.*' => ['integer', 'exists:destinations,id'],
        ];
    }
}
