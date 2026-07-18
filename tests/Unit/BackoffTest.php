<?php

use App\Support\Backoff;

beforeEach(function () {
    $this->backoff = new Backoff;
});

it('follows the base doubling progression', function () {
    expect($this->backoff->base(1))->toBe(30);
    expect($this->backoff->base(2))->toBe(60);
    expect($this->backoff->base(3))->toBe(120);
    expect($this->backoff->base(4))->toBe(240);
    expect($this->backoff->base(5))->toBe(480);
    expect($this->backoff->base(6))->toBe(960);
    expect($this->backoff->base(7))->toBe(1920);
});

it('caps the base delay at one hour', function () {
    expect($this->backoff->base(8))->toBe(3600);
    expect($this->backoff->base(20))->toBe(3600);
});

it('treats attempt numbers below one as the first attempt', function () {
    expect($this->backoff->base(0))->toBe(30);
});

it('keeps the jittered delay within the 0.8 to 1.2 band', function () {
    foreach (range(1, 500) as $ignored) {
        $delay = $this->backoff->delay(1);
        expect($delay)->toBeGreaterThanOrEqual(24)->toBeLessThanOrEqual(36);
    }
});

it('applies jitter around the capped base', function () {
    foreach (range(1, 200) as $ignored) {
        $delay = $this->backoff->delay(10);
        expect($delay)->toBeGreaterThanOrEqual(2880)->toBeLessThanOrEqual(4320);
    }
});
