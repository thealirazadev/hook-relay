# Engineering Rules — hook-relay

These rules are binding for every change in this repository.

## Conventions

- **Framework patterns**: Laravel idioms throughout. Controllers stay thin; validation lives in
  Form Requests; delivery logic lives in the `DeliverEvent` job; provider-specific verification
  and metadata extraction live in `app/Support/Providers`. No query logic in routes, middleware,
  or Blade.
- **Preferred libraries**: Only what the stack already includes — Eloquent, the bundled HTTP
  client, the database queue, Pest, Pint. Signature verification uses `hash_hmac`/`hash_equals`
  from the standard library. Do not add provider SDKs, DTO libraries, repositories, admin panels
  (Filament/Nova), or a CSS framework.
- **What to avoid**: No raw SQL with user input, no driver-specific SQL (the code must run on both
  SQLite and MySQL — do date bucketing in PHP), no logic in Blade beyond display and loops, no
  JavaScript unless a phase explicitly calls for it, no `env()` calls outside `config/`.
- **Naming (PSR-12 + Laravel)**: Controllers `PascalCaseController`; models singular `PascalCase`
  (the event model is `WebhookEvent`, table `webhook_events` — do not rename to `Event`); Form
  Requests `VerbNounRequest`; jobs imperative `PascalCase` (`DeliverEvent`); tables plural
  snake_case; columns snake_case; routes lowercase. Pint enforces style.
- **Commit format**: Conventional Commits, short imperative subject, e.g.
  `feat: add stripe signature verifier`, `fix: cap response body excerpt at 2048 bytes`.
- **ONE COMMIT PER FEATURE**: Each feature or task is exactly one commit; never batch features,
  never fragment one small feature. The commit lists in `docs/phases.md` are the intended order.
- **Pin exact dependency versions**: Exact versions in `composer.json`, `composer.lock`
  committed. Any dependency change is its own commit and needs approval first (see Boundaries).
  No blanket upgrades or pulling latest without approval.
- **DB migration rule**: Every schema change goes through a migration file. Never edit a migration
  that has been applied/committed — add a new one. Never change schema by hand. Model
  `$fillable`/`$casts` changes ship in the same commit as the migration introducing the columns.
- **State transitions**: Delivery `status` may only change along the transitions listed in
  `docs/architecture.md`. Never set a status ad hoc from a controller; requeue and replay go
  through the same code path that creates pending deliveries.

## Error handling & logging

- **Every external/fallible call handles failure**: The outbound delivery POST (timeout,
  connection refused, DNS), event/attempt persistence, and job dispatch all handle failure
  explicitly. An attempt that throws still records a `delivery_attempts` row with the `error`
  field set before the job retries.
- **Ingest never 500s on bad input**: Unknown key, bad signature, oversize body, and wrong method
  each map to their documented status and envelope. Unparseable JSON is not an error — payloads
  are opaque bytes.
- **Friendly user errors vs detailed logs**: Ingest callers get the short envelope; the dashboard
  gets flash messages and field errors. Full context (exception class, source id, delivery id —
  never secrets, never full payloads) goes to logs only.
- **No stack traces to users**: `APP_DEBUG=false` outside local. No framework debug pages in any
  response.
- **One consistent JSON error format** (see `docs/api-contracts.md`):
  `{ "error": { "code": "...", "message": "..." } }` for every ingest error, no exceptions.
- **Structured logging from day one**: Context arrays with consistent dotted event keys:
  `ingest.accepted`, `ingest.duplicate`, `ingest.signature_failed`, `ingest.unknown_source`,
  `ingest.payload_too_large`, `delivery.attempted`, `delivery.delivered`, `delivery.dead`,
  `delivery.requeued`, `event.replayed`, `auth.login_failed`, `prune.completed`. Example:
  `Log::warning('ingest.signature_failed', ['source_id' => $source->id, 'reason' => 'hmac_mismatch'])`.

## Security

- **No hardcoded secrets**: All config secrets in `.env` (git-ignored); `.env.example` carries
  dummies only. Source signing secrets live in the database encrypted via the `encrypted` cast
  (never plaintext at rest, never logged, never fully re-displayed after save).
- **Ingest auth is the signature**: Verification uses constant-time comparison; Stripe timestamps
  are checked against a 300-second tolerance to blunt replay of captured requests. A source's
  ingest key is a 32-char random token — unguessable, but treated as an address, not a secret:
  the signature is the gate.
- **Dashboard auth**: Session login (`auth` middleware) on every dashboard route; login throttled
  by IP; logout invalidates and regenerates the session. No registration route exists. CSRF on
  all dashboard POSTs; the stateless ingest route is explicitly outside CSRF and session
  middleware.
- **Validate all input server-side** via Form Requests: source names/providers/secrets,
  destination URLs (`http`/`https` only, max 2048 chars), filter parameters, bulk-replay id lists
  (max 100). Never trust the client.
- **Rendering user/payload data**: Stored payloads, headers, and response excerpts are rendered
  with Blade's escaping (`{{ }}`), never `{!! !!}` — webhook bodies are attacker-controlled
  content and must never execute in the dashboard (stored XSS).
- **Queries**: Eloquent/parameter binding only.
- **Outbound requests**: Only to operator-entered `http`/`https` URLs; redirects are not
  followed; per-attempt timeout enforced. SSRF exposure is accepted as-is because only the
  authenticated operator can configure destinations on their own instance — documented trade-off,
  not an oversight.
- **Rate limiting**: Login throttled by IP; ingest throttled per ingest key (protects the DB from
  a runaway provider loop) with the standard `429` envelope.
- **Protected routes** (full table in `docs/api-contracts.md`): ingest and `/up` are public;
  everything else requires the operator session.

## Simplicity / YAGNI-KISS

- Build only what the current phase requires. No speculative features, no config toggles beyond
  the four documented `HOOK_RELAY` env values, no premature caching, no queue priorities.
- Prefer the framework's built-in mechanism (job `tries`/`backoff`, `Prunable`, named rate
  limiters) over hand-rolled infrastructure.
- No abstraction until three real use cases exist. The `Provider` interface is justified by four
  concrete implementations; nothing else warrants an interface in v1.
- No new wrapper classes, factories, managers, or utils files without owner approval first.
- Before submitting, self-review: can this be done in fewer lines without hurting readability?
  If yes, rewrite first. If a solution exceeds ~150 lines, pause and justify it.

## Code style

- Comments are sparse and explain why, not what. Concise docstrings on non-obvious methods only
  (the backoff formula and the Stripe header parsing deserve one; getters do not).
- No emoji anywhere in code, comments, commits, or docs.
- No mention of AI, assistants, or authorship attribution anywhere — no "Generated by",
  no `Co-authored-by` lines in commits.
- Conventional Commits, imperative subject, one per feature.
- Let Pint own formatting; do not hand-format against it.

## Boundaries — never do without asking the owner first

- **No wholesale delete/rewrite** of working files. Targeted edits; flag destructive changes first.
- **Do not change `docs/PRD.md` or `docs/architecture.md`** without flagging the change and its
  reason and getting sign-off — they are the source of truth.
- **No new dependency without approval.** Propose what, why, version, and size, then wait.
- **Ask when ambiguous** rather than guessing at product behavior.
- **Stop after two failed fix attempts** on the same problem; report what was tried and the
  current state instead of thrashing.
- **Scope discipline**: any mid-phase request not in `docs/PRD.md` gets classified with the owner
  as (a) current phase, (b) a new phase, or (c) Backlog in `docs/phases.md`. Never silently
  absorb scope.
