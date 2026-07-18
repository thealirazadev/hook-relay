<?php

namespace App\Support\Providers;

use Illuminate\Http\Request;

class GenericProvider implements Provider
{
    public function verify(Request $request, string $secret): bool
    {
        $header = $request->header('X-Signature');

        if (! is_string($header) || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    public function eventId(Request $request): ?string
    {
        return $request->header('X-Event-Id') ?: null;
    }

    public function eventType(Request $request): ?string
    {
        return null;
    }
}
