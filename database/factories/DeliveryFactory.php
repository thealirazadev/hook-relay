<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\Destination;
use App\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Delivery>
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_event_id' => WebhookEvent::factory(),
            'destination_id' => Destination::factory(),
            'status' => Delivery::STATUS_PENDING,
            'attempt_count' => 0,
            'max_attempts' => config('hook_relay.delivery_max_attempts'),
            'next_attempt_at' => null,
            'last_attempted_at' => null,
        ];
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function dead(): static
    {
        return $this->state(fn () => [
            'status' => Delivery::STATUS_DEAD,
            'attempt_count' => config('hook_relay.delivery_max_attempts'),
        ]);
    }

    public function delivered(): static
    {
        return $this->state(fn () => ['status' => Delivery::STATUS_DELIVERED, 'attempt_count' => 1]);
    }
}
