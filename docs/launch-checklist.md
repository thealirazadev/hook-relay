# Launch Checklist — hook-relay

Work top to bottom before going to production. Nothing is checked until verified in the target
environment.

## Environment & configuration

- [ ] Production `.env` created from `.env.example` with real values (no dummies).
- [ ] `APP_KEY` generated for production (note: rotating it later invalidates stored encrypted
      signing secrets — back it up).
- [ ] `APP_DEBUG=false`, `APP_ENV=production`.
- [ ] `APP_URL` set to the real public host (ingest URLs shown in the dashboard build from it).
- [ ] `DB_*` points at production MySQL; credentials stored securely, not in the repo.
- [ ] `INGEST_MAX_BODY_KB`, `DELIVERY_TIMEOUT_SECONDS`, `DELIVERY_MAX_ATTEMPTS`,
      `RETENTION_DAYS` reviewed for this deployment.
- [ ] Config/route/view caches warmed (`config:cache`, `route:cache`, `view:cache`).

## Processes

- [ ] Queue worker running under a supervisor (e.g. systemd/supervisord: `php artisan
      queue:work --tries=1`) and restarting on failure/deploy.
- [ ] Cron entry for `php artisan schedule:run` every minute (drives pruning).
- [ ] Deploy procedure restarts the worker (`queue:restart`) so code changes take effect.

## Security

- [ ] No secrets committed; `.env` git-ignored; only `.env.example` (dummies) tracked.
- [ ] HTTPS enforced; HTTP redirects to HTTPS (providers should only be given https ingest URLs).
- [ ] Operator account created via `app:create-user`; no registration route reachable.
- [ ] Login throttle and ingest per-key throttle active; 429 envelope confirmed.
- [ ] Signature verification confirmed against one real webhook from each configured provider
      (send a test event from the provider's dashboard).
- [ ] Stored signing secrets confirmed encrypted at rest (inspect a `sources` row).
- [ ] Payload rendering spot-checked as escaped (send an event with an HTML/script body; view it).

## Reliability & observability

- [ ] Error tracking / log aggregation wired up and receiving events.
- [ ] Structured logs verified for `ingest.*`, `delivery.*`, `event.replayed`, `prune.completed`.
- [ ] Kill-the-worker test: stop the worker mid-delivery, restart, confirm the delivery completes
      (at-least-once verified in production conditions).
- [ ] A forced-failure destination reaches `dead` and appears in `/dlq`; requeue works.
- [ ] Database backups scheduled (covers events, deliveries, and the queue) and a restore tested
      at least once.
- [ ] Migrations run cleanly on production (`migrate --force`); rollback path understood.
- [ ] `model:prune` observed running via the scheduler with a sane `prune.completed` count.

## Pages & responses

- [ ] Ingest endpoint returns only JSON envelopes for every error case (404/401/405/413/429).
- [ ] Dashboard 404 and 419 pages render the branded layout; generic 500 shows no stack trace
      with `APP_DEBUG=false`.
- [ ] Every index page checked in the empty state and at 320px width.
- [ ] Flash messages and validation errors verified on a slow connection (server-rendered, no
      JS dependency).

## Quality gates

- [ ] `php artisan test` green in CI or on the production build.
- [ ] `./vendor/bin/pint --test` clean.
- [ ] `composer.lock` committed with pinned versions matching the deployed build.

## Project-specific

- [ ] Duplicate-ingest race verified under concurrency (two simultaneous identical POSTs → one
      event row; unique index holds on MySQL).
- [ ] Payload just under `INGEST_MAX_BODY_KB` ingests, stores, and forwards byte-identical on
      MySQL (LONGTEXT, no truncation).
- [ ] Backoff timing observed in production logs (delays roughly match the documented schedule).
- [ ] `deliveries.status` and `webhook_events` filter indexes confirmed used (EXPLAIN on the
      DLQ and events-index queries) with a realistic data volume.
- [ ] Retention window agreed with the owner before first prune runs in production.
