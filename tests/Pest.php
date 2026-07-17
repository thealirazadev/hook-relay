<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Build a POST request with a raw body and the given headers.
 *
 * @param  array<string, string>  $headers
 */
function makeRequest(string $body, array $headers = []): Request
{
    $request = Request::create('/ingest/test', 'POST', content: $body);

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

function stripeSignature(string $body, string $secret, ?int $timestamp = null): string
{
    $timestamp ??= time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

    return "t={$timestamp},v1={$signature}";
}

function githubSignature(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

function shopifySignature(string $body, string $secret): string
{
    return base64_encode(hash_hmac('sha256', $body, $secret, true));
}

function genericSignature(string $body, string $secret): string
{
    return 'sha256='.hash_hmac('sha256', $body, $secret);
}

/**
 * POST a raw body to the ingest endpoint with the given headers.
 *
 * @param  array<string, string>  $headers
 */
function postIngest(string $key, string $body, array $headers = []): TestResponse
{
    $server = [];

    foreach ($headers as $name => $value) {
        if (strtolower($name) === 'content-type') {
            $server['CONTENT_TYPE'] = $value;

            continue;
        }

        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
    }

    return test()->call('POST', "/ingest/{$key}", [], [], [], $server, $body);
}
