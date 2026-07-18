<?php

use App\Support\Providers\GenericProvider;

beforeEach(function () {
    $this->provider = new GenericProvider;
    $this->secret = 'generic_secret';
    $this->body = '{"hello":"world"}';
});

it('accepts a valid signature', function () {
    $request = makeRequest($this->body, ['X-Signature' => genericSignature($this->body, $this->secret)]);

    expect($this->provider->verify($request, $this->secret))->toBeTrue();
});

it('rejects a tampered body', function () {
    $signature = genericSignature($this->body, $this->secret);
    $request = makeRequest('{"hello":"there"}', ['X-Signature' => $signature]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a wrong secret', function () {
    $request = makeRequest($this->body, ['X-Signature' => genericSignature($this->body, 'other')]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a missing or malformed header', function () {
    expect($this->provider->verify(makeRequest($this->body), $this->secret))->toBeFalse();
    expect($this->provider->verify(makeRequest($this->body, ['X-Signature' => 'plain']), $this->secret))->toBeFalse();
});

it('extracts the optional event id header and has no event type', function () {
    $request = makeRequest($this->body, ['X-Event-Id' => 'gen-1']);

    expect($this->provider->eventId($request))->toBe('gen-1');
    expect($this->provider->eventType($request))->toBeNull();
});

it('returns a null event id when the optional header is absent', function () {
    expect((new GenericProvider)->eventId(makeRequest($this->body)))->toBeNull();
});
