<?php

namespace Database\Factories;

use App\Models\Source;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = json_encode(['id' => 'evt_'.Str::random(10), 'type' => 'demo.event']);

        return [
            'source_id' => Source::factory(),
            'provider_event_id' => 'evt_'.Str::random(10),
            'dedupe_key' => 'evt_'.Str::random(10),
            'event_type' => 'demo.event',
            'headers' => ['content-type' => 'application/json'],
            'payload' => $payload,
            'content_type' => 'application/json',
            'received_at' => now(),
        ];
    }
}
