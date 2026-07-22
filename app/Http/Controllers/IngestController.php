<?php

namespace App\Http\Controllers;

use App\Models\Source;
use App\Support\HeaderFilter;
use App\Support\Providers\ProviderResolver;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IngestController extends Controller
{
    /** Width of the dedupe_key column; a longer provider id is hashed to fit. */
    private const DEDUPE_KEY_MAX = 191;

    /** Width of the string columns fed from request data; values are capped to fit. */
    private const STRING_COLUMN_MAX = 255;

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
        $dedupeKey = $this->dedupeKey($providerEventId, $body);

        if ($existing = $source->events()->where('dedupe_key', $dedupeKey)->first()) {
            Log::info('ingest.duplicate', ['source_id' => $source->id, 'event_id' => $existing->id]);

            return $this->accepted($existing->id, true);
        }

        // Persist the event and fan it out to deliveries atomically. If the
        // fan-out fails, the event is rolled back too, so the provider's retry
        // re-ingests it instead of short-circuiting on a stranded, delivery-less
        // duplicate. Delivery jobs are dispatched only after this commits.
        $result = DB::transaction(function () use ($source, $provider, $request, $providerEventId, $dedupeKey, $body) {
            try {
                $event = $source->events()->create([
                    'provider_event_id' => $this->cap($providerEventId),
                    'dedupe_key' => $dedupeKey,
                    'event_type' => $this->cap($provider->eventType($request)),
                    'headers' => $this->headerFilter->filter($request->headers->all()),
                    'payload' => $body,
                    'content_type' => $this->cap($request->header('Content-Type', 'application/json')),
                    'received_at' => now(),
                ]);
            } catch (QueryException $e) {
                // A concurrent identical POST won the unique (source_id, dedupe_key)
                // race between the pre-check and this insert. The winner's row is
                // already committed, so treat this request as a duplicate.
                if ($existing = $source->events()->where('dedupe_key', $dedupeKey)->first()) {
                    return ['event' => $existing, 'duplicate' => true];
                }

                throw $e;
            }

            $event->createDeliveries();

            return ['event' => $event, 'duplicate' => false];
        });

        if ($result['duplicate']) {
            Log::info('ingest.duplicate', ['source_id' => $source->id, 'event_id' => $result['event']->id]);

            return $this->accepted($result['event']->id, true);
        }

        Log::info('ingest.accepted', [
            'source_id' => $source->id,
            'event_id' => $result['event']->id,
            'event_type' => $result['event']->event_type,
        ]);

        return $this->accepted($result['event']->id, false);
    }

    /**
     * Derive the dedupe key for an event. Falls back to a body hash when the
     * provider supplies no event id, and hashes an over-long provider id so the
     * key always fits the dedupe_key column. Hashing is deterministic, so the
     * same provider id always maps to the same key and dedupe is preserved.
     */
    private function dedupeKey(?string $providerEventId, string $body): string
    {
        if ($providerEventId === null) {
            return 'sha256:'.hash('sha256', $body);
        }

        if (mb_strlen($providerEventId) > self::DEDUPE_KEY_MAX) {
            return 'sha256:'.hash('sha256', $providerEventId);
        }

        return $providerEventId;
    }

    /** Cap a request-derived string so it fits its storage column on strict MySQL. */
    private function cap(?string $value): ?string
    {
        return $value === null ? null : mb_substr($value, 0, self::STRING_COLUMN_MAX);
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
