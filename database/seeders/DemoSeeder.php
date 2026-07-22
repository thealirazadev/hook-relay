<?php

namespace Database\Seeders;

use App\Models\Delivery;
use App\Models\DeliveryAttempt;
use App\Models\Destination;
use App\Models\Source;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Obviously synthetic demo data (example.com destinations, dummy signing secrets)
 * used to fill the events, deliveries, delivery-detail, and dead-letter screens for
 * the README screenshots. Safe to run on a throwaway database:
 *
 *   php artisan migrate:fresh --force
 *   php artisan db:seed --class=DemoSeeder --force
 *
 * Deliveries and attempts are written directly with fixed states, so nothing is
 * dispatched to the queue and no outbound HTTP is attempted.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'demo@example.com'],
            ['name' => 'Demo Operator', 'password' => bcrypt('password')],
        );

        $stripe = $this->source('Stripe Payments', 'stripe');
        $github = $this->source('GitHub Webhooks', 'github');
        $shopify = $this->source('Shopify Orders', 'shopify');

        $billing = $this->destination('Billing Service', 'https://billing.internal.example.com/webhooks');
        $analytics = $this->destination('Analytics Pipeline', 'https://analytics.example.com/ingest');
        $slack = $this->destination('Slack Notifier', 'https://hooks.example.com/services/T000DEMO/B000DEMO/relay');

        $stripe->destinations()->sync([$billing->id, $analytics->id]);
        $github->destinations()->sync([$analytics->id, $slack->id]);
        $shopify->destinations()->sync([$billing->id]);

        // Stripe charge succeeded: clean delivery to both routed destinations.
        $e1 = $this->event($stripe, 'charge.succeeded', 'evt_'.Str::lower(Str::random(24)), 12);
        $this->delivered($e1, $billing, 12);
        $this->delivered($e1, $analytics, 12);

        // Stripe invoice payment failed: analytics delivered on a retry, billing
        // still failing and awaiting its next attempt.
        $e2 = $this->event($stripe, 'invoice.payment_failed', 'evt_'.Str::lower(Str::random(24)), 47);
        $this->deliveredAfterRetry($e2, $analytics, 47);
        $this->failing($e2, $billing, 47);

        // Stripe subscription updated: dead-lettered after exhausting all attempts.
        $e3 = $this->event($stripe, 'customer.subscription.updated', 'evt_'.Str::lower(Str::random(24)), 190);
        $this->dead($e3, $billing, 190);
        $this->delivered($e3, $analytics, 190);

        // GitHub push: fan-out to analytics and Slack, both clean.
        $e4 = $this->event($github, 'push', (string) Str::uuid(), 26);
        $this->delivered($e4, $analytics, 26);
        $this->delivered($e4, $slack, 26);

        // GitHub pull_request: Slack delivered, analytics dead-lettered.
        $e5 = $this->event($github, 'pull_request', (string) Str::uuid(), 63);
        $this->delivered($e5, $slack, 63);
        $this->dead($e5, $analytics, 63);

        // GitHub issues: brand-new, still pending its first attempt.
        $e6 = $this->event($github, 'issues', (string) Str::uuid(), 2);
        $this->pending($e6, $slack, 2);
        $this->pending($e6, $analytics, 2);

        // Shopify orders: one clean, one delivered after a couple of retries.
        $e7 = $this->event($shopify, 'orders/create', (string) fake()->numberBetween(820000000000000000, 899999999999999999), 33);
        $this->delivered($e7, $billing, 33);

        $e8 = $this->event($shopify, 'orders/paid', (string) fake()->numberBetween(820000000000000000, 899999999999999999), 8);
        $this->deliveredAfterRetry($e8, $billing, 8);
    }

    protected function source(string $name, string $provider): Source
    {
        return Source::query()->create([
            'name' => $name,
            'provider' => $provider,
            'signing_secret' => 'whsec_demo_'.Str::random(24),
            'active' => true,
        ]);
    }

    protected function destination(string $name, string $url): Destination
    {
        return Destination::query()->create(['name' => $name, 'url' => $url, 'active' => true]);
    }

    protected function event(Source $source, string $type, string $providerId, int $minutesAgo): WebhookEvent
    {
        $at = now()->subMinutes($minutesAgo);

        return WebhookEvent::query()->create([
            'source_id' => $source->id,
            'provider_event_id' => $providerId,
            'dedupe_key' => $providerId,
            'event_type' => $type,
            'headers' => ['content-type' => 'application/json', 'user-agent' => $source->provider.'/1.0'],
            'payload' => json_encode(['id' => $providerId, 'type' => $type], JSON_PRETTY_PRINT),
            'content_type' => 'application/json',
            'received_at' => $at,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $attempts  each: number, status?, error?, body?, ms
     */
    protected function delivery(WebhookEvent $event, Destination $dest, string $status, int $minutesAgo, array $attempts, ?int $nextInMinutes = null): Delivery
    {
        $lastAt = now()->subMinutes($minutesAgo);

        $delivery = Delivery::query()->create([
            'webhook_event_id' => $event->id,
            'destination_id' => $dest->id,
            'status' => $status,
            'attempt_count' => count($attempts),
            'max_attempts' => config('hook_relay.delivery_max_attempts'),
            'next_attempt_at' => $nextInMinutes === null ? null : now()->addMinutes($nextInMinutes),
            'last_attempted_at' => $attempts === [] ? null : $lastAt,
        ]);

        // Backfill the row timestamps so updated_at reflects the last attempt.
        $delivery->forceFill(['created_at' => $event->received_at, 'updated_at' => $lastAt])->saveQuietly();

        $count = count($attempts);
        foreach ($attempts as $i => $a) {
            $attempt = new DeliveryAttempt([
                'attempt_number' => $a['number'],
                'response_status' => $a['status'] ?? null,
                'response_headers' => isset($a['status']) ? ['content-type' => 'application/json', 'server' => 'nginx'] : null,
                'response_body_excerpt' => $a['body'] ?? null,
                'error' => $a['error'] ?? null,
                'duration_ms' => $a['ms'],
            ]);
            $attempt->delivery_id = $delivery->id;
            // Older attempts first, spaced with exponential-ish gaps before the last.
            $attempt->created_at = (clone $lastAt)->subMinutes(($count - 1 - $i) * 3);
            $attempt->save();
        }

        return $delivery;
    }

    protected function delivered(WebhookEvent $event, Destination $dest, int $minutesAgo): Delivery
    {
        return $this->delivery($event, $dest, Delivery::STATUS_DELIVERED, $minutesAgo, [
            ['number' => 1, 'status' => 200, 'body' => '{"received":true}', 'ms' => fake()->numberBetween(80, 260)],
        ]);
    }

    protected function deliveredAfterRetry(WebhookEvent $event, Destination $dest, int $minutesAgo): Delivery
    {
        return $this->delivery($event, $dest, Delivery::STATUS_DELIVERED, $minutesAgo, [
            ['number' => 1, 'status' => 503, 'body' => 'Service Unavailable', 'ms' => 412],
            ['number' => 2, 'status' => 200, 'body' => '{"received":true}', 'ms' => 173],
        ]);
    }

    protected function failing(WebhookEvent $event, Destination $dest, int $minutesAgo): Delivery
    {
        return $this->delivery($event, $dest, Delivery::STATUS_FAILED, $minutesAgo, [
            ['number' => 1, 'error' => 'cURL error 7: Failed to connect to billing.internal.example.com port 443: Connection refused', 'ms' => 5031],
            ['number' => 2, 'status' => 502, 'body' => 'Bad Gateway', 'ms' => 638],
        ], nextInMinutes: 4);
    }

    protected function pending(WebhookEvent $event, Destination $dest, int $minutesAgo): Delivery
    {
        return $this->delivery($event, $dest, Delivery::STATUS_PENDING, $minutesAgo, []);
    }

    protected function dead(WebhookEvent $event, Destination $dest, int $minutesAgo): Delivery
    {
        return $this->delivery($event, $dest, Delivery::STATUS_DEAD, $minutesAgo, [
            ['number' => 1, 'status' => 500, 'body' => 'Internal Server Error', 'ms' => 240],
            ['number' => 2, 'status' => 502, 'body' => 'Bad Gateway', 'ms' => 311],
            ['number' => 3, 'error' => 'cURL error 28: Operation timed out after 10000 milliseconds', 'ms' => 10000],
            ['number' => 4, 'status' => 503, 'body' => 'Service Unavailable', 'ms' => 402],
            ['number' => 5, 'error' => 'cURL error 7: Failed to connect: Connection refused', 'ms' => 5008],
            ['number' => 6, 'status' => 500, 'body' => 'Internal Server Error', 'ms' => 268],
            ['number' => 7, 'status' => 502, 'body' => 'Bad Gateway', 'ms' => 355],
            ['number' => 8, 'status' => 500, 'body' => 'Internal Server Error', 'ms' => 291],
        ]);
    }
}
