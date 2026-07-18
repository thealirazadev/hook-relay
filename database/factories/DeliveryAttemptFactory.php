<?php

namespace Database\Factories;

use App\Models\Delivery;
use App\Models\DeliveryAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAttempt>
 */
class DeliveryAttemptFactory extends Factory
{
    protected $model = DeliveryAttempt::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_id' => Delivery::factory(),
            'attempt_number' => 1,
            'response_status' => 200,
            'response_headers' => ['content-type' => 'application/json'],
            'response_body_excerpt' => 'ok',
            'error' => null,
            'duration_ms' => 42,
        ];
    }

    public function failed(int $status = 500): static
    {
        return $this->state(fn () => [
            'response_status' => $status,
            'response_body_excerpt' => 'error',
        ]);
    }

    public function connectionError(string $message = 'Connection refused'): static
    {
        return $this->state(fn () => [
            'response_status' => null,
            'response_headers' => null,
            'response_body_excerpt' => null,
            'error' => $message,
        ]);
    }
}
