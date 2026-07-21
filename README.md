# hook-relay

[![CI](https://github.com/thealirazadev/hook-relay/actions/workflows/ci.yml/badge.svg)](https://github.com/thealirazadev/hook-relay/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

A self-hosted inbound webhook gateway built with Laravel. Point third-party webhooks (Stripe,
GitHub, Shopify, or any generic HMAC source) at a hook-relay ingest URL; it verifies the
signature, persists every event, and forwards each one to your configured destination URLs with
retries, dead-lettering, and a full per-attempt audit trail. A server-rendered dashboard lets you
browse, filter, inspect, and replay events and deliveries.

## Stack

- PHP 8.2+ / Laravel 11.x
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
