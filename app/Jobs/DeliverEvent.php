<?php

namespace App\Jobs;

use App\Models\Delivery;
use App\Support\Backoff;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DeliverEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** First 2048 bytes of a response body are stored. */
    private const EXCERPT_BYTES = 2048;

    public int $tries;

    public function __construct(public Delivery $delivery)
    {
        $this->tries = $delivery->max_attempts;
    }

    public function handle(): void
    {
        $delivery = $this->delivery->fresh();

        if ($delivery === null || $delivery->isTerminal()) {
            return;
        }

        $delivery->forceFill([
            'status' => Delivery::STATUS_DELIVERING,
            'last_attempted_at' => now(),
            'attempt_count' => $delivery->attempt_count + 1,
        ])->save();

        $result = $this->send($delivery);

        $delivery->attempts()->create([
            'attempt_number' => $delivery->attempt_count,
            'response_status' => $result['status'],
            'response_headers' => $result['headers'],
            'response_body_excerpt' => $result['body'],
            'error' => $result['error'],
            'duration_ms' => $result['duration_ms'],
        ]);

        Log::info('delivery.attempted', [
            'delivery_id' => $delivery->id,
            'attempt' => $delivery->attempt_count,
            'status' => $result['status'],
        ]);

        if ($result['delivered']) {
            $delivery->forceFill(['status' => Delivery::STATUS_DELIVERED, 'next_attempt_at' => null])->save();
            Log::info('delivery.delivered', ['delivery_id' => $delivery->id]);

            return;
        }

        if ($this->attempts() >= $delivery->max_attempts) {
            $delivery->forceFill(['status' => Delivery::STATUS_DEAD, 'next_attempt_at' => null])->save();
            Log::warning('delivery.dead', ['delivery_id' => $delivery->id, 'attempts' => $delivery->attempt_count]);

            return;
        }

        $delay = app(Backoff::class)->delay($this->attempts());
        $delivery->forceFill([
            'status' => Delivery::STATUS_FAILED,
            'next_attempt_at' => now()->addSeconds($delay),
        ])->save();

        $this->release($delay);
    }

    /**
     * Perform one outbound attempt. Never throws for HTTP-level failures; a
     * connection error or timeout is captured and returned so the attempt is
     * always recorded before the job retries.
     *
     * @return array{delivered: bool, status: int|null, headers: array<string, string>|null, body: string|null, error: string|null, duration_ms: int}
     */
    private function send(Delivery $delivery): array
    {
        $event = $delivery->event;
        $destination = $delivery->destination()->withTrashed()->first();
        $sourceName = $event->source()->withTrashed()->first()?->name ?? '';
        $contentType = $event->content_type ?: 'application/json';

        $start = hrtime(true);

        try {
            $response = Http::withHeaders([
                'X-Relay-Event-Id' => $event->id,
                'X-Relay-Delivery-Id' => $delivery->id,
                'X-Relay-Source' => $sourceName,
                'User-Agent' => 'hook-relay/1.0',
            ])
                ->withBody($event->payload, $contentType)
                ->withOptions(['allow_redirects' => false])
                ->timeout(config('hook_relay.delivery_timeout_seconds'))
                ->post($destination->url);

            return [
                'delivered' => $response->successful(),
                'status' => $response->status(),
                'headers' => $this->flattenHeaders($response->headers()),
                'body' => $this->excerpt($response->body()),
                'error' => null,
                'duration_ms' => $this->elapsedMs($start),
            ];
        } catch (ConnectionException $e) {
            return [
                'delivered' => false,
                'status' => null,
                'headers' => null,
                'body' => null,
                'error' => Str::limit($e->getMessage(), 240),
                'duration_ms' => $this->elapsedMs($start),
            ];
        }
    }

    public function failed(?Throwable $e): void
    {
        $delivery = $this->delivery->fresh();

        if ($delivery !== null && ! $delivery->isTerminal()) {
            $delivery->forceFill(['status' => Delivery::STATUS_DEAD, 'next_attempt_at' => null])->save();
            Log::warning('delivery.dead', ['delivery_id' => $delivery->id, 'reason' => 'job_failed']);
        }
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((hrtime(true) - $start) / 1e6);
    }

    /**
     * @param  array<string, array<int, string>>  $headers
     * @return array<string, string>
     */
    private function flattenHeaders(array $headers): array
    {
        return collect($headers)
            ->mapWithKeys(fn (array $values, string $name) => [strtolower($name) => implode(', ', $values)])
            ->all();
    }

    private function excerpt(string $body): string
    {
        return mb_strcut($body, 0, self::EXCERPT_BYTES, 'UTF-8');
    }
}
