<?php

use App\Support\DeliverySigner;

beforeEach(function () {
    $this->signer = new DeliverySigner;
});

it('produces a sha256-prefixed hmac over the timestamped body', function () {
    $secret = 'whsec_test';
    $body = '{"hello":"world"}';
    $timestamp = 1_700_000_000;

    $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    expect($this->signer->sign($secret, $timestamp, $body))->toBe($expected);
});

it('verifies a correctly signed body', function () {
    $secret = 'whsec_test';
    $body = '{"amount":100}';
    $timestamp = 1_700_000_000;

    $signature = $this->signer->sign($secret, $timestamp, $body);

    expect($this->signer->verify($signature, $secret, $timestamp, $body))->toBeTrue();
});

it('rejects a tampered body', function () {
    $secret = 'whsec_test';
    $timestamp = 1_700_000_000;

    $signature = $this->signer->sign($secret, $timestamp, '{"amount":100}');

    expect($this->signer->verify($signature, $secret, $timestamp, '{"amount":999}'))->toBeFalse();
});

it('rejects a wrong secret', function () {
    $body = '{"amount":100}';
    $timestamp = 1_700_000_000;

    $signature = $this->signer->sign('whsec_original', $timestamp, $body);

    expect($this->signer->verify($signature, 'whsec_rotated', $timestamp, $body))->toBeFalse();
});

it('rejects a tampered timestamp', function () {
    $secret = 'whsec_test';
    $body = '{"amount":100}';

    $signature = $this->signer->sign($secret, 1_700_000_000, $body);

    expect($this->signer->verify($signature, $secret, 1_700_000_300, $body))->toBeFalse();
});

it('rejects a length-equal but different signature (constant-time compare)', function () {
    $secret = 'whsec_test';
    $body = '{"amount":100}';
    $timestamp = 1_700_000_000;

    $good = $this->signer->sign($secret, $timestamp, $body);
    // Flip the last hex nibble: same length, one bit different.
    $lastChar = substr($good, -1);
    $forged = substr($good, 0, -1).($lastChar === '0' ? '1' : '0');

    expect(strlen($forged))->toBe(strlen($good));
    expect($this->signer->verify($forged, $secret, $timestamp, $body))->toBeFalse();
});

it('changes the signature when the secret is rotated', function () {
    $body = '{"amount":100}';
    $timestamp = 1_700_000_000;

    $before = $this->signer->sign('whsec_original', $timestamp, $body);
    $after = $this->signer->sign('whsec_rotated', $timestamp, $body);

    expect($after)->not->toBe($before);
});
