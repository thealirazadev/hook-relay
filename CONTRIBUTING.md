# Contributing to hook-relay

Thanks for taking the time to contribute. hook-relay is a self-hosted Laravel application, and this
guide covers everything you need to get a working local copy, run the checks, and open a pull
request that lands cleanly.

By participating you agree to abide by our [Code of Conduct](CODE_OF_CONDUCT.md). Security issues
must **not** be filed as public issues or pull requests — follow the [Security Policy](SECURITY.md)
instead.

## Prerequisites

- PHP 8.2 or newer with the `mbstring`, `xml`, `ctype`, `curl`, `dom`, `fileinfo`, `openssl`,
  `tokenizer`, `pdo`, `pdo_sqlite`, and `sqlite3` extensions.
- [Composer](https://getcomposer.org/).

No Node toolchain is required: the dashboard is server-rendered Blade with a single static
stylesheet and no build step.

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan app:create-user you@example.com
```

Development uses SQLite by default; the automated tests run against an in-memory SQLite database
regardless of your local driver, so they never touch your development data.

To exercise the full system locally you need three processes:

```bash
php artisan serve            # web dashboard
php artisan queue:work       # outbound deliveries
php artisan schedule:work    # retention pruning (only needed when testing retention)
```

## Running the checks

Every change must pass the same two commands CI runs, in this order:

```bash
./vendor/bin/pint --test     # PSR-12 formatting check (must pass)
php artisan test             # full Pest suite (must pass, new tests included)
```

Useful variations:

```bash
./vendor/bin/pint                              # auto-fix formatting
./vendor/bin/pest                              # run the suite via Pest directly
php artisan test tests/Feature/IngestTest.php  # a single file
php artisan test --filter=rejects_tampered_body
```

## Coding standards

- **Formatting is owned by Pint.** Do not hand-format against it; run `./vendor/bin/pint` before
  committing.
- **Laravel idioms throughout.** Controllers stay thin; validation lives in Form Requests; delivery
  logic lives in the `DeliverEvent` job; provider verification lives in `app/Support/Providers`. No
  query logic in routes, middleware, or Blade.
- **No new dependencies** without discussing it first in an issue. The stack is intentionally
  minimal (Eloquent, the bundled HTTP client, the database queue, Pest, Pint) and dependency
  versions are pinned exactly, with the lockfile committed.
- **Schema changes go through migrations.** Never edit a migration that has already been committed;
  add a new one. Model `$fillable`/`$casts` changes ship in the same commit as the migration that
  introduces the columns.
- **Security-sensitive rules:** never hardcode secrets, render all user- and payload-supplied data
  with Blade escaping (`{{ }}`, never `{!! !!}`), validate all input server-side, and use Eloquent
  or parameter binding for every query.
- **No emoji** anywhere in code, comments, commits, or documentation.

See [`docs/rules.md`](docs/rules.md) for the full engineering rules and
[`docs/testing.md`](docs/testing.md) for the testing strategy.

## Commits and pull requests

- Use [Conventional Commits](https://www.conventionalcommits.org/) with a short imperative subject,
  for example `fix: cap response body excerpt at 2048 bytes`.
- One commit per discrete change (a migration, a model, a route, a component, its test). Keep each
  commit in a working state; do not batch several features into one commit.
- Before opening a pull request, confirm `./vendor/bin/pint --test` and `php artisan test` both
  pass locally, and add or update tests for any behavior you change.
- In the pull request description, explain what changed and why, and link any related issue. The PR
  template checklist walks through the expectations.

Small, focused pull requests are much easier to review and merge than large ones. When in doubt,
open an issue to discuss the approach before writing code.
