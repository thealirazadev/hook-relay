<?php

use App\Support\HeaderFilter;

beforeEach(function () {
    $this->filter = new HeaderFilter;
});

it('strips denylisted headers', function () {
    $result = $this->filter->filter([
        'Authorization' => ['Bearer token'],
        'Cookie' => ['session=abc'],
        'Proxy-Authorization' => ['x'],
        'Content-Type' => ['application/json'],
    ]);

    expect($result)->not->toHaveKey('authorization');
    expect($result)->not->toHaveKey('cookie');
    expect($result)->not->toHaveKey('proxy-authorization');
    expect($result)->toHaveKey('content-type');
});

it('preserves non-denylisted headers and lowercases their names', function () {
    $result = $this->filter->filter([
        'X-GitHub-Event' => ['push'],
        'Content-Type' => ['application/json'],
    ]);

    expect($result['x-github-event'])->toBe('push');
    expect($result['content-type'])->toBe('application/json');
});

it('joins multi-valued headers', function () {
    $result = $this->filter->filter([
        'X-Forwarded-For' => ['1.1.1.1', '2.2.2.2'],
    ]);

    expect($result['x-forwarded-for'])->toBe('1.1.1.1, 2.2.2.2');
});

it('accepts scalar header values', function () {
    $result = $this->filter->filter([
        'X-Single' => 'value',
    ]);

    expect($result['x-single'])->toBe('value');
});
