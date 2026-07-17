# Product Requirements — hook-relay

## What we're building

A self-hosted inbound webhook gateway. An operator registers a source (Stripe, GitHub, Shopify, or
a generic HMAC sender) and receives a unique ingest URL to paste into that provider's webhook
settings. Incoming requests are signature-verified per provider scheme, persisted as events with
their raw payload, deduplicated by provider event id, and forwarded to one or more configured
destination URLs by a queued worker with exponential-backoff retries, a dead-letter state, and a
recorded attempt history (status, headers, body excerpt, duration). A Blade dashboard lets the
operator manage sources and destinations, browse and filter events and deliveries, inspect the
dead-letter queue, requeue dead deliveries, and replay events singly or in bulk.

## Target user

A developer or small team running their own infrastructure who wants reliable webhook intake
without a hosted service: one place to receive provider webhooks, proof of what arrived, guaranteed
forwarding to internal consumers even when those consumers are briefly down, and a way to replay
history after a consumer bug. Single-operator, self-hosted; not a SaaS.

## Core features (prioritized)

1. **Operator authentication** — Session-based login for the dashboard. No public registration;
   the operator account is created with an artisan command. Everything except the ingest endpoint
   and the login page requires an authenticated session.

2. **Source CRUD** — Create, list, edit, and delete sources. Each source has a name, a provider
   (`stripe`, `github`, `shopify`, `generic`), a signing secret (stored encrypted), and a
   generated unique ingest key that forms its ingest URL (`POST /ingest/{ingest_key}`).

3. **Verified ingest with idempotent persistence** — The ingest endpoint verifies the request
   signature using the source's provider scheme (Stripe `Stripe-Signature` with timestamp
   tolerance, GitHub `X-Hub-Signature-256`, Shopify `X-Shopify-Hmac-Sha256`, generic
   `X-Signature`), enforces a payload size limit, persists the raw payload and filtered headers as
   a `webhook_events` row, and deduplicates by provider event id (payload-hash fallback): a
   provider retry of the same event returns 200 without creating a second event or new deliveries.

4. **Destination CRUD with per-source routing** — Create, list, edit, and delete destination URLs,
   and attach destinations to sources. Each accepted event fans out to one delivery per
   destination routed to its source.

5. **Queued delivery with at-least-once semantics** — A queued job POSTs the original payload to
   each destination, records every attempt (response status, headers, body excerpt, duration, or
   the connection error), and retries failures on an exponential backoff schedule with jitter up
   to a max-attempts cap, after which the delivery is marked `dead`. Every forwarded request
   carries idempotency headers (`X-Relay-Event-Id`, `X-Relay-Delivery-Id`) so downstream
   consumers can dedupe. Delivery states: `pending`, `delivering`, `delivered`, `failed`, `dead`.

6. **Dashboard: browse, filter, inspect** — Events index filterable by source, event type, date
   range, and provider event id search; event detail with payload, headers, and its deliveries;
   deliveries index filterable by status, source, and destination; delivery detail with the full
   attempt history; a dead-letter view listing `dead` deliveries with single and bulk requeue.

7. **Replay** — Replay a single event from its detail page, or bulk-replay selected events from
   the filtered events index. A replay creates fresh deliveries to the source's currently routed
   destinations, reusing the stable event id header so downstream dedupe still works.

8. **Retention pruning** — A scheduled command prunes events (and their deliveries/attempts) older
   than a configured retention window, skipping events that still have non-terminal deliveries.

## Non-goals

- Outbound webhook subscription management for your own product — this is an inbound relay only.
- Multi-tenant SaaS: no orgs, roles, per-user scoping, or public sign-up.
- Payload transformation, filtering rules, or any routing/filtering DSL — events are forwarded
  verbatim to every routed destination.
- Editing payloads before replay; replays send the original payload unchanged.
- Calling provider APIs (no Stripe/GitHub/Shopify API clients; hook-relay only receives).
- Destination authentication beyond the URL itself (no OAuth, no per-destination signing in v1).
- Alerting/notifications on dead letters (dashboard visibility only in v1).
- Horizontal scaling concerns: Redis, Horizon, multi-node workers. Database queue on one box.
- Real-time dashboard updates (websockets/polling); plain page loads.

## Success criteria per core feature

- **Operator authentication** — The artisan-created operator can log in and reach the dashboard;
  wrong credentials show a safe error; every dashboard route without a session redirects to
  `/login`; logout ends the session. There is no registration route.
- **Source CRUD** — Creating a source displays a full ingest URL with a unique key; the signing
  secret is never rendered back in full after save; editing name/secret works; deleting a source
  makes its ingest URL return 404 while its historical events remain viewable.
- **Verified ingest** — For each of the four providers: a correctly signed request returns 200 and
  creates exactly one `webhook_events` row with raw payload, filtered headers, extracted provider
  event id, and event type; a tampered body or wrong secret returns 401 with the error envelope
  and persists nothing; a Stripe timestamp outside tolerance is rejected; an unknown ingest key
  returns 404; a body over the size limit returns 413.
- **Idempotency** — Sending the same provider event twice yields one event row and one set of
  deliveries; the second request returns 200 with `duplicate: true`. Two different events with
  no provider id but different bodies both persist (hash fallback distinguishes them).
- **Destinations and routing** — An event from a source routed to N destinations creates exactly N
  deliveries; a destination not routed to the source gets none; detaching a destination stops new
  deliveries but preserves existing ones.
- **Queued delivery** — A destination returning 2xx yields state `delivered` with one attempt row
  capturing status, headers, body excerpt, and duration. A failing destination is retried per the
  documented backoff schedule, each attempt recorded, and reaches `dead` after the max-attempts
  cap. Every forwarded request contains `X-Relay-Event-Id` and `X-Relay-Delivery-Id`, and
  `X-Relay-Event-Id` is identical across retries and replays of the same event.
- **Dashboard** — Each filter narrows results correctly and combines with pagination; empty
  filter results render an explicit empty state; the DLQ view lists only `dead` deliveries;
  requeue (single and bulk) returns deliveries to `pending` and they are re-attempted.
- **Replay** — Replaying an event creates new `pending` deliveries for currently routed
  destinations and they are delivered; bulk replay of K selected events creates deliveries for
  all K; replay never creates a duplicate `webhook_events` row.
- **Retention pruning** — Running the prune command with a 30-day window deletes only terminal
  events older than 30 days, cascades to deliveries and attempts, and leaves newer events and
  events with pending/failed deliveries untouched.
