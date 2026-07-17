# Testing — hook-relay

## Strategy

- **Automated first, manual second.** Every feature ships with automated tests in the same
  commit series; the manual checklists in `docs/phases.md` cover what automation can't observe
  well (a real worker process, a real echo destination, browser keyboard passes).
- **Pest on PHPUnit.** Feature tests for HTTP and job behavior, unit tests for isolated helpers.
  Tests run against in-memory SQLite with `RefreshDatabase` (configured in `phpunit.xml`)
  regardless of the dev database; never against the development database.
- **Fakes over network.** `Http::fake()` for destination responses (success, 4xx/5xx, timeout via
  faked exceptions), `Queue::fake()` where dispatch itself is the assertion, and synchronous job
  execution (`$job->handle()` or `Bus::fake` alternatives) where attempt side effects are the
  assertion. No test ever performs a real outbound HTTP request.
- **Factories** for `User`, `Source`, `Destination`, `WebhookEvent`, `Delivery`,
  `DeliveryAttempt` drive setup; no hand-built rows. Signed ingest requests are built by a small
  test helper that computes real HMACs per provider, so verification is exercised for real.

### What to cover

Unit tests:
- Each provider class: valid signature accepted; tampered body, wrong secret, missing/malformed
  header rejected; Stripe timestamp inside/outside the 300s tolerance; Stripe multiple `v1`
  entries; correct event id and event type extraction; hash fallback when the id is absent.
- `Backoff`: exact base progression, cap at 3600s, jitter stays within 0.8–1.2, attempt cap.
- `HeaderFilter`: denylisted headers (authorization, cookie, proxy) stripped; others preserved.

Feature tests:
- Auth: login success, wrong password (safe error), throttle, logout, guests redirected, no
  registration route exists.
- Source/destination CRUD: validation, encrypted secret storage (raw value absent from DB dump),
  soft delete behavior (404 on ingest, history preserved), routing checkboxes persist.
- Ingest, per provider: 200 + persisted event with byte-identical payload; 401 nothing persisted;
  404 unknown/inactive key; 405 wrong method; 413 oversize; duplicate → `duplicate: true`, one
  row, no new deliveries; headers stored minus denylist.
- Delivery: fan-out (N routed destinations → N pending deliveries, inactive/detached excluded);
  2xx → `delivered` + one attempt row with status/headers/excerpt/duration; failure →
  `failed` with `next_attempt_at`; exhaustion → `dead`; attempt rows accumulate; response
  excerpt capped at 2048 bytes; idempotency headers present and `X-Relay-Event-Id` stable
  across attempts; redirects not followed.
- DLQ: view lists only `dead`; requeue resets to `pending`, keeps attempt history, fresh budget;
  requeue-all chunks.
- Replay: single and bulk create fresh deliveries to currently routed destinations; same event
  ULID reused; cap of 100 enforced; no duplicate event rows.
- Dashboard filters: each filter, combinations, pagination, empty results.
- Pruning: boundary at `RETENTION_DAYS`; events with non-terminal deliveries survive; cascade
  removes deliveries and attempts.
- Ingest throttle: 429 envelope per key; other keys unaffected.
- Envelope shape: every ingest success and error response matches `docs/api-contracts.md`
  exactly.

## Exact commands

```bash
# Full test suite
php artisan test

# Equivalent via Pest directly
./vendor/bin/pest

# A single file
php artisan test tests/Feature/IngestTest.php

# Filter by test name
php artisan test --filter=rejects_tampered_body

# Formatting check (must pass; PSR-12 via Pint)
./vendor/bin/pint --test

# Auto-fix formatting
./vendor/bin/pint
```

First-time setup:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:create-user you@example.com
```

Running the full system locally (three processes):

```bash
php artisan serve            # web
php artisan queue:work       # deliveries
php artisan schedule:work    # pruning (only needed when testing retention)
```

## Definition of "done" for a feature

A feature is not done until all of the following pass, in order:

1. `./vendor/bin/pint --test` — no style violations.
2. `php artisan test` — full suite green, new tests included.
3. The feature's manual checklist items in `docs/phases.md` pass.

After creating or editing files, run build/tests and fix all errors before reporting done. One
commit per feature, in the order listed in `docs/phases.md`.
