<?php

use App\Support\Providers\ShopifyProvider;

beforeEach(function () {
    $this->provider = new ShopifyProvider;
    $this->secret = 'shpss_secret';
    $this->body = '{"id":9876}';
});

it('accepts a valid base64 signature', function () {
    $request = makeRequest($this->body, ['X-Shopify-Hmac-Sha256' => shopifySignature($this->body, $this->secret)]);

    expect($this->provider->verify($request, $this->secret))->toBeTrue();
});

it('rejects a tampered body', function () {
    $signature = shopifySignature($this->body, $this->secret);
    $request = makeRequest('{"id":1}', ['X-Shopify-Hmac-Sha256' => $signature]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a wrong secret', function () {
    $request = makeRequest($this->body, ['X-Shopify-Hmac-Sha256' => shopifySignature($this->body, 'other')]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a missing header', function () {
    expect($this->provider->verify(makeRequest($this->body), $this->secret))->toBeFalse();
});

it('rejects a malformed non-base64 header without erroring', function () {
    $request = makeRequest($this->body, ['X-Shopify-Hmac-Sha256' => 'not-base64-!!!']);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('extracts the webhook id and topic from headers', function () {
    $request = makeRequest($this->body, [
        'X-Shopify-Webhook-Id' => 'wh-42',
        'X-Shopify-Topic' => 'orders/create',
    ]);

    expect($this->provider->eventId($request))->toBe('wh-42');
    expect($this->provider->eventType($request))->toBe('orders/create');
});

it('returns null ids when the headers are absent', function () {
    $request = makeRequest($this->body);

    expect($this->provider->eventId($request))->toBeNull();
    expect($this->provider->eventType($request))->toBeNull();
});
