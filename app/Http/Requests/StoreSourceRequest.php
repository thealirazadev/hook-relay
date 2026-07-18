<?php

namespace App\Http\Requests;

use App\Models\Source;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSourceRequest extends FormRequest
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
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', Rule::in(Source::PROVIDERS)],
            'signing_secret' => ['required', 'string', 'max:4096'],
        ];
    }
}
