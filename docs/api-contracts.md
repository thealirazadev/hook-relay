# API Contracts — hook-relay

Two HTTP surfaces exist: the public JSON **ingest endpoint** and the session-authenticated
**dashboard** (server-rendered HTML, plain form POSTs — not a JSON API). This file also fixes the
**outbound delivery request** contract, which downstream consumers depend on. All three are agreed
here before any code is written.

Base URL: value of `APP_URL`. Timestamps are ISO-8601 UTC.

## Error envelope (all ingest JSON errors)

```json
{
  "error": {
    "code": "invalid_signature",
    "message": "The request signature could not be verified."
  }
}
```

`details` (object) may be added for field-level context but is normally absent on ingest.

### Stable error codes

| HTTP | `error.code` | When |
|---|---|---|
| 404 | `unknown_source` | Ingest key does not match an active source. |
| 401 | `invalid_signature` | Signature header missing, malformed, mismatched, or (Stripe) timestamp outside tolerance. |
| 413 | `payload_too_large` | Body exceeds `INGEST_MAX_BODY_KB`. |
| 405 | `method_not_allowed` | Any method other than POST on the ingest URL. |
| 429 | `rate_limited` | Per-source ingest throttle exceeded. |
| 500 | `server_error` | Unexpected error (details logged, never returned). |

Nothing is persisted on any error response. Signature failures are logged with the source id and
reason, never with the secret.

---

## Ingest endpoint

### POST /ingest/{ingest_key}  (public; signature is the auth)

The raw request body is treated as opaque bytes: it is verified, stored, and forwarded verbatim.
JSON parsing is opportunistic (Stripe id/type extraction) and never rejects a payload.

Required signature header by source provider:

| Provider | Header | Scheme |
|---|---|---|
| `stripe` | `Stripe-Signature` | `t=<unix ts>,v1=<hex hmac_sha256(secret, "{t}.{body}")>`; reject if \|now − t\| > 300s; multiple `v1` entries allowed, any match passes |
| `github` | `X-Hub-Signature-256` | `sha256=<hex hmac_sha256(secret, body)>` |
| `shopify` | `X-Shopify-Hmac-Sha256` | `base64(raw hmac_sha256(secret, body))` |
| `generic` | `X-Signature` | `sha256=<hex hmac_sha256(secret, body)>` |

All comparisons use constant-time equality (`hash_equals`).

Provider event id (dedupe key) and event type extraction:

| Provider | Event id | Event type |
|---|---|---|
| `stripe` | JSON body `id` | JSON body `type` |
| `github` | `X-GitHub-Delivery` header | `X-GitHub-Event` header |
| `shopify` | `X-Shopify-Webhook-Id` header | `X-Shopify-Topic` header |
| `generic` | `X-Event-Id` header (optional) | — |

When no provider event id is present, the dedupe key falls back to `sha256:<hex sha256(body)>`.
Dedupe is scoped per source: unique `(source_id, dedupe_key)`.

Response `200` (accepted, first time):
```json
{ "data": { "event_id": "01J2ZK8Q4V9WXY5T3M1N7P6R2S", "duplicate": false } }
```

Response `200` (duplicate — provider retry; nothing new persisted, no new deliveries):
```json
{ "data": { "event_id": "01J2ZK8Q4V9WXY5T3M1N7P6R2S", "duplicate": true } }
```

`event_id` is the stored event's ULID — the same value later sent downstream as
`X-Relay-Event-Id`. Errors: see the code table above. Providers treat any 2xx as success, so
duplicates deliberately return 200, not 409.

---

## Outbound delivery request (hook-relay → destination)

For each delivery attempt, hook-relay sends:

```
POST <destination.url>
Content-Type: <original ingest Content-Type, default application/json>
User-Agent: hook-relay/1.0
X-Relay-Event-Id: 01J2ZK8Q4V9WXY5T3M1N7P6R2S
X-Relay-Delivery-Id: 01J2ZKA31H8FDT0C9G4B5E7XQM
X-Relay-Source: <source name>

<original raw payload, unmodified>
```

Contract for consumers:

- **Dedupe on `X-Relay-Event-Id`.** It is stable for the lifetime of the event — identical across
  retries, requeues, and replays. `X-Relay-Delivery-Id` is unique per delivery and identifies the
  audit-trail row.
- A response with any **2xx** status within `DELIVERY_TIMEOUT_SECONDS` (default 10s) marks the
  delivery `delivered`. Anything else — 3xx (redirects are not followed), 4xx, 5xx, timeout,
  connection failure — counts as a failed attempt and is retried per the backoff schedule in
  `docs/architecture.md` until the attempt cap, then parked as `dead`.
- Original provider headers (including provider signatures) are **not** forwarded; they are stored
  on the event and visible in the dashboard. Destinations trust the relay by network position and
  the relay headers, not by re-verifying provider signatures.
- Response status, headers, and the first 2048 bytes of the response body are recorded per attempt.

---

## Dashboard routes (HTML, not JSON)

All routes below except login require an authenticated session; unauthenticated requests redirect
to `/login`. All POSTs carry a CSRF token. Validation errors re-render the form with field errors;
success redirects with a flash message. There is exactly one operator role — any authenticated
user can do everything.

| Method | Path | Purpose |
|---|---|---|
| GET | `/login` | Login form (guests only). |
| POST | `/login` | Attempt login; throttled by IP. |
| POST | `/logout` | End session. |
| GET | `/` | Dashboard home: status counts, recent events, dead-letter count. |
| GET | `/sources` | List sources with ingest URLs. |
| GET | `/sources/create` · POST `/sources` | Create source (name, provider, secret). |
| GET | `/sources/{id}/edit` · PUT `/sources/{id}` | Edit source + routed-destination checkboxes. |
| DELETE | `/sources/{id}` | Soft-delete; ingest URL starts returning 404. |
| GET | `/destinations` | List destinations. |
| GET | `/destinations/create` · POST `/destinations` | Create destination (name, url). |
| GET | `/destinations/{id}/edit` · PUT `/destinations/{id}` | Edit destination. |
| DELETE | `/destinations/{id}` | Soft-delete; no new deliveries, history kept. |
| GET | `/events` | Paginated index; filters: `source_id`, `event_type`, `from`, `to`, `q` (provider event id). |
| GET | `/events/{ulid}` | Payload, stored headers, metadata, deliveries list. |
| POST | `/events/{ulid}/replay` | New pending deliveries to currently routed destinations. |
| POST | `/events/replay` | Bulk replay of selected event ids (max 100 per request). |
| GET | `/deliveries` | Paginated index; filters: `status`, `source_id`, `destination_id`. |
| GET | `/deliveries/{ulid}` | Delivery detail with full attempt history. |
| GET | `/dlq` | Dead deliveries only, newest first. |
| POST | `/dlq/{ulid}/requeue` | Requeue one dead delivery. |
| POST | `/dlq/requeue-all` | Requeue every dead delivery (dispatched in chunks of 100). |
| GET | `/up` | Framework health check (public, no body contract). |

Access summary: `POST /ingest/{key}` and `GET /up` are the only routes reachable without a
session; login is throttled; everything else is operator-only.
