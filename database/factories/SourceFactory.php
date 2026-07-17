<?php

namespace Database\Factories;

use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Source>
 */
class SourceFactory extends Factory
{
    protected $model = Source::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' source',
            'provider' => 'generic',
            'signing_secret' => 'whsec_'.Str::random(24),
            'active' => true,
        ];
    }

    public function provider(string $provider): static
    {
        return $this->state(fn () => ['provider' => $provider]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => false]);
    }
}
