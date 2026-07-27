<?php

use App\Jobs\DeliverEvent;
use App\Models\Delivery;
use App\Models\Destination;
use App\Models\Source;
use App\Models\WebhookEvent;
use App\Support\DeliverySigner;
use Illuminate\Support\Facades\Http;

function signedDelivery(string $secret, string $payload = '{"hello":"world"}'): Delivery
{
    $source = Source::factory()->create(['name' => 'Acme']);
    $event = WebhookEvent::factory()->for($source)->create([
        'payload' => $payload,
        'content_type' => 'application/json',
    ]);
    $destination = Destination::factory()->create([
        'url' => 'https://dest.test/hook',
        'signing_secret' => $secret,
    ]);

    return Delivery::factory()->for($event, 'event')->create([
        'destination_id' => $destination->id,
        'status' => Delivery::STATUS_PENDING,
        'max_attempts' => 8,
    ]);
}

function deliverOnce(Delivery $delivery): void
{
    $job = new DeliverEvent($delivery);
    $job->withFakeQueueInteractions();
    $job->job->attempts = 1;
    $job->handle();
}

it('signs each forwarded delivery over the timestamped body', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    $secret = 'whsec_known';
    $delivery = signedDelivery($secret, '{"raw":"payload"}');

    deliverOnce($delivery);

    Http::assertSent(function ($request) use ($secret) {
        expect($request->hasHeader('X-Relay-Timestamp'))->toBeTrue();
        expect($request->hasHeader('X-Relay-Signature'))->toBeTrue();

        $timestamp = (int) $request->header('X-Relay-Timestamp')[0];
        $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), $secret);

        return $request->header('X-Relay-Signature')[0] === $expected;
    });
});

it('produces a signature a downstream consumer accepts for a good body and rejects when tampered', function () {
    Http::fake(['*' => Http::response('ok', 200)]);
    $secret = 'whsec_known';
    $delivery = signedDelivery($secret, '{"amount":100}');

    deliverOnce($delivery);

    $signer = new DeliverySigner;

    Http::assertSent(function ($request) use ($signer, $secret) {
        $timestamp = (int) $request->header('X-Relay-Timestamp')[0];
        $signature = $request->header('X-Relay-Signature')[0];

        return $signer->verify($signature, $secret, $timestamp, $request->body())
            && ! $signer->verify($signature, $secret, $timestamp, $request->body().'tampered');
    });
});

it('changes the outbound signature when the destination secret is rotated', function () {
    $this->freezeTime();
    Http::fake(['*' => Http::response('ok', 200)]);

    $first = signedDelivery('whsec_original', '{"amount":100}');
    $destination = $first->destination;

    // A second delivery to the same destination and event, so only the secret varies.
    $second = Delivery::factory()->for($first->event, 'event')->create([
        'destination_id' => $destination->id,
        'status' => Delivery::STATUS_PENDING,
        'max_attempts' => 8,
    ]);

    deliverOnce($first);
    $destination->rotateSigningSecret();
    deliverOnce($second);

    $signatures = [];
    Http::assertSent(function ($request) use (&$signatures) {
        $signatures[] = $request->header('X-Relay-Signature')[0];

        return true;
    });

    expect($signatures)->toHaveCount(2);
    expect($signatures[0])->not->toBe($signatures[1]);
});
