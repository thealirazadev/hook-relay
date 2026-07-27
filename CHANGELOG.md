# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project aims to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html)
once tagged releases begin.

## [Unreleased]

Initial development of hook-relay, a self-hosted inbound webhook gateway. No versioned
release has been tagged yet; everything below is currently on `main`.

### Added

- Verified ingest endpoint with per-provider HMAC signature verification for Stripe,
  GitHub, Shopify, and a generic HMAC source, including Stripe timestamp tolerance and
  constant-time comparison.
- Idempotent event storage: duplicate deliveries of the same event are de-duplicated and
  return success without creating a second event row.
- At-least-once outbound delivery via a queued job with exponential backoff and jitter,
  dead-lettering after the attempt cap, and stable idempotency headers across retries.
- Per-attempt audit trail capturing response status, headers, body excerpt (capped at
  2048 bytes), duration, and connection errors.
- Sources and destinations CRUD with encrypted signing secrets, soft deletes, and
  per-source routing.
- Outbound delivery signing: each destination has its own generated signing secret, and
  every forwarded delivery carries `X-Relay-Timestamp` and an `X-Relay-Signature`
  (HMAC-SHA256 over `"{timestamp}.{body}"`) so consumers can verify origin and integrity;
  the secret is viewable and rotatable from the dashboard.
- Server-rendered dashboard: status overview, events and deliveries browse with
  combinable filters, event and delivery detail, and a dead-letter queue with single and
  bulk requeue.
- Single and bulk event replay (capped at 100) that reuses the original event id.
- Retention pruning of terminal events, and a per-key ingest rate limit.
- Operator authentication with a console command to create users, throttled login, and no
  public registration.
