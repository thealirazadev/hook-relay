# hook-relay

[![CI](https://github.com/thealirazadev/hook-relay/actions/workflows/ci.yml/badge.svg)](https://github.com/thealirazadev/hook-relay/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A self-hosted inbound webhook gateway built with Laravel. Point third-party webhooks (Stripe,
GitHub, Shopify, or any generic HMAC source) at a hook-relay ingest URL; it verifies the
signature, persists every event, and forwards each one to your configured destination URLs with
retries, dead-lettering, and a full per-attempt audit trail. A server-rendered dashboard lets you
browse, filter, inspect, and replay events and deliveries.

## Stack

- PHP 8.2+ / Laravel 12.x
- SQLite (local dev) / MySQL 8.x (production)
- Database queue driver (no Redis dependency)
- Blade + one static CSS file for the dashboard (no Node toolchain)
- Pest (feature + unit tests) on PHPUnit
- Laravel Pint (PSR-12 formatting)

See `docs/` for the PRD, architecture, API contracts, phases, and engineering rules.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:create-user you@example.com   # prompts for a password
```

The four hook-relay settings (`INGEST_MAX_BODY_KB`, `DELIVERY_TIMEOUT_SECONDS`,
`DELIVERY_MAX_ATTEMPTS`, `RETENTION_DAYS`) live in `.env`; see `.env.example` for defaults.

## Run

Three processes make up a full local instance:

```bash
php artisan serve          # dashboard + ingest endpoint
php artisan queue:work     # delivers events to destinations
php artisan schedule:work  # runs retention pruning (only needed to exercise pruning)
```

Log in at `/login`, create a source to get its ingest URL (`POST /ingest/{key}`), add a
destination, and route the destination to the source on the source's edit screen. Point a
provider webhook at the ingest URL, or send a signed request yourself.

## Test

```bash
php artisan test              # full suite (Pest on PHPUnit)
./vendor/bin/pest             # equivalent
./vendor/bin/pint --test      # PSR-12 formatting check
```

## Production

`php artisan migrate --force`, run `php artisan queue:work` under a supervisor, and add a cron
entry for `php artisan schedule:run` every minute. See `docs/launch-checklist.md`.

## Benchmark

Measured with `benchmarks/delivery_throughput.php`, which delivers to a local `php -S` echo server
(200 OK) over loopback and does the real per-delivery work — the `delivering` → attempt-row →
`delivered` state writes plus a live Guzzle POST. No mocking.

Conditions: 12th Gen Intel Core i5-1235U (12 threads), 32 GB RAM, Ubuntu 24.04, PHP 8.2.29,
SQLite 3.37.2. One synchronous worker (`QUEUE_CONNECTION=sync`), 500 deliveries against a throwaway
SQLite database, 20-delivery warmup.

| Metric | Value |
|---|---|
| Throughput (single worker) | ~68 deliveries/s (median of 5 warm runs; 68–70 steady-state, ~43 on a cold first run) |
| Per-delivery wall time | ~15 ms (three committed SQLite writes + one HTTP POST) |
| HTTP round-trip per attempt | ~1 ms p50, ~2 ms p95 |

Retry backoff for attempt _n_ is `min(30 x 2^(n-1), 3600)` seconds, jittered by a uniform factor in
`[0.8, 1.2]`. Measured base delays and observed jitter bounds over 10,000 samples per attempt:

| Attempt | Base (s) | Observed min (s) | Observed max (s) |
|---|---|---|---|
| 1 | 30 | 24 | 36 |
| 2 | 60 | 48 | 72 |
| 3 | 120 | 96 | 144 |
| 4 | 240 | 192 | 288 |
| 5 | 480 | 384 | 576 |
| 6 | 960 | 768 | 1152 |
| 7 | 1920 | 1536 | 2304 |

Throughput here is bound by SQLite's synchronous per-transaction commit, not by the ~1 ms network
hop. Production runs on MySQL with multiple `php artisan queue:work` processes, which raises
aggregate throughput roughly linearly with worker count until database write contention dominates.

Run it yourself: `php benchmarks/delivery_throughput.php [count]`. It uses a throwaway database and
echo server, both cleaned up on exit, and never touches your dev data.

## Design decisions

The trade-offs that shaped hook-relay, and the alternatives they were weighed against. Fuller
rationale lives in `docs/architecture.md` and `docs/memory.md`.

- **At-least-once delivery, not exactly-once.** A delivery is retried until a `2xx` is observed or
  the attempt cap is hit. A crash between a successful POST and recording the result causes a
  re-send, never a lost event. Exactly-once across an untrusted network is not achievable without
  downstream cooperation, so instead every request carries a stable `X-Relay-Event-Id` (unchanged
  across retries, requeues, and replays) and consumers dedupe on it.

- **Database queue, not Redis.** The queue is the heart of the product, and the `database` driver
  keeps the whole deployment a single PHP + database box. The trade-off is lower throughput and
  DB-polling overhead versus Redis; it is acceptable because a self-hosted relay handles hundreds
  of events per minute, not thousands per second, and dropping the Redis dependency makes
  self-hosting materially easier. Queue and failed-job state share the database backup story.

- **Dedupe by provider event id, with a payload-hash fallback.** The idempotency key is a unique
  `(source_id, dedupe_key)` index. `dedupe_key` is the provider's own event id (Stripe `id`,
  `X-GitHub-Delivery`, `X-Shopify-Webhook-Id`, generic `X-Event-Id`) when present, otherwise
  `sha256:<hex of the raw body>`. This dedupes provider-side retries precisely when an id exists
  and still catches byte-identical re-sends when it does not, without trusting the client to supply
  a key.

- **Duplicate ingest returns `200`, not `409`.** Providers treat any non-2xx as a failure and will
  retry the webhook, often forever. A duplicate is a success from the sender's perspective — the
  event is already safely stored — so it returns `200` with `{"duplicate": true}` rather than a
  conflict status that would trigger an unwanted retry storm.

- **Replay uses current routing, not a snapshot.** Replaying an event reuses the existing event row
  (same ULID, so downstream dedupe still holds) and fans out fresh deliveries to the destinations
  routed to the source *right now*. There is intentionally no unique key on
  `(webhook_event_id, destination_id)`: a replay is meant to create additional deliveries, letting
  operators re-drive traffic to destinations that were added, fixed, or re-enabled after the
  original ingest.

## License

License: MIT
