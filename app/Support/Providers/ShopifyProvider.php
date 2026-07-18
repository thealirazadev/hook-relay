<?php

namespace App\Support\Providers;

use Illuminate\Http\Request;

class ShopifyProvider implements Provider
{
    public function verify(Request $request, string $secret): bool
    {
        $header = $request->header('X-Shopify-Hmac-Sha256');

        if (! is_string($header) || $header === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $request->getContent(), $secret, true));

        return hash_equals($expected, $header);
    }

    public function eventId(Request $request): ?string
    {
        return $request->header('X-Shopify-Webhook-Id') ?: null;
    }

    public function eventType(Request $request): ?string
    {
        return $request->header('X-Shopify-Topic') ?: null;
    }
}
