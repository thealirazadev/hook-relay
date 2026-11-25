# Design decisions

The engineering choices behind hook-relay, the reasons for them, and the bugs and edge cases that
shaped them. Each one is reflected in the code referenced in its section.

## 1. The ingest endpoint is stateless and authenticated only by the signature

`POST /ingest/{ingestKey}` lives in its own route file (`routes/ingest.php`) and is registered in
`bootstrap/app.php` outside the `web` middleware group, so it carries no session and no CSRF check.
Providers cannot hold a session or a CSRF token, and the request signature is the real proof of
origin. The ingest key is a 32-character random token, but it is treated as an address rather than
a secret: an unknown or inactive key returns 404, and a known key with a bad signature returns 401
with nothing persisted.

An exception renderer in `bootstrap/app.php` maps every exception on `ingest/*` (throttle, wrong
method, not found, anything unexpected) to the single JSON error envelope
`{ "error": { "code", "message" } }`, so a provider never receives an HTML error page.

## 2. One verifier class per provider, standard library only

Stripe, GitHub, Shopify and a generic `X-Signature` scheme each get a class behind the `Provider`
interface (`verify`, `eventId`, `eventType`), resolved by `ProviderResolver`. Four concrete schemes
justify the interface; nothing else in the app has one. Verification uses `hash_hmac` and
`hash_equals` rather than provider SDKs, which keeps the dependency list short and the comparison
constant-time.

The Stripe verifier enforces a 300-second timestamp tolerance to blunt replay of captured requests,
and accepts the header when any of several `v1` signatures matches, which is how Stripe signs during
a secret rotation. A header with a timestamp but no signature, or a non-numeric timestamp, is
rejected rather than partially trusted.

## 3. Deduplication by provider event id, with a body-hash fallback

Idempotency is a unique index on `(source_id, dedupe_key)`. The key is the provider's own event id
(Stripe `id`, `X-GitHub-Delivery`, `X-Shopify-Webhook-Id`, generic `X-Event-Id`) when present, and
`sha256:<hex of the raw body>` otherwise. This dedupes provider retries precisely when an id exists
and still catches byte-identical re-sends when it does not, without asking the sender for a key.

Edge case found in review: the id was written straight into the 191-character `dedupe_key` column,
so a generic source sending a longer id failed the insert on strict MySQL. The resulting 500 made the
provider retry forever. An over-long id is now hashed to `sha256:<hex>`, which is deterministic and
so still dedupes, and the stored `provider_event_id`, `event_type` and `content_type` are capped to
their 255-character columns. The columns were bounded in code instead of widened, so no migration
was needed.

## 4. A duplicate ingest returns 200, not 409

Providers treat any non-2xx response as a failed delivery and retry it. From the sender's point of
view a duplicate is a success, because the event is already stored, so the endpoint returns 200 with
`"duplicate": true` instead of a conflict status that would trigger a retry storm.

## 5. The event and its deliveries are written in one transaction

`IngestController` persists the event and calls `WebhookEvent::createDeliveries()` inside a single
`DB::transaction`, and `DeliverEvent` sets `afterCommit` so its job is queued only once that
transaction commits.

Bug this fixed: the event row used to commit before fan-out. If fan-out failed, the event was left
with no deliveries, and the provider's retry was then short-circuited as a duplicate, so the webhook
was silently lost. With both writes in one transaction a fan-out failure rolls the event back and
the retry re-ingests it. Dispatching after commit also means a worker never picks up a delivery whose
row is not yet visible.

The unique-index race between two identical concurrent POSTs is caught inside the transaction: the
losing insert throws, the controller re-queries the winner's row and returns it as a duplicate.
MySQL and SQLite do not abort a transaction on a duplicate-key error, so the re-query sees the
committed row. Postgres would abort it, and is not a supported target.

## 6. At-least-once delivery on the database queue

A delivery is retried until a 2xx is observed or the attempt cap is reached. A crash between a
successful POST and recording its result causes a re-send, never a lost event. Exactly-once is not
achievable across a network the relay does not control, so every forwarded request carries
`X-Relay-Event-Id` (the event ULID, unchanged across retries, requeues and replays) and
`X-Relay-Delivery-Id`, and consumers dedupe on the event id. Provider signature headers are stored
with the event but never forwarded.

The queue uses the `database` driver rather than Redis. Throughput is lower, but a self-hosted relay
handles hundreds of events per minute rather than thousands per second, and a single PHP plus
database box is much easier to run. Queue state also shares the database backup.

## 7. Retries use the job's own budget, and every attempt is recorded

`DeliverEvent` takes `tries` from the delivery's `max_attempts` snapshot and counts with
`$this->attempts()`. On failure it marks the delivery `failed`, sets `next_attempt_at`, and releases
itself with a delay of `min(30 * 2^(n-1), 3600)` seconds multiplied by a uniform factor in
`[0.8, 1.2]` (`App\Support\Backoff`), so destinations that fail together do not retry in lockstep.
When the budget is spent the delivery becomes `dead`; `failed()` is kept as a safety net for jobs the
queue kills.

Connection errors and timeouts are caught and returned as data, so an attempt row is always written
before the job retries. Redirects are not followed, and the stored response body is cut to 2048
bytes with `mb_strcut` so truncation never splits a UTF-8 character. A DLQ requeue resets the
delivery to `pending` and dispatches a fresh job with a fresh budget, while `attempt_count` and the
attempt rows keep the lifetime history. Delivery to a soft-deleted destination still works because
the job loads it `withTrashed`, which avoids a null-destination crash for in-flight deliveries.

## 8. Outbound requests are signed over the timestamp and the body

Each destination has its own generated `whsec_` secret, stored with the `encrypted` cast. Every
forwarded request carries `X-Relay-Timestamp` and
`X-Relay-Signature: sha256=<hex hmac_sha256(secret, "{timestamp}.{body}")>` (`DeliverySigner`).
Folding the timestamp into the HMAC authenticates it, so a consumer can reject stale deliveries and
a forged timestamp fails verification; signing the body alone would leave the timestamp forgeable.
It is also the same construction the inbound Stripe verifier uses.

Unlike a source secret, which the operator already has from the provider and which is never shown
again, a destination secret is generated by the relay, so the edit screen reveals it and a rotate
action shows the new one once. The column is nullable because encrypted text has no usable default;
the migration backfills every existing destination, soft-deleted ones included, so every delivery is
signable.

## 9. Replay and requeue share one fan-out path and use current routing

Ingest, single replay and bulk replay all call `WebhookEvent::createDeliveries()`, and DLQ requeue
goes through `Delivery::requeue()`, so delivery creation has one implementation. A replay reuses the
existing event row (same ULID, so downstream dedupe holds) and fans out to the destinations routed
to the source now, not to a snapshot. There is deliberately no unique key on
`(webhook_event_id, destination_id)`, because a replay is meant to add deliveries, for example to a
destination that was fixed or added after the original ingest. Bulk replay is capped at 100 events
per request by `BulkReplayRequest`.

## 10. Retention pruning skips anything still in flight

`WebhookEvent` uses Laravel's `Prunable` trait, and `model:prune` runs daily from the scheduler. An
event is prunable only when it is older than `RETENTION_DAYS` and none of its deliveries is
`pending`, `delivering` or `failed`. Deliveries and attempts are removed by the foreign-key cascade.
Pruning trades old audit history for bounded storage; the window is operator-configured.

## 11. Configuration stays at four values

Only `INGEST_MAX_BODY_KB`, `DELIVERY_TIMEOUT_SECONDS`, `DELIVERY_MAX_ATTEMPTS` and `RETENTION_DAYS`
are env-backed, read once in `config/hook_relay.php`. The per-key ingest throttle is fixed at 60
requests per minute in `AppServiceProvider` rather than exposed as a fifth setting; it exists to stop
a runaway provider loop from flooding the database, and a deployment that needs a different ceiling
changes it there.

## 12. A server-rendered dashboard with defensive filters

The dashboard is Blade plus one static stylesheet, with no Node build and no JavaScript.
Destructive actions confirm through a `<details>` disclosure holding an inline form instead of a
`confirm()` dialog. Stored payloads, headers and response excerpts are attacker-controlled, so they
are always rendered with Blade escaping. Request headers matching a denylist (authorization, cookies,
proxy credentials) are stripped by `HeaderFilter` before an event is stored.

Index filters are sanitized inline (integer casts, a date parser that returns null on bad input,
parameter-bound search) rather than validated by a Form Request, because failed validation on a GET
filter redirects back to the same URL and can loop. Nonsense filter values produce an empty result
instead of an error. Bug found in review: an array-valued parameter such as `?q[]=x` reached string
handling in the query and the view and returned a 500; filters are now read only as strings and
anything else is ignored. The event model is named `WebhookEvent` to avoid clashing with Laravel's
`Event` facade.
