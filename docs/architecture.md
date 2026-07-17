# Architecture — hook-relay

## App flow

```
Provider (Stripe / GitHub / Shopify / generic)
        │  POST /ingest/{ingest_key}  (raw body + provider signature header)
        ▼
Ingest pipeline
        ├─ resolve Source by ingest_key      → unknown key: 404, nothing stored
        ├─ enforce payload size limit        → too large: 413, nothing stored
        ├─ verify signature (provider class) → invalid: 401, nothing stored, logged
        ├─ derive dedupe key                 → provider event id, else sha256(body)
        ├─ duplicate? (source_id+dedupe_key) → 200 {duplicate: true}, nothing new
        └─ persist WebhookEvent (raw payload, filtered headers, event type)
                │
                ▼
        create one Delivery per Destination routed to the Source (state: pending)
        dispatch DeliverEvent job per delivery (database queue)
                │
                ▼
Queue worker (php artisan queue:work)
        ├─ state: delivering
        ├─ POST payload to destination URL
        │     headers: X-Relay-Event-Id, X-Relay-Delivery-Id, X-Relay-Source,
        │              original Content-Type; 10s timeout; redirects not followed
        ├─ record DeliveryAttempt (status, headers, body excerpt, duration | error)
        ├─ 2xx        → state: delivered (terminal)
        ├─ non-2xx / timeout / connection error
        │     ├─ attempts < max → state: failed, next_attempt_at set,
        │     │                    job retries after backoff + jitter
        │     └─ attempts = max → state: dead (terminal until manual requeue)
        ▼
Dashboard (Blade, session auth)
        ├─ sources / destinations CRUD + routing
        ├─ events index (filter: source, type, date, id search) + detail + replay
        ├─ deliveries index (filter: status, source, destination) + attempt history
        └─ DLQ view (state = dead) with single / bulk requeue → back to pending
```

## Request lifecycle

**Ingest** (`routes/ingest.php`, minimal middleware — no session, no CSRF):
1. Route `POST /ingest/{ingestKey}` matched; throttle per ingest key applies.
2. `IngestController` resolves the source, checks size, and delegates verification and
   metadata extraction to the source's provider class.
3. On acceptance it persists the event, creates deliveries, dispatches jobs, and returns the
   JSON envelope. All failures return the single JSON error envelope.

**Dashboard** (`routes/web.php`, full web middleware):
1. `auth` middleware guards everything except `GET|POST /login`.
2. Form Requests validate all input; controllers stay thin; Blade renders server-side.
3. Mutations are plain form POSTs with CSRF; feedback via session flash + redirect.

## Delivery state machine

States live on `deliveries.status`. Transitions are the only allowed ones:

| From | To | Trigger |
|---|---|---|
| — | `pending` | Event accepted, replay, or manual requeue |
| `pending` | `delivering` | Worker picks up the job |
| `delivering` | `delivered` | Destination returned 2xx (terminal) |
| `delivering` | `failed` | Non-2xx, timeout, or connection error with attempts remaining; `next_attempt_at` set |
| `failed` | `delivering` | Queue retries the job at `next_attempt_at` |
| `delivering` | `dead` | Attempt failed and the attempt cap is reached (terminal) |
| `dead` | `pending` | Manual requeue from the DLQ view (fresh job, fresh attempt budget) |

**At-least-once semantics.** A delivery is retried until a 2xx is observed or the cap is hit; a
crash between a successful POST and recording the result causes a re-send, never a lost event.
Downstream consumers must dedupe on `X-Relay-Event-Id` (stable across retries and replays).

**Backoff schedule.** Retry delay for attempt n (n ≥ 1 failed attempts so far) is
`min(30 * 2^(n-1), 3600)` seconds, then multiplied by a uniform random factor in `[0.8, 1.2]`
(jitter, so synchronized failures do not retry in lockstep). With the default cap of 8 attempts:
~30s, 1m, 2m, 4m, 8m, 16m, 32m after the first try — roughly one hour of cover, then `dead`.
Implemented with the framework's job retry mechanics: `tries` = `DELIVERY_MAX_ATTEMPTS`,
`backoff()` computes the delay, `failed()` marks the delivery `dead`. A manual requeue dispatches
a brand-new job with a fresh budget; `deliveries.attempt_count` keeps the lifetime total and
attempt rows are never deleted, so the audit trail survives requeues.

## Proposed folder / file tree

```
app/
├── Console/Commands/
│   └── CreateOperatorCommand.php          # app:create-user {email} (prompts for password)
├── Http/
│   ├── Controllers/
│   │   ├── IngestController.php           # POST /ingest/{ingestKey}
│   │   ├── Auth/LoginController.php       # show, login, logout
│   │   ├── DashboardController.php        # home: counts + recent activity
│   │   ├── SourceController.php           # CRUD + destination routing checkboxes
│   │   ├── DestinationController.php      # CRUD
│   │   ├── EventController.php            # index, show, replay, bulkReplay
│   │   ├── DeliveryController.php         # index, show
│   │   └── DlqController.php              # index, requeue, requeueAll
│   ├── Requests/
│   │   ├── LoginRequest.php
│   │   ├── StoreSourceRequest.php
│   │   ├── UpdateSourceRequest.php
│   │   ├── StoreDestinationRequest.php
│   │   ├── UpdateDestinationRequest.php
│   │   └── BulkReplayRequest.php          # event ids, max 100 per request
│   └── Middleware/                        # framework defaults; no custom middleware
├── Jobs/
│   └── DeliverEvent.php                   # one job per delivery; retries via tries/backoff
├── Models/
│   ├── User.php
│   ├── Source.php
│   ├── Destination.php
│   ├── WebhookEvent.php                   # named to avoid clashing with the Event facade
│   ├── Delivery.php
│   └── DeliveryAttempt.php
└── Support/
    ├── Providers/
    │   ├── Provider.php                   # interface: verify, eventId, eventType
    │   ├── StripeProvider.php             # Stripe-Signature t/v1, 300s tolerance
    │   ├── GithubProvider.php             # X-Hub-Signature-256
    │   ├── ShopifyProvider.php            # X-Shopify-Hmac-Sha256 (base64)
    │   ├── GenericProvider.php            # X-Signature: sha256=<hex>
    │   └── ProviderResolver.php           # provider enum -> instance
    ├── Backoff.php                        # delay-with-jitter calculation
    └── HeaderFilter.php                   # strips denylisted headers before storage

config/
└── hook_relay.php                         # size limit, timeout, attempts, retention (env-backed)

database/
├── migrations/
│   ├── 0001_01_01_000000_create_users_table.php
│   ├── 0001_01_01_000001_create_cache_table.php
│   ├── 0001_01_01_000002_create_jobs_table.php        # database queue + failed_jobs
│   ├── 2026_07_01_000100_create_sources_table.php
│   ├── 2026_07_01_000200_create_webhook_events_table.php
│   ├── 2026_07_01_000300_create_destinations_table.php
│   ├── 2026_07_01_000400_create_source_destination_table.php
│   └── 2026_07_01_000500_create_deliveries_and_attempts_tables.php
├── factories/                             # User, Source, Destination, WebhookEvent,
│   └── ...                                # Delivery, DeliveryAttempt
└── seeders/DatabaseSeeder.php

public/css/app.css                         # the only stylesheet; no Node build

resources/views/
├── layouts/app.blade.php                  # nav, flash messages, footer
├── auth/login.blade.php
├── dashboard.blade.php
├── sources/{index,create,edit}.blade.php
├── destinations/{index,create,edit}.blade.php
├── events/{index,show}.blade.php
├── deliveries/{index,show}.blade.php
└── dlq/index.blade.php

routes/
├── web.php                                # login + dashboard (auth middleware)
└── ingest.php                             # POST /ingest/{ingestKey}; registered in
                                           # bootstrap/app.php without session/CSRF middleware

tests/
├── Feature/
│   ├── AuthTest.php
│   ├── SourceCrudTest.php
│   ├── DestinationCrudTest.php
│   ├── IngestTest.php                     # per-provider accept/reject, dedupe, 404/413
│   ├── DeliveryJobTest.php                # Http::fake success/failure/retry/dead
│   ├── DlqRequeueTest.php
│   ├── ReplayTest.php
│   ├── DashboardFilterTest.php
│   └── PruneTest.php
└── Unit/
    ├── StripeProviderTest.php
    ├── GithubProviderTest.php
    ├── ShopifyProviderTest.php
    ├── GenericProviderTest.php
    ├── BackoffTest.php
    └── HeaderFilterTest.php
```

## Tech stack with rationale

- **Laravel 11.x (PHP 8.2+)** — Routing, validation, Eloquent, queued jobs with retry/backoff
  built in, scheduler for pruning, first-class testing (`Http::fake`, `Queue::fake`). Matches the
  sibling `laravel-shortlink` project's stack and tooling. Exact versions are pinned at install
  time and `composer.lock` is committed.
- **Database queue driver** — The queue is the heart of the product, and the `database` driver
  keeps the whole deployment a single PHP + database box. Trade-off: lower throughput and
  DB-polling overhead versus Redis; acceptable because a self-hosted relay handles hundreds of
  events per minute, not thousands per second, and losing the Redis dependency makes self-hosting
  materially easier. Jobs and failed jobs live in `jobs`/`failed_jobs`, so queue state shares the
  database backup story.
- **SQLite (dev) / MySQL 8.x (prod)** — SQLite gives zero-setup local dev and in-memory tests;
  MySQL is the deployed target for concurrent worker + web writes. Trade-off: two engines mean
  avoiding driver-specific SQL (no raw `DATE()`/`strftime()`), which the codebase enforces by
  doing date bucketing and JSON handling in PHP.
- **Blade + one static CSS file** — Server-rendered pages, plain form POSTs, no Node toolchain,
  no build step. Boring is a feature for an ops dashboard; see `docs/design.md`.
- **Laravel HTTP client (bundled Guzzle)** — Outbound delivery POSTs with timeouts and
  `Http::fake` testability; no new dependency.
- **Pest on PHPUnit** — Concise feature/unit tests, same engine as `artisan test`.
- **Laravel Pint** — Zero-config PSR-12 formatting.

No other runtime dependencies. Signature verification is `hash_hmac` + `hash_equals` from the
standard library; no provider SDKs.

## Data model

Tables named here are the contract; the coding agent must not rename them.

### users
Operator accounts (created via `app:create-user`; no registration).
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, unique | |
| password | string (hashed) | |
| timestamps | | |

### sources
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | display name, e.g. "Stripe production" |
| provider | string enum | `stripe` \| `github` \| `shopify` \| `generic` |
| ingest_key | string(64), unique | random 32-char URL-safe token; forms the ingest URL |
| signing_secret | text | encrypted cast; the provider's webhook signing secret |
| active | boolean, default true | inactive sources reject ingest with 404 |
| timestamps + deleted_at | | soft delete keeps history browsable |

Relationships: `hasMany(WebhookEvent)`, `belongsToMany(Destination)`.

### destinations
| Field | Type | Notes |
|---|---|---|
| id | bigint PK | |
| name | string | |
| url | string(2048) | validated http/https |
| active | boolean, default true | inactive destinations get no new deliveries |
| timestamps + deleted_at | | soft delete keeps attempt history intact |

Relationships: `belongsToMany(Source)`, `hasMany(Delivery)`.

### source_destination
| Field | Type | Notes |
|---|---|---|
| source_id | bigint FK → sources.id | composite unique with destination_id |
| destination_id | bigint FK → destinations.id | |

### webhook_events
| Field | Type | Notes |
|---|---|---|
| id | ulid PK | sortable, safe to expose; sent as `X-Relay-Event-Id` |
| source_id | bigint FK → sources.id | indexed |
| provider_event_id | string, nullable | Stripe `id` / `X-GitHub-Delivery` / `X-Shopify-Webhook-Id` / `X-Event-Id` |
| dedupe_key | string(191) | provider event id, else `sha256:<hex of body>` |
| event_type | string, nullable | Stripe `type` / `X-GitHub-Event` / `X-Shopify-Topic`; null for generic |
| headers | json | request headers minus denylist (authorization, cookie, proxy headers) |
| payload | longText | raw request body, verbatim; capped by `INGEST_MAX_BODY_KB` |
| content_type | string, nullable | echoed on forwarded requests |
| received_at | datetime | |
| created_at | datetime | no updated_at; events are immutable |

Indexes: unique `(source_id, dedupe_key)` — the idempotency guarantee; `(source_id, received_at)`
and `event_type` for dashboard filters.

### deliveries
| Field | Type | Notes |
|---|---|---|
| id | ulid PK | sent as `X-Relay-Delivery-Id` |
| webhook_event_id | ulid FK → webhook_events.id | indexed, cascade on delete |
| destination_id | bigint FK → destinations.id | indexed |
| status | string enum | `pending` \| `delivering` \| `delivered` \| `failed` \| `dead` |
| attempt_count | unsigned int, default 0 | lifetime total, survives requeue |
| max_attempts | unsigned int | snapshot of config at dispatch time |
| next_attempt_at | datetime, nullable | set when `failed`; shown in dashboard |
| last_attempted_at | datetime, nullable | |
| timestamps | | |

Indexes: `status` (DLQ view), `(destination_id, status)`.
No unique key on `(webhook_event_id, destination_id)`: replays intentionally create additional
deliveries for the same pair.

### delivery_attempts
| Field | Type | Notes |
|---|---|---|
| id | ulid PK | |
| delivery_id | ulid FK → deliveries.id | indexed, cascade on delete |
| attempt_number | unsigned int | monotonic per delivery (lifetime, across requeues) |
| response_status | smallint, nullable | null when the request never completed |
| response_headers | json, nullable | |
| response_body_excerpt | text, nullable | first 2048 bytes, UTF-8-safe truncation |
| error | string, nullable | connection/timeout error message when status is null |
| duration_ms | unsigned int | wall time of the attempt |
| created_at | datetime | immutable rows; no updated_at |

Framework tables: `sessions`, `cache`, `jobs`, `job_batches`, `failed_jobs` (database drivers for
session, cache, and queue).

## Where state lives

- **Database (single source of truth)** — sources, destinations, routing, events, deliveries,
  attempts, queued jobs, sessions, cache. One backup covers everything.
- **Session** — operator auth state, database-backed. The ingest route is stateless.
- **Queue state** — `jobs` table rows plus each job's retry bookkeeping; delivery status columns
  are the authoritative view, written by the job at every transition.
- **Secrets/config** — `.env` only. Source signing secrets are the one secret class stored in the
  database, encrypted at rest with `APP_KEY` via Eloquent's `encrypted` cast.
- **Nothing client-side** beyond the session cookie; no JS state, no local storage.

## Retention pruning

`WebhookEvent` uses the `Prunable` trait: prunable when `received_at` is older than
`RETENTION_DAYS` and no delivery of the event is `pending`, `delivering`, or `failed`.
`model:prune` runs daily via the scheduler; deliveries and attempts go with their event via FK
cascade. Trade-off: pruning loses old audit history — acceptable for a self-hosted tool where the
retention window is operator-configured; set `RETENTION_DAYS` higher if compliance needs it.

## External dependencies and required env vars

External runtime services: none. hook-relay receives HTTP and sends HTTP to operator-configured
destinations; it never calls provider APIs. Production needs MySQL, a queue worker process
(`php artisan queue:work`), and a cron entry for `php artisan schedule:run`.

Required environment variables (see `.env.example`):

| Variable | Purpose |
|---|---|
| `APP_KEY` | Encryption key; also encrypts stored signing secrets. Must be generated. |
| `APP_URL` | Base host; ingest URLs shown in the dashboard are built from it. |
| `APP_DEBUG` | Must be false outside local. |
| `DB_CONNECTION` / `DB_*` | `sqlite` for dev, `mysql` + credentials for prod. |
| `SESSION_DRIVER` | `database`. |
| `QUEUE_CONNECTION` | `database`. |
| `CACHE_STORE` | `database` (also backs rate-limit counters). |
| `INGEST_MAX_BODY_KB` | Max accepted payload size in KB (default 512); larger bodies get 413. |
| `DELIVERY_TIMEOUT_SECONDS` | Per-attempt outbound HTTP timeout (default 10). |
| `DELIVERY_MAX_ATTEMPTS` | Attempt cap before `dead` (default 8). |
| `RETENTION_DAYS` | Event retention window for pruning (default 30). |

The four `HOOK_RELAY`-specific values are read once in `config/hook_relay.php`; code reads config,
never `env()` directly.
