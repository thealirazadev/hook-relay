# Phases — hook-relay

**Rule: phase N+1 does not start until the owner approves phase N.** Phases are ordered
smallest-useful-shippable first; each ends green (app runs, tests pass, Pint clean, logs quiet).
One commit per feature/task, Conventional Commits, in the listed order.

The senior differentiators are hard requirements placed early: idempotent ingest lands in
Phase 1; at-least-once delivery with backoff + jitter, the dead-letter state with manual requeue,
the delivery idempotency headers, and the full attempt audit trail all land in Phase 2. None of
these may slip to a later phase.

---

## Phase 1 — Foundation, sources, and verified idempotent ingest

**Goal**: A provider webhook pointed at a hook-relay URL is signature-verified and durably,
idempotently persisted. Smallest slice that is already useful (a webhook inbox with proof of
receipt).

### Definition of done

- App scaffolded; SQLite dev database; `database` drivers for queue/session/cache; `.env` from
  `.env.example`; `config/hook_relay.php` holds the four env-backed settings.
- Migrations applied: framework tables plus `sources` and `webhook_events` exactly as specified
  in `docs/architecture.md` (including the unique `(source_id, dedupe_key)` index).
- `app:create-user` command creates the operator; session login/logout with IP-throttled login;
  every dashboard route behind `auth`; no registration route.
- Source CRUD screens: create (name, provider, secret), index showing full ingest URLs, edit,
  soft delete. Secret stored via `encrypted` cast and never re-rendered in full.
- All four provider classes (`stripe`, `github`, `shopify`, `generic`) implement verify /
  eventId / eventType per `docs/api-contracts.md`, using `hash_equals`; Stripe enforces the
  300s timestamp tolerance.
- `POST /ingest/{ingestKey}` (stateless route file, no CSRF/session): unknown or inactive key
  404, oversize body 413, bad signature 401 with nothing persisted, valid request 200 with the
  event ULID, duplicate request 200 with `duplicate: true` and nothing new persisted.
- Structured logs for `ingest.accepted`, `ingest.duplicate`, `ingest.signature_failed`,
  `ingest.unknown_source`, `ingest.payload_too_large`, `auth.login_failed`.
- Pint clean; unit tests (four providers, header filter) and feature tests (auth, source CRUD,
  ingest paths incl. dedupe race via double-send) pass.

### Manual test checklist

- Run `php artisan app:create-user ada@example.com` → log in at `/login` → dashboard loads;
  wrong password → safe error, no user enumeration; `/sources` without session → redirect.
- Create a `generic` source → index shows `APP_URL/ingest/<32-char key>`.
- `curl -X POST` that URL with a correctly computed `X-Signature` → 200 with `event_id`;
  repeat the exact request → 200 with `duplicate: true`; check the DB has one row.
- Same request with one byte of the body changed (old signature) → 401 envelope; no new row.
- POST to `/ingest/nonexistent` → 404 `unknown_source`; GET the real ingest URL → 405.
- Send a body over `INGEST_MAX_BODY_KB` → 413 envelope.
- Create a `stripe` source and send a valid `Stripe-Signature` with `t` 10 minutes old → 401.

### Verification

- App runs (`php artisan serve`), tests pass (`php artisan test`), `./vendor/bin/pint --test`
  clean, log output during the manual run shows only expected `ingest.*`/`auth.*` events.
- Unhappy paths: empty create-source form → field errors, no 500; secret with trailing
  whitespace → verification still matches (trim on save); duplicate double-submit of the same
  webhook (two rapid identical POSTs) → exactly one event row (unique index holds, second gets
  `duplicate: true`); refresh after form POST → no resubmission surprise (redirect-after-post).
- Empty states: sources index with no sources renders an explicit empty state with a create link.
- Long inputs: 255-char source name accepted or cleanly rejected; payload just under the limit
  persists intact and byte-identical.

### Commits

1. `chore: scaffold laravel app with sqlite and database queue`
2. `chore: add env example and hook relay config`
3. `feat: add operator login and create user command`
4. `feat: add sources migration and model`
5. `feat: add source crud screens`
6. `feat: add provider signature verifiers`
7. `feat: add webhook events migration and model`
8. `feat: add ingest endpoint with signature verification`
9. `feat: deduplicate ingest by provider event id`
10. `feat: enforce ingest payload size limit`
11. `feat: add structured ingest logging`
12. `test: cover verifiers and ingest paths`

---

## Phase 2 — Destinations, routing, and at-least-once delivery with DLQ

**Goal**: Accepted events are forwarded to routed destinations with retries, backoff + jitter,
a dead-letter state with manual requeue, idempotency headers, and a complete per-attempt audit
trail. This phase delivers the remaining senior differentiators.

### Definition of done

- Migrations applied: `destinations`, `source_destination`, `deliveries`, `delivery_attempts`
  exactly as specified in `docs/architecture.md`.
- Destination CRUD screens (name, http/https URL, active flag, soft delete); source edit screen
  gains routed-destination checkboxes.
- Accepted ingest creates one `pending` delivery per active routed destination and dispatches
  `DeliverEvent` per delivery; a source with no routes accepts events with zero deliveries.
- `DeliverEvent` job: marks `delivering`, POSTs the raw payload with `X-Relay-Event-Id`,
  `X-Relay-Delivery-Id`, `X-Relay-Source`, original `Content-Type`, and the 10s timeout; does
  not follow redirects; records a `delivery_attempts` row for every attempt (status, headers,
  2048-byte body excerpt, duration — or `error` on connection failure/timeout).
- 2xx → `delivered`. Failure with budget left → `failed` + `next_attempt_at` per the backoff
  formula (`min(30 * 2^(n-1), 3600)` seconds, jitter factor 0.8–1.2), retried via job
  `tries`/`backoff`. Budget exhausted → `dead` via the job's `failed()` hook.
- DLQ page lists dead deliveries with per-row requeue and requeue-all (chunked); requeue resets
  status to `pending`, dispatches a fresh job with a fresh budget, and preserves all prior
  attempt rows and the lifetime `attempt_count`.
- Delivery detail page: status, destination, timing columns, full attempt table.
- Structured logs for `delivery.attempted`, `delivery.delivered`, `delivery.dead`,
  `delivery.requeued`.
- Pint clean; `Backoff` unit tests (bounds, cap, jitter range); feature tests with `Http::fake`
  covering delivered / retry-then-delivered / dead, header assertions (stable event id across
  attempts), routing fan-out, and requeue.

### Manual test checklist

- Create two destinations (one pointing at a local echo server, one at a dead port); route both
  to a source; run `php artisan queue:work` in a second terminal.
- Send a valid webhook → echo destination shows `delivered` with one attempt (status 200,
  duration, body excerpt); dead-port destination shows `failed` with `next_attempt_at` in the
  future, then eventually `dead` after the cap (temporarily set `DELIVERY_MAX_ATTEMPTS=2` to
  observe it quickly).
- Inspect the echo server's received request: raw body byte-identical, `X-Relay-Event-Id`
  present and equal to the ingest response's `event_id`.
- Open `/dlq` → the dead delivery is listed; requeue it while the echo server now answers →
  it delivers; the attempt history still shows the earlier failed attempts.
- Requeue-all with several dead deliveries → all return to `pending` and get re-attempted.
- Detach a destination from the source → new events create no delivery for it; old deliveries
  remain visible.

### Verification

- App + worker run cleanly; tests pass; Pint clean; logs show only expected `delivery.*` events.
- Unhappy paths: destination URL with an invalid scheme rejected at validation; destination
  returning 302 → counted as failure (redirect not followed); destination hanging past 10s →
  timeout recorded as an attempt with `error` set; killing the worker mid-delivery and
  restarting → delivery is retried, no event lost (at-least-once observed); double-clicking
  requeue → no duplicate job crash, delivery ends in a consistent state.
- Empty states: `/dlq` with no dead deliveries and delivery index with no rows both render
  explicit empty states.
- Long inputs: a destination responding with a 1MB body → excerpt capped at 2048 bytes, no
  storage error.

### Commits

1. `feat: add destinations migration model and crud`
2. `feat: add per source destination routing`
3. `feat: add deliveries and attempts migrations and models`
4. `feat: create pending deliveries on ingest`
5. `feat: add deliver event job with response capture`
6. `feat: add exponential backoff with jitter and dead state`
7. `feat: send idempotency headers on forwarded requests`
8. `feat: add dlq view with requeue and requeue all`
9. `feat: add delivery detail with attempt history`
10. `test: cover delivery retries dead lettering and requeue`

---

## Phase 3 — Dashboard browsing, replay, retention, and hardening

**Goal**: The operator can find anything (filters + search), replay events singly and in bulk,
and run the instance long-term (pruning, ingest throttling, polish).

### Definition of done

- Dashboard home: counts per delivery status, dead-letter count linking to `/dlq`, recent events.
- Events index: pagination plus combinable filters (`source_id`, `event_type`, `from`, `to`,
  `q` on provider event id) and row checkboxes for bulk replay.
- Event detail: metadata, stored headers, payload (escaped, scrollable), deliveries list,
  replay button.
- Deliveries index: pagination plus filters (`status`, `source_id`, `destination_id`).
- Single replay creates fresh `pending` deliveries to currently routed active destinations;
  bulk replay validates max 100 ids; both reuse the same event ULID downstream; logged as
  `event.replayed`.
- `WebhookEvent` is `Prunable` (older than `RETENTION_DAYS`, only terminal deliveries);
  `model:prune` scheduled daily; cascade removes deliveries/attempts; logged as
  `prune.completed` with counts.
- Ingest throttled per ingest key with the 429 envelope; login throttle verified.
- README finalized (real install/run/test replacing the TBD sections); `docs/testing.md`
  commands verified as written.
- Pint clean; feature tests for filters, replay (single/bulk/cap), pruning boundaries, and
  ingest throttling.

### Manual test checklist

- Seed a few sources/events; combine source + type + date filters → results correct, filters
  survive pagination; nonsense filter values → empty state, no 500.
- Search a known provider event id → the event appears; search an unknown id → empty state.
- Replay a delivered event → new pending deliveries appear and deliver; downstream sees the
  same `X-Relay-Event-Id` as the original.
- Select 3 events and bulk replay → deliveries created for all 3; try >100 via a forged form →
  validation error, nothing dispatched.
- Set `RETENTION_DAYS=0`, run `php artisan model:prune` → old terminal events and their
  deliveries/attempts are gone; an event with a `failed` delivery survives.
- Hammer one ingest URL past the throttle → 429 envelope; a different source's URL is
  unaffected.

### Verification

- App + worker + scheduler run cleanly; full suite passes; Pint clean; no unexpected log noise.
- Unhappy paths: replay on an event whose source now has zero routed destinations → friendly
  message, zero deliveries created, no crash; bulk replay form submitted with no selection →
  validation message; pruning during active deliveries → in-flight events untouched; browser
  refresh after replay POST → no duplicate replay (redirect-after-post).
- Empty states: fresh install dashboard home renders sensible zeros; every index has an
  explicit empty state.
- Long inputs: multi-hundred-KB payload renders scrollable without breaking layout; very long
  destination URLs and event types truncate with ellipsis in tables.
- Accessibility spot-check per `docs/design.md`: labels, focus states, contrast, keyboard-only
  pass through login → create source → inspect event → replay.

### Commits

1. `feat: add dashboard home with status counts`
2. `feat: add events index with filters and search`
3. `feat: add event detail with payload and deliveries`
4. `feat: add deliveries index with filters`
5. `feat: add single event replay`
6. `feat: add bulk replay from events index`
7. `feat: add retention pruning`
8. `feat: throttle ingest per source`
9. `docs: finalize readme and testing commands`
10. `test: cover filters replay pruning and throttling`

---

## Backlog

_(empty — move out-of-scope ideas here with a one-line rationale)_
