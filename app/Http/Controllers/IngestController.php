<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Support\HeaderFilter;
use App\Support\Providers\ProviderResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class IngestController extends Controller
{
    public function __construct(
        private readonly ProviderResolver $providers,
        private readonly HeaderFilter $headerFilter,
    ) {}

    public function __invoke(Request $request, string $ingestKey): JsonResponse
    {
        $source = Source::where('ingest_key', $ingestKey)->where('active', true)->first();

        if ($source === null) {
            Log::warning('ingest.unknown_source', ['ingest_key' => $ingestKey]);

            return $this->error('unknown_source', 'No active source matches this ingest key.', 404);
        }

        $body = $request->getContent();

        if (strlen($body) > config('hook_relay.max_body_kb') * 1024) {
            Log::warning('ingest.payload_too_large', ['source_id' => $source->id, 'bytes' => strlen($body)]);

            return $this->error('payload_too_large', 'The request body exceeds the size limit.', 413);
        }

        $provider = $this->providers->for($source->provider);

        if (! $provider->verify($request, $source->signing_secret)) {
            Log::warning('ingest.signature_failed', ['source_id' => $source->id, 'reason' => 'hmac_mismatch']);

            return $this->error('invalid_signature', 'The request signature could not be verified.', 401);
        }

        $providerEventId = $provider->eventId($request);
        $dedupeKey = $providerEventId ?? 'sha256:'.hash('sha256', $body);

        if ($existing = $source->events()->where('dedupe_key', $dedupeKey)->first()) {
            Log::info('ingest.duplicate', ['source_id' => $source->id, 'event_id' => $existing->id]);

            return $this->accepted($existing->id, true);
        }

        try {
            $event = $source->events()->create([
                'provider_event_id' => $providerEventId,
                'dedupe_key' => $dedupeKey,
                'event_type' => $provider->eventType($request),
                'headers' => $this->headerFilter->filter($request->headers->all()),
                'payload' => $body,
                'content_type' => $request->header('Content-Type', 'application/json'),
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            // A concurrent identical POST won the unique (source_id, dedupe_key) race.
            if ($existing = $source->events()->where('dedupe_key', $dedupeKey)->first()) {
                Log::info('ingest.duplicate', ['source_id' => $source->id, 'event_id' => $existing->id]);

                return $this->accepted($existing->id, true);
            }

            throw $e;
        }

        $event->createDeliveries();

        Log::info('ingest.accepted', [
            'source_id' => $source->id,
            'event_id' => $event->id,
            'event_type' => $event->event_type,
        ]);

        return $this->accepted($event->id, false);
    }

    private function accepted(string $eventId, bool $duplicate): JsonResponse
    {
        return response()->json([
            'data' => ['event_id' => $eventId, 'duplicate' => $duplicate],
        ]);
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }
}
