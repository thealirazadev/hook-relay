<?php

namespace App\Support\Providers;

use Illuminate\Http\Request;

class StripeProvider implements Provider
{
    /** Reject signatures whose timestamp is more than this many seconds off. */
    private const TOLERANCE_SECONDS = 300;

    public function verify(Request $request, string $secret): bool
    {
        $header = $request->header('Stripe-Signature');

        if (! is_string($header) || $header === '') {
            return false;
        }

        [$timestamp, $signatures] = $this->parseHeader($header);

        if ($timestamp === null || $signatures === []) {
            return false;
        }

        if (abs(time() - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function eventId(Request $request): ?string
    {
        return $this->jsonValue($request, 'id');
    }

    public function eventType(Request $request): ?string
    {
        return $this->jsonValue($request, 'type');
    }

    /**
     * Parse "t=<ts>,v1=<hex>,v1=<hex>" into [timestamp, [v1 signatures]].
     *
     * @return array{0: int|null, 1: list<string>}
     */
    private function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);

            if (count($pair) !== 2) {
                continue;
            }

            [$key, $value] = $pair;

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        return [$timestamp, $signatures];
    }

    private function jsonValue(Request $request, string $key): ?string
    {
        $decoded = json_decode($request->getContent(), true);

        if (! is_array($decoded) || ! isset($decoded[$key]) || ! is_scalar($decoded[$key])) {
            return null;
        }

        return (string) $decoded[$key];
    }
}
