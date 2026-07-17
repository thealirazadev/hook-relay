# hook-relay

A self-hosted inbound webhook gateway built with Laravel. Point third-party webhooks (Stripe,
GitHub, Shopify, or any generic HMAC source) at a hook-relay ingest URL; it verifies the
signature, persists every event, and forwards each one to your configured destination URLs with
retries, dead-lettering, and a full per-attempt audit trail. A server-rendered dashboard lets you
browse, filter, inspect, and replay events and deliveries.

Status: planning — docs under review

## Planned stack

- PHP 8.2+ / Laravel 11.x
- SQLite (local dev) / MySQL 8.x (production)
- Database queue driver (no Redis dependency)
- Blade + one static CSS file for the dashboard (no Node toolchain)
- Pest (feature + unit tests) on PHPUnit
- Laravel Pint (PSR-12 formatting)

See `docs/` for the PRD, architecture, API contracts, phases, and engineering rules.

## Install

TBD until implementation starts.

## Run

TBD until implementation starts.

## Test

TBD until implementation starts.

## License

License: MIT
