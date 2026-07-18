<?php

namespace App\Support\Providers;

use Illuminate\Http\Request;

interface Provider
{
    /**
     * Verify the request signature against the source's signing secret.
     * Implementations must use constant-time comparison and never throw on
     * malformed input; a bad or missing signature simply returns false.
     */
    public function verify(Request $request, string $secret): bool;

    /**
     * The provider's event id used for deduplication, or null when the
     * provider does not supply one (falls back to a payload hash upstream).
     */
    public function eventId(Request $request): ?string;

    /**
     * The provider's event type, or null when the provider has no concept of one.
     */
    public function eventType(Request $request): ?string;
}
