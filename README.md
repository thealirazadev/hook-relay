# hook-relay

A self-hosted inbound webhook gateway built with Laravel. Point third-party webhooks (Stripe,
GitHub, Shopify, or any generic HMAC source) at a hook-relay ingest URL; it verifies the
signature, persists every event, and forwards each one to your configured destination URLs with
retries, dead-lettering, and a full per-attempt audit trail. A server-rendered dashboard lets you
browse, filter, inspect, and replay events and deliveries.

## Stack

- PHP 8.2+ / Laravel 11.x
- SQLite (local dev) / MySQL 8.x (production)
- Database queue driver (no Redis dependency)
- Blade + one static CSS file for the dashboard (no Node toolchain)
- Pest (feature + unit tests) on PHPUnit
- Laravel Pint (PSR-12 formatting)

See `docs/` for the PRD, architecture, API contracts, phases, and engineering rules.

## Install

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:create-user you@example.com   # prompts for a password
```

The four hook-relay settings (`INGEST_MAX_BODY_KB`, `DELIVERY_TIMEOUT_SECONDS`,
`DELIVERY_MAX_ATTEMPTS`, `RETENTION_DAYS`) live in `.env`; see `.env.example` for defaults.

## Run

Three processes make up a full local instance:

```bash
php artisan serve          # dashboard + ingest endpoint
php artisan queue:work     # delivers events to destinations
php artisan schedule:work  # runs retention pruning (only needed to exercise pruning)
```

Log in at `/login`, create a source to get its ingest URL (`POST /ingest/{key}`), add a
destination, and route the destination to the source on the source's edit screen. Point a
provider webhook at the ingest URL, or send a signed request yourself.

## Test

```bash
php artisan test              # full suite (Pest on PHPUnit)
./vendor/bin/pest             # equivalent
./vendor/bin/pint --test      # PSR-12 formatting check
```

## Production

`php artisan migrate --force`, run `php artisan queue:work` under a supervisor, and add a cron
entry for `php artisan schedule:run` every minute. See `docs/launch-checklist.md`.

## License

License: MIT
