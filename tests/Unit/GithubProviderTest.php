<?php

use App\Support\Providers\GithubProvider;

beforeEach(function () {
    $this->provider = new GithubProvider;
    $this->secret = 'gh_secret';
    $this->body = '{"action":"opened"}';
});

it('accepts a valid signature', function () {
    $request = makeRequest($this->body, ['X-Hub-Signature-256' => githubSignature($this->body, $this->secret)]);

    expect($this->provider->verify($request, $this->secret))->toBeTrue();
});

it('rejects a tampered body', function () {
    $signature = githubSignature($this->body, $this->secret);
    $request = makeRequest('{"action":"closed"}', ['X-Hub-Signature-256' => $signature]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a wrong secret', function () {
    $request = makeRequest($this->body, ['X-Hub-Signature-256' => githubSignature($this->body, 'other')]);

    expect($this->provider->verify($request, $this->secret))->toBeFalse();
});

it('rejects a missing or malformed header', function () {
    expect($this->provider->verify(makeRequest($this->body), $this->secret))->toBeFalse();
    expect($this->provider->verify(makeRequest($this->body, ['X-Hub-Signature-256' => 'nope']), $this->secret))->toBeFalse();
});

it('extracts the delivery id and event type from headers', function () {
    $request = makeRequest($this->body, [
        'X-GitHub-Delivery' => 'abc-123',
        'X-GitHub-Event' => 'pull_request',
    ]);

    expect($this->provider->eventId($request))->toBe('abc-123');
    expect($this->provider->eventType($request))->toBe('pull_request');
});

it('returns null ids when the headers are absent', function () {
    $request = makeRequest($this->body);

    expect($this->provider->eventId($request))->toBeNull();
    expect($this->provider->eventType($request))->toBeNull();
});
