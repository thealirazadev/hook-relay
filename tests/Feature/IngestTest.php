<?php

use App\Models\Source;
use App\Models\WebhookEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('accepts a correctly signed stripe webhook and stores one event', function () {
    $source = Source::factory()->provider('stripe')->create(['signing_secret' => 'whsec_x']);
    $body = '{"id":"evt_1","type":"charge.succeeded"}';

    $response = postIngest($source->ingest_key, $body, [
        'Stripe-Signature' => stripeSignature($body, 'whsec_x'),
        'Content-Type' => 'application/json',
    ]);

    $response->assertOk()->assertJson(['data' => ['duplicate' => false]]);

    $event = WebhookEvent::sole();
    expect($event->source_id)->toBe($source->id);
    expect($event->payload)->toBe($body);
    expect($event->provider_event_id)->toBe('evt_1');
    expect($event->event_type)->toBe('charge.succeeded');
    expect($response->json('data.event_id'))->toBe($event->id);
});

it('accepts a correctly signed github webhook', function () {
    $source = Source::factory()->provider('github')->create(['signing_secret' => 'gh']);
    $body = '{"action":"opened"}';

    postIngest($source->ingest_key, $body, [
        'X-Hub-Signature-256' => githubSignature($body, 'gh'),
        'X-GitHub-Delivery' => 'del-1',
        'X-GitHub-Event' => 'issues',
    ])->assertOk();

    $event = WebhookEvent::sole();
    expect($event->provider_event_id)->toBe('del-1');
    expect($event->event_type)->toBe('issues');
});

it('accepts a correctly signed shopify webhook', function () {
    $source = Source::factory()->provider('shopify')->create(['signing_secret' => 'shp']);
    $body = '{"id":1}';

    postIngest($source->ingest_key, $body, [
        'X-Shopify-Hmac-Sha256' => shopifySignature($body, 'shp'),
        'X-Shopify-Webhook-Id' => 'wh-1',
        'X-Shopify-Topic' => 'orders/create',
    ])->assertOk();

    expect(WebhookEvent::sole()->event_type)->toBe('orders/create');
});

it('accepts a correctly signed generic webhook', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"hello":"world"}';

    postIngest($source->ingest_key, $body, [
        'X-Signature' => genericSignature($body, 'gen'),
        'X-Event-Id' => 'g-1',
    ])->assertOk();

    $event = WebhookEvent::sole();
    expect($event->provider_event_id)->toBe('g-1');
    expect($event->event_type)->toBeNull();
});

it('rejects a tampered body with 401 and persists nothing', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';
    $signature = genericSignature($body, 'gen');

    postIngest($source->ingest_key, '{"a":2}', ['X-Signature' => $signature])
        ->assertStatus(401)
        ->assertJson(['error' => ['code' => 'invalid_signature']]);

    expect(WebhookEvent::count())->toBe(0);
});

it('rejects a wrong secret with 401', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'right']);
    $body = '{"a":1}';

    postIngest($source->ingest_key, $body, ['X-Signature' => genericSignature($body, 'wrong')])
        ->assertStatus(401);

    expect(WebhookEvent::count())->toBe(0);
});

it('rejects a stripe timestamp outside tolerance with 401', function () {
    $source = Source::factory()->provider('stripe')->create(['signing_secret' => 'whsec_x']);
    $body = '{"id":"evt_1"}';

    postIngest($source->ingest_key, $body, [
        'Stripe-Signature' => stripeSignature($body, 'whsec_x', time() - 900),
    ])->assertStatus(401);

    expect(WebhookEvent::count())->toBe(0);
});

it('returns 404 for an unknown ingest key', function () {
    postIngest('does-not-exist', '{}', ['X-Signature' => 'x'])
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'unknown_source']]);
});

it('returns 404 for an inactive source', function () {
    $source = Source::factory()->provider('generic')->inactive()->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';

    postIngest($source->ingest_key, $body, ['X-Signature' => genericSignature($body, 'gen')])
        ->assertStatus(404)
        ->assertJson(['error' => ['code' => 'unknown_source']]);
});

it('returns 405 with the envelope for a non-post method', function () {
    $source = Source::factory()->provider('generic')->create();

    $this->getJson('/ingest/'.$source->ingest_key)
        ->assertStatus(405)
        ->assertJson(['error' => ['code' => 'method_not_allowed']]);
});

it('deduplicates a repeated provider event id', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';
    $headers = ['X-Signature' => genericSignature($body, 'gen'), 'X-Event-Id' => 'dup-1'];

    $first = postIngest($source->ingest_key, $body, $headers);
    $first->assertOk()->assertJson(['data' => ['duplicate' => false]]);

    $second = postIngest($source->ingest_key, $body, $headers);
    $second->assertOk()->assertJson(['data' => ['duplicate' => true]]);

    expect(WebhookEvent::count())->toBe(1);
    expect($second->json('data.event_id'))->toBe($first->json('data.event_id'));
});

it('falls back to a body hash when there is no provider event id', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);

    $bodyA = '{"n":1}';
    $bodyB = '{"n":2}';

    postIngest($source->ingest_key, $bodyA, ['X-Signature' => genericSignature($bodyA, 'gen')])->assertOk();
    postIngest($source->ingest_key, $bodyB, ['X-Signature' => genericSignature($bodyB, 'gen')])->assertOk();

    expect(WebhookEvent::count())->toBe(2);

    // Re-sending body A is a duplicate by hash.
    postIngest($source->ingest_key, $bodyA, ['X-Signature' => genericSignature($bodyA, 'gen')])
        ->assertOk()->assertJson(['data' => ['duplicate' => true]]);

    expect(WebhookEvent::count())->toBe(2);
});

it('bounds the dedupe key when a provider sends an over-length event id', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';
    $longId = str_repeat('e', 300);
    $headers = ['X-Signature' => genericSignature($body, 'gen'), 'X-Event-Id' => $longId];

    $first = postIngest($source->ingest_key, $body, $headers);
    $first->assertOk()->assertJson(['data' => ['duplicate' => false]]);

    $event = WebhookEvent::sole();
    // The stored key fits the dedupe_key column and is the deterministic hash
    // of the id, so an id longer than the column can never overflow it.
    expect(mb_strlen($event->dedupe_key))->toBeLessThanOrEqual(191);
    expect($event->dedupe_key)->toBe('sha256:'.hash('sha256', $longId));
    // The raw id is capped to the provider_event_id column width.
    expect(mb_strlen($event->provider_event_id))->toBeLessThanOrEqual(255);

    // Re-sending the same over-length id dedupes: the hash is stable.
    $second = postIngest($source->ingest_key, $body, $headers);
    $second->assertOk()->assertJson(['data' => ['duplicate' => true]]);
    expect($second->json('data.event_id'))->toBe($first->json('data.event_id'));
    expect(WebhookEvent::count())->toBe(1);
});

it('stores filtered headers without denylisted ones', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';

    postIngest($source->ingest_key, $body, [
        'X-Signature' => genericSignature($body, 'gen'),
        'X-Custom' => 'keep-me',
        'Authorization' => 'Bearer secret',
        'Cookie' => 'session=abc',
    ])->assertOk();

    $headers = WebhookEvent::sole()->headers;
    expect($headers)->toHaveKey('x-custom');
    expect($headers)->not->toHaveKey('authorization');
    expect($headers)->not->toHaveKey('cookie');
});

it('rejects a payload over the size limit with 413', function () {
    config()->set('hook_relay.max_body_kb', 1);
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = str_repeat('x', 2048);

    postIngest($source->ingest_key, $body, ['X-Signature' => genericSignature($body, 'gen')])
        ->assertStatus(413)
        ->assertJson(['error' => ['code' => 'payload_too_large']]);

    expect(WebhookEvent::count())->toBe(0);
});

it('returns 200 duplicate when a concurrent insert wins the unique race', function () {
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';
    $headers = ['X-Signature' => genericSignature($body, 'gen'), 'X-Event-Id' => 'race-1'];

    // Simulate a competing request that commits the same (source_id, dedupe_key)
    // between this request's duplicate pre-check and its own insert: plant the row
    // from inside the "creating" hook so the real insert hits the unique violation
    // and must fall through to the catch block's re-query.
    $plantedId = (string) Str::ulid();
    $armed = true;

    WebhookEvent::creating(function () use (&$armed, $source, $plantedId) {
        if (! $armed) {
            return;
        }
        $armed = false;

        DB::table('webhook_events')->insert([
            'id' => $plantedId,
            'source_id' => $source->id,
            'provider_event_id' => 'race-1',
            'dedupe_key' => 'race-1',
            'event_type' => null,
            'headers' => '{}',
            'payload' => '{"a":1}',
            'content_type' => 'application/json',
            'received_at' => now(),
            'created_at' => now(),
        ]);
    });

    $response = postIngest($source->ingest_key, $body, $headers);

    $armed = false;

    $response->assertOk()->assertJson(['data' => ['duplicate' => true]]);
    expect($response->json('data.event_id'))->toBe($plantedId);
    expect(WebhookEvent::count())->toBe(1);
});

it('logs structured ingest events', function () {
    Log::spy();
    $source = Source::factory()->provider('generic')->create(['signing_secret' => 'gen']);
    $body = '{"a":1}';

    postIngest($source->ingest_key, $body, ['X-Signature' => genericSignature($body, 'gen')])->assertOk();
    Log::shouldHaveReceived('info')->withArgs(fn ($event) => $event === 'ingest.accepted')->once();

    postIngest($source->ingest_key, '{"a":2}', ['X-Signature' => 'sha256=bad'])->assertStatus(401);
    Log::shouldHaveReceived('warning')->withArgs(fn ($event) => $event === 'ingest.signature_failed')->once();
});
