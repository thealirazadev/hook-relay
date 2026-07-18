<?php

namespace App\Support\Providers;

use Illuminate\Http\Request;

class GithubProvider implements Provider
{
    public function verify(Request $request, string $secret): bool
    {
        $header = $request->header('X-Hub-Signature-256');

        if (! is_string($header) || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);

        return hash_equals($expected, $header);
    }

    public function eventId(Request $request): ?string
    {
        return $request->header('X-GitHub-Delivery') ?: null;
    }

    public function eventType(Request $request): ?string
    {
        return $request->header('X-GitHub-Event') ?: null;
    }
}
