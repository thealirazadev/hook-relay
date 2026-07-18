<?php

namespace App\Support;

class Backoff
{
    public const BASE_SECONDS = 30;

    public const MAX_SECONDS = 3600;

    public const JITTER_MIN = 0.8;

    public const JITTER_MAX = 1.2;

    /**
     * Un-jittered delay for the nth failed attempt: min(30 * 2^(n-1), 3600).
     */
    public function base(int $attempt): int
    {
        $attempt = max(1, $attempt);

        return (int) min(self::BASE_SECONDS * (2 ** ($attempt - 1)), self::MAX_SECONDS);
    }

    /**
     * Delay in seconds for the nth failed attempt, multiplied by a uniform
     * jitter factor in [0.8, 1.2] so synchronized failures do not retry in lockstep.
     */
    public function delay(int $attempt): int
    {
        $factor = mt_rand((int) (self::JITTER_MIN * 100), (int) (self::JITTER_MAX * 100)) / 100;

        return (int) round($this->base($attempt) * $factor);
    }
}
