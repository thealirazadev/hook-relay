<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkReplayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_ids' => ['required', 'array', 'max:100'],
            'event_ids.*' => ['string', 'distinct', 'exists:webhook_events,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'event_ids.required' => 'Select at least one event to replay.',
            'event_ids.max' => 'You can replay at most 100 events at once.',
        ];
    }
}
