<?php

use App\Jobs\DeliverEvent;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Source;
use App\Models\WebhookEvent;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

function makeDelivery(array $deliveryOverrides = [], array $eventOverrides = []): Delivery
{
    $source = Source::factory()->create(['name' => 'Acme']);
    $event = WebhookEvent::factory()->for($source)->create(array_merge([
        'payload' => '{"hello":"world"}',
        'content_type' => 'application/json',
    ], $eventOverrides));
    $destination = Destination::factory()->create(['url' => 'https://dest.test/hook']);

    return Delivery::factory()->for($event, 'event')->create(array_merge([
        'destination_id' => $destination->id,
        'status' => Delivery::STATUS_PENDING,
        'max_attempts' => 8,
    ], $deliveryOverrides));
}

function runJob(Delivery $delivery, int $attempts = 1): DeliverEvent
{
    $job = new DeliverEvent($delivery);
    $job->withFakeQueueInteractions();
    $job->job->attempts = $attempts;
    $job->handle();

    return $job;
}

it('marks a delivery delivered on a 2xx response', function () {
    Http::fake(['*' => Http::response('{"ok":true}', 200, ['X-Trace' => 'abc'])]);
    $delivery = makeDelivery();

    runJob($delivery);

    $delivery->refresh();
    expect($delivery->status)->toBe(Delivery::STATUS_DELIVERED);
    expect($delivery->attempts()->count())->toBe(1);

    $attempt = $delivery->attempts()->first();
    expect($attempt->response_status)->toBe(200);
    expect($attempt->response_body_excerpt)->toBe('{"ok":true}');
    expect($attempt->response_headers)->toHaveKey('x-trace');
    expect($attempt->duration_ms)->toBeGreaterThanOrEqual(0);
    expect($attempt->error)->toBeNull();
});

it('sends idempotency headers and the raw body byte-identical', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    $delivery = makeDelivery([], ['payload' => '{"raw":"payload"}']);
    $event = $delivery->event;

    runJob($delivery);

    Http::assertSent(function ($request) use ($event, $delivery) {
        return $request->url() === 'https://dest.test/hook'
            && $request->hasHeader('X-Relay-Event-Id', $event->id)
            && $request->hasHeader('X-Relay-Delivery-Id', $delivery->id)
            && $request->hasHeader('X-Relay-Source', 'Acme')
            && $request->hasHeader('User-Agent', 'hook-relay/1.0')
            && $request->body() === '{"raw":"payload"}';
    });
});

it('marks a delivery failed and schedules a retry when budget remains', function () {
    Http::fake(['*' => Http::response('boom', 500)]);
    $delivery = makeDelivery(['max_attempts' => 8]);

    $job = runJob($delivery, attempts: 1);

    $delivery->refresh();
    expect($delivery->status)->toBe(Delivery::STATUS_FAILED);
    expect($delivery->next_attempt_at)->not->toBeNull();
    expect($delivery->attempts()->count())->toBe(1);
    $job->assertReleased();
});

it('marks a delivery dead when the attempt cap is reached', function () {
    Http::fake(['*' => Http::response('boom', 500)]);
    $delivery = makeDelivery(['max_attempts' => 3]);

    $job = runJob($delivery, attempts: 3);

    $delivery->refresh();
    expect($delivery->status)->toBe(Delivery::STATUS_DEAD);
    expect($delivery->next_attempt_at)->toBeNull();
    expect($delivery->attempts()->count())->toBe(1);
    $job->assertNotReleased();
});

it('retries then delivers, keeping a stable event id across attempts', function () {
    Http::fakeSequence()->push('err', 500)->push('{"ok":1}', 200);
    $delivery = makeDelivery();
    $event = $delivery->event;

    runJob($delivery);
    expect($delivery->fresh()->status)->toBe(Delivery::STATUS_FAILED);

    runJob($delivery->fresh());
    $delivery->refresh();

    expect($delivery->status)->toBe(Delivery::STATUS_DELIVERED);
    expect($delivery->attempt_count)->toBe(2);
    expect($delivery->attempts()->count())->toBe(2);

    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request->hasHeader('X-Relay-Event-Id', $event->id));
});

it('counts a 302 redirect as a failure and does not follow it', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => 'https://elsewhere.test'])]);
    $delivery = makeDelivery(['max_attempts' => 8]);

    runJob($delivery);

    $delivery->refresh();
    expect($delivery->status)->toBe(Delivery::STATUS_FAILED);
    expect($delivery->attempts()->first()->response_status)->toBe(302);
});

it('records a connection error as an attempt with the error set', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));
    $delivery = makeDelivery(['max_attempts' => 8]);

    $job = runJob($delivery);

    $delivery->refresh();
    expect($delivery->status)->toBe(Delivery::STATUS_FAILED);

    $attempt = $delivery->attempts()->first();
    expect($attempt->response_status)->toBeNull();
    expect($attempt->error)->toContain('Connection timed out');
    $job->assertReleased();
});

it('caps the stored response body excerpt at 2048 bytes', function () {
    Http::fake(['*' => Http::response(str_repeat('a', 5000), 500)]);
    $delivery = makeDelivery(['max_attempts' => 8]);

    runJob($delivery);

    expect(strlen($delivery->attempts()->first()->response_body_excerpt))->toBe(2048);
});

it('skips a delivery that is already terminal', function () {
    Http::fake();
    $delivery = makeDelivery(['status' => Delivery::STATUS_DELIVERED]);

    runJob($delivery);

    Http::assertNothingSent();
    expect($delivery->fresh()->attempts()->count())->toBe(0);
});
