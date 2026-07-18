<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ingest
    |--------------------------------------------------------------------------
    |
    | Maximum accepted webhook payload size, in kilobytes. Requests with a body
    | larger than this are rejected with a 413 and nothing is persisted.
    |
    */

    'max_body_kb' => (int) env('INGEST_MAX_BODY_KB', 512),

    /*
    |--------------------------------------------------------------------------
    | Delivery
    |--------------------------------------------------------------------------
    |
    | Per-attempt outbound HTTP timeout in seconds, and the number of attempts a
    | delivery gets before it is parked as dead. The attempt cap is snapshotted
    | onto each delivery at dispatch time so config changes never strand jobs.
    |
    */

    'delivery_timeout_seconds' => (int) env('DELIVERY_TIMEOUT_SECONDS', 10),

    'delivery_max_attempts' => (int) env('DELIVERY_MAX_ATTEMPTS', 8),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Days to keep events (and their deliveries and attempts) before pruning.
    | Events with a non-terminal delivery are never pruned regardless of age.
    |
    */

    'retention_days' => (int) env('RETENTION_DAYS', 30),

];
