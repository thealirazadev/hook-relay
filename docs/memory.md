# Project Memory — hook-relay

Running log of what is done, in progress, and decided. Update after every meaningful chunk of
work; log every non-obvious decision with its reason. Keep entries short and dated.

## Completed

- 2026-07-18 — Planning documentation created (README, PRD, architecture, api-contracts, rules,
  phases, design, testing, memory, launch-checklist, .env.example). No code yet; docs under
  owner review.
- 2026-07-18 — Phase 1 complete. Scaffolded Laravel 11.55.0 (SQLite dev, database queue/session/
  cache), operator auth + `app:create-user`, source CRUD (encrypted secret, soft delete), four
  provider signature verifiers + resolver, header filter, and the verified idempotent ingest
  endpoint (404/413/401/dedupe/200). 66 tests pass, Pint clean. Verified live over HTTP with a
  real server: guarded redirect, login, source create, ingest 200/duplicate/401/404/405 with the
  JSON envelopes and one event row for a double-send.

- 2026-07-18 — Phase 2 complete. Destinations CRUD, per-source routing (pivot `source_destination`),
  deliveries + delivery_attempts, ingest fan-out to pending deliveries, the `DeliverEvent` job
  (response capture, exponential backoff + jitter, dead-lettering, idempotency headers), the DLQ
  view with single + bulk requeue, and delivery detail. 104 tests pass, Pint clean. Verified live
  with a real queue worker: delivery to an echo server carried the correct `X-Relay-*` headers and
  a byte-identical body; a dead port produced a captured connection error and dead-lettered after
  the cap; requeue recovered it to delivered with the prior attempt rows preserved.

- 2026-07-18 — Phase 3 complete. Dashboard home with status counts, events index (combinable
  filters + provider-id search) and detail, deliveries index with filters, single + bulk replay
  (max 100, same event ULID reused, no duplicate event rows), retention pruning via `Prunable` +
  daily `model:prune` (cascades deliveries/attempts, skips events with non-terminal deliveries),
  per-source ingest throttle (60/min per key, 429 `rate_limited` envelope), branded 404/419/500
  pages, and a finalized README. 138 tests pass, Pint clean. Verified live: every browse page
  loads, a replay flows through the real worker reusing the same `X-Relay-Event-Id`, and prune
  removes terminal events. Logs show only the documented event keys, zero ERROR lines.

- 2026-07-22 — Repository housekeeping after publishing: added the MIT `LICENSE` at the root
  (matching the `license` field already in composer.json) and `.github/workflows/ci.yml`, which
  runs the same two commands the project passes locally — `./vendor/bin/pint --test` then
  `php artisan test` — on PHP 8.2 for pushes and pull requests to `main`. No source or test
  changes.

- 2026-07-23 — Added README screenshots. `database/seeders/DemoSeeder.php` seeds synthetic data
  (three sources: Stripe/GitHub/Shopify; three `example.com` destinations with routing; eight
  events with realistic types and provider ids; fourteen deliveries spanning pending, delivered,
  delivered-after-retry, failed-and-retrying, and dead; 29 attempt rows including a dead delivery
  with the full eight-attempt trail of 500/502/503 responses, a timeout, and a connection error).
  Deliveries/attempts are written directly with fixed states, so nothing dispatches to the queue.
  `scripts/capture-screenshots.mjs` drives Playwright (not a repo dependency) to shoot the events
  browse, deliveries browse, a dead delivery's detail (attempt audit trail), and the dead-letter
  queue into `docs/images/` at 1280-wide; PNGs are 44-151 KB. Referenced near the top of the README
  with descriptive alt text plus a reproduction section. Genuine captures of the running app — no
  application code changed. Same sandbox serving note as laravel-uptime: `php -S ... index.php`
  routes static assets through the front controller (CSS 302s), so captures used
  `php -S -t public <router>` where the router returns false for existing files; README documents
  the normal `php artisan serve` path. pint clean, 139 tests pass, CI green.

## Project status

- v1 complete: all three phases implemented, tested (138 passing), and verified live. Remaining
  work is deployment (see docs/launch-checklist.md), which needs a target environment.

## Decisions log

- 2026-07-18 — Stack fixed: Laravel 11.x / PHP 8.2+, Blade + one static CSS file (no Node),
  database queue driver (no Redis), SQLite dev / MySQL 8.x prod, Pest, Pint. Reason: single-box
  self-hosting with the fewest moving parts; matches the sibling laravel-shortlink conventions.
- 2026-07-18 — Event model named `WebhookEvent` (table `webhook_events`) to avoid colliding with
  Laravel's `Event` facade.
- 2026-07-18 — Delivery states fixed: pending, delivering, delivered, failed, dead. Retries via
  the queue job's own tries/backoff; manual requeue dispatches a fresh job with a fresh budget
  while `attempt_count` and attempt rows keep lifetime history.
- 2026-07-18 — Duplicate ingest returns 200 (not 409) because providers treat non-2xx as a
  failure and would retry forever.
- 2026-07-18 — Provider signature headers are stored on the event but never forwarded to
  destinations; downstream dedupes on `X-Relay-Event-Id`, which stays stable across retries,
  requeues, and replays.
- 2026-07-18 — Dependencies pinned to exact versions in composer.json (owner requirement);
  composer warns exact constraints are unusual but this is intentional. Laravel 11.55.0 is the
  latest 11.x patch; `composer audit` reported advisories but the network was unavailable to
  fetch details, so we ship the latest available patch.
- 2026-07-18 — Removed the default Vite/Tailwind/Node scaffolding; the dashboard is Blade plus a
  single static `public/css/app.css` with no build step, per docs/design.md.
- 2026-07-18 — Ingest route registered outside the `web` middleware group via the `then` callback
  in bootstrap/app.php (no session, no CSRF); a `withExceptions` renderer returns the JSON error
  envelope for any `ingest/*` request (405 method_not_allowed, 404 unknown_source, 500
  server_error) so the endpoint never emits HTML.
- 2026-07-18 — Destructive actions confirm via a no-JS `<details>` disclosure (inline confirm
  form), honoring the design rule against `confirm()`/JavaScript.
- 2026-07-18 — Mid-run the owner asked for granular commits; from the provider verifiers onward
  each structural unit (interface, each verifier, migration, model, endpoint stage) is its own
  commit, tests included alongside or as their own `test:` commits. Dedupe was kept in the ingest
  endpoint commit because a working persistence path needs the unique-index race handling.
- 2026-07-18 — Local run recipe: `php -S 127.0.0.1:PORT -t public public/index.php` via the `php`
  wrapper (which scopes LD_LIBRARY_PATH to PHP). `php artisan serve` does not work here because
  its spawned `php -S` grandchild loses LD_LIBRARY_PATH and cannot load the bundled libtidy.
- 2026-07-18 — Delivery retry uses the framework budget via `$this->attempts()` (fresh per job, so
  a requeue gets a fresh budget) with `tries` snapshotted from `delivery.max_attempts`; the job
  releases with the jittered backoff delay, marks `dead` when `attempts() >= max_attempts`, and
  keeps `failed()` as a safety net. Tested with `withFakeQueueInteractions()` so the cap and dead
  transition are exercised without a real queue or wall-clock.
- 2026-07-18 — Fan-out and requeue live on the models (`WebhookEvent::createDeliveries()`,
  `Delivery::requeue()`) so ingest, replay, and DLQ requeue share one delivery-creation path, per
  rules.md; no separate manager class was introduced.
- 2026-07-18 — Dashboard filter params are sanitized inline (integer casts, defensive Carbon date
  parsing, parameter-bound LIKE search) and output-escaped, rather than validated via a FormRequest,
  because FormRequest validation on GET filters redirects and can loop; nonsense values yield an
  empty state instead of an error, matching the phase checklist.
- 2026-07-18 — Ingest throttle fixed at 60 requests/minute per ingest key (not env-configurable,
  per the YAGNI rule limiting config to the four documented values); adjust the limit in
  AppServiceProvider if a deployment needs a different ceiling.
- 2026-07-22 — Security upgrade pass. Owner explicitly approved breaking majors, which unblocked
  the Laravel 11 → 12 move that the 2026-07-18 entry had deferred. GitHub Dependabot listed zero
  alerts for this repo (alerts are enabled, the scan simply had nothing recorded), so `composer
  audit` was the only true signal — it reported 6 advisories across 2 packages. Cleared all of
  them: guzzlehttp/guzzle 7.15.0 → 7.15.1 (3 medium: host-only cookie scope, unbounded response
  cookies, URI fragments in redirect Referer headers) and laravel/framework 11.55.0 → 12.64.0
  (1 high CRLF injection in the default email rule, fixed only in 12.60.0+; 1 medium temporary
  signed URL path confusion, fixed only in 12.61.1+ — neither has an 11.x patch, so the major was
  the only route). The 11 → 12 upgrade needed no application code and no config changes: the app
  uses none of the breaking surfaces (no `HasUuids`, no `Concurrency::run`, no `image` validation
  rule, no `Schema::getTables()`, no `mergeIfMissing`, and `config/filesystems.php` already
  defines the `local` disk explicitly with the 12.x root and `serve`). Carbon was already on 3.x.
  Laravel 12 still requires PHP ^8.2, so the CI matrix stays on 8.2. Dev tooling was already at
  Laravel-12-compatible versions and was left untouched (pest 3.8.7 allows ^12.9.2, collision
  8.9.5, phpunit 11.5.56 — the upgrade guide asks for exactly these lines). 139 tests still pass,
  pint clean, `composer audit` reports zero advisories. laravel/tinker 3.0.2 and laravel/sail
  1.64.0 exist but are non-security bumps with no advisory against the pins, so both stay put.
