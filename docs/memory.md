# Project Memory — hook-relay

Running log of what is done, in progress, and decided. Update after every meaningful chunk of
work; log every non-obvious decision with its reason. Keep entries short and dated.

## Completed

- 2026-07-18 — Planning documentation created (README, PRD, architecture, api-contracts, rules,
  phases, design, testing, memory, launch-checklist, .env.example). No code yet; docs under
  owner review.

## In progress

- None — awaiting owner approval of the planning docs before Phase 1 starts.

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
