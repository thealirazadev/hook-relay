<?php

namespace App\Support;

class HeaderFilter
{
    /** Headers never persisted with an event; matched case-insensitively. */
    private const DENYLIST = [
        'authorization',
        'cookie',
        'set-cookie',
        'proxy-authorization',
        'proxy-authenticate',
    ];

    /**
     * Reduce a request's header bag to a stored map, dropping denylisted headers.
     *
     * @param  array<string, array<int, string|null>|string>  $headers
     * @return array<string, string>
     */
    public function filter(array $headers): array
    {
        $filtered = [];

        foreach ($headers as $name => $values) {
            $lower = strtolower($name);

            if (in_array($lower, self::DENYLIST, true)) {
                continue;
            }

            $filtered[$lower] = is_array($values)
                ? implode(', ', array_map(fn ($v) => (string) $v, $values))
                : (string) $values;
        }

        return $filtered;
    }
}
