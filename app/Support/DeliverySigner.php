<?php

namespace App\Support;

/**
 * Signs outbound deliveries so a downstream consumer can verify that a
 * forwarded request genuinely came from this relay and was not tampered with.
 *
 * The signed payload is "{timestamp}.{body}" (the same construction the inbound
 * Stripe verifier uses), so the timestamp is authenticated and a consumer can
 * reject stale deliveries within a tolerance window. The verification recipe
 * lives in the README; this class is the single source of the construction.
 */
class DeliverySigner
{
    /**
     * The `X-Relay-Signature` header value: `sha256=<lowercase hex>`.
     */
    public function sign(string $secret, int $timestamp, string $body): string
    {
        return 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    /**
     * Constant-time check of a signature header against a recomputed one.
     */
    public function verify(string $signature, string $secret, int $timestamp, string $body): bool
    {
        return hash_equals($this->sign($secret, $timestamp, $body), $signature);
    }
}
