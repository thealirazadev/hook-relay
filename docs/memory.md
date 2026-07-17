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

## In progress

- Phase 3 next — dashboard browsing/filters, single + bulk replay, retention pruning, ingest
  throttle, README.

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
