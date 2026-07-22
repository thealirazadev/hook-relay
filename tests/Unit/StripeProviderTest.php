<?php

use App\Support\Providers\StripeProvider;

beforeEach(function () {
    $this->provider = new StripeProvider;
    $this->secret = 'whsec_test';
    $this->body = '{"id":"evt_123","type":"charge.succeeded"}';
});

it('accepts a valid signature', function () {
    $request = makeRequest($this->body, ['Stripe-Signature' => stripeSignature($this->body, $this->secret)]);

    expect($this->provider->verify($request, $this->secret))->toBeTrue();
});

it('rejects a tampered body', function () {
    $signature = stripeSignature($this->body, $this->secret);
    $request = makeRequest('{"id":"evt_123","type":"tampered"}', ['Stripe-Signature' => $signature]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a wrong secret', function () {
    $request = makeRequest($this->body, ['Stripe-Signature' => stripeSignature($this->body, 'other')]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a missing header', function () {
    expect($this->provider->verify(makeRequest($this->body), $this->secret))->toBeFalse();
});

it('rejects a malformed header', function () {
    $request = makeRequest($this->body, ['Stripe-Signature' => 'garbage']);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('accepts a timestamp inside the tolerance', function () {
    $signature = stripeSignature($this->body, $this->secret, time() - 290);
    $request = makeRequest($this->body, ['Stripe-Signature' => $signature]);

    expect($this->provider->verify($request, $this->secret))->toBeTrue();
});

it('rejects a timestamp outside the tolerance', function () {
    $signature = stripeSignature($this->body, $this->secret, time() - 600);
    $request = makeRequest($this->body, ['Stripe-Signature' => $signature]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a header carrying a timestamp but no v1 signature', function () {
    $request = makeRequest($this->body, ['Stripe-Signature' => 't='.time()]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a header carrying a v1 signature but no timestamp', function () {
    $valid = hash_hmac('sha256', time().'.'.$this->body, $this->secret);
    $request = makeRequest($this->body, ['Stripe-Signature' => 'v1='.$valid]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a non-numeric timestamp rather than coercing it', function () {
    $valid = hash_hmac('sha256', time().'.'.$this->body, $this->secret);
    $request = makeRequest($this->body, ['Stripe-Signature' => 't=abc,v1='.$valid]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('accepts when one of several v1 signatures matches', function () {
    $valid = hash_hmac('sha256', time().'.'.$this->body, $this->secret);
    $header = 't='.time().',v1=deadbeef,v1='.$valid;
    $request = makeRequest($this->body, ['Stripe-Signature' => $header]);

    expect($this->provider->verify($request, $this->secret))->toBeTrue();
});

it('extracts the event id and type from the json body', function () {
    $request = makeRequest($this->body);

    expect($this->provider->eventId($request))->toBe('evt_123');
    expect($this->provider->eventType($request))->toBe('charge.succeeded');
});

it('returns null ids for a non-json body', function () {
    $request = makeRequest('not json');

    expect($this->provider->eventId($request))->toBeNull();
    expect($this->provider->eventType($request))->toBeNull();
});

it('returns a null event id when the json id is not scalar', function () {
    $request = makeRequest('{"id":{"nested":1},"type":"charge.succeeded"}');

    expect($this->provider->eventId($request))->toBeNull();
    expect($this->provider->eventType($request))->toBe('charge.succeeded');
});
