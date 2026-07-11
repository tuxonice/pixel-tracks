# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

pixel-tracks: a small self-hosted PHP app for uploading and browsing GPX tracks (stats, map view, share links). Auth is passwordless via email "magic links". PHP `^8.3`, SQLite storage.

## Setup

```
cp .env.dist .env
composer install
composer copy-assets      # copies src/Resources/{css,js,plugins,images} -> public/
bin/console t:g           # generates Data Transfer Objects — required before code using them will typecheck/run (see below)
```

Docker (preferred dev flow): `make start`, then `make cli` to get a shell in the app container, then run the composer commands above inside it. `make help` lists all targets. Mailpit (catches magic-link emails in dev) is at `http://localhost:8025/`.

Writable folders needed outside Docker: `var/logs/`, `var/data/`, `var/database/`. Web server document root is `public/`.

## Commands

- `composer tests` — runs static analysis (phpcs + phpstan) then the full Codeception suite. This is what CI runs.
- `vendor/bin/phpcs` — code style (PSR12 + custom rules, see `phpcs.xml`)
- `vendor/bin/phpstan analyse` — static analysis at level 6 (`phpstan.neon`), covers `src` and `tests/Unit`
- `vendor/bin/codecept run tests/Unit` — unit tests only
- `vendor/bin/codecept run tests/Acceptance` — acceptance tests; require the app actually serving requests (`php -S localhost:8000 -t public`) and `tests/Acceptance.suite.yml`'s `PhpBrowser.url` pointed at it
- `vendor/bin/codecept run <path>` — run a single test file or directory, e.g. `vendor/bin/codecept run tests/Unit/Cache/CacheTest.php`
- `bin/console` — project CLI entrypoint; subcommands: `t:g` (transfer:generate), `migration:apply`/status/make (check `src/Command/Migration` for exact names)

## Architecture

**Request lifecycle**: `public/index.php` → `App::getInstance()` builds a PHP-DI container (`src/di-config.php`) and a FastRoute dispatcher (`src/Routes/Web.php`). `App::route()` builds a `MiddlewarePipeline` (`RestrictCountryMiddleware` → `ExceptionHandlerMiddleware` → `AuthenticationMiddleware` → `CsrfMiddleware`, in that order) and passes the request through it before dispatching to the matched controller action via the DI container (which autowires constructor and method params, e.g. `Request`, `Session`, route path params).

**Routing**: all routes are declared in `src/Routes/Web.php`. Controllers live in `src/Controllers/` and are plain classes with DI-injected dependencies — no base controller class.

**Auth**: no passwords. `MagicLinkController` emails a one-time login link; `LoginController` validates it and starts a session; `GateKeeper` (`src/Service/GateKeeper.php`) is consulted by `AuthenticationMiddleware` to decide if the current session is authenticated, redirecting to `/send-magic-link` otherwise. Public (unauthenticated) routes are listed explicitly in `AuthenticationMiddleware::PUBLIC_ROUTES`.

**Data layer**: no ORM. `src/Service/Database.php` wraps a Doctrine DBAL `Connection` over SQLite. Repositories (`src/Repository/`) hand-write SQL and return generated Transfer Objects rather than arrays/entities.

**Data Transfer Objects are generated, not hand-written.** Definitions live in `src/DataTransfers/Definitions/*.json` (e.g. `track.json` defines `TrackTransfer`, `PaginatedTrackTransfer`, `UserTransfer`). Running `bin/console t:g` (wraps `tuxonice/transfer-objects`) generates the actual classes into `src/DataTransfers/DataTransferObjects/`, which is gitignored and excluded from phpcs — **this directory won't exist on a fresh checkout**, and code referencing e.g. `PixelTrack\DataTransfers\DataTransferObjects\TrackTransfer` won't resolve until the generator has been run. CI runs `bin/console t:g` right after `composer install`, before phpcs/phpstan/tests. Do the same locally when these classes appear missing. When adding/changing a transfer's shape, edit the JSON definition and regenerate rather than hand-editing generated output.

**Migrations**: plain PHP files in `src/Database/Migrations/`, named `YYYY_MM_DD_HHMMSS_description.php`, each returning an object with an `up()` method that returns raw SQL. `MigrationProvider` (`src/Database/MigrationProvider.php`) tracks applied migrations in a `migrations` table and applies pending ones in filename order. Use `bin/console` migration commands (`src/Command/Migration/`) rather than editing the DB directly; use `MigrationMakeCommand` to scaffold a new one so naming/format stays consistent.

**GPX handling**: uploads go through `UploadController` → `FileUploaderService` → `GpxValidator` (XML/schema validation, `src/Validator/XmlValidator.php`, schemas dir via `Config::getSchemaPath()`) → parsed with `sibyx/phpgpx` into `GpsTrack` (`src/Gps/GpsTrack.php`) for stats (distance/elevation/points). Uploaded files are stored per-user under `var/data/profile-{userId}/`.

**Cross-cutting services**: `Cache` (`src/Cache/`, used e.g. by the rate limiter), `RateLimiter` (`src/RateLimiter/RateLimiter.php`, token-bucket, configured per-use via `refillPeriod`/`maxCapacity`/`prefix` — used to throttle magic-link requests), `Paginator`/`PaginatorQuery` (`src/Pagination/`, backs the profile track list), `Twig`/`TwigLoader` (view rendering; templates load from `src/Templates/`, e.g. `Default/track.twig`), mail via `MailProviderInterface` with three implementations selected by `MAIL_PROVIDER` env var (`smtp`, `carob-mailer`, `log`).

**Config**: `src/Service/Config.php` centralizes filesystem paths (`var/logs`, `var/data`, `var/database`, migrations, schemas) and a handful of env-derived values (base URL, login tolerance, allowed country code, production-mode check). Env vars are loaded once in `bootstrap.php` via `vlucas/phpdotenv`, using `.env.test` instead of `.env` when the `Application-Env: test` HTTP header is present (this is how acceptance tests get test config against a running server).

## Testing

Codeception with three suites, each with a distinct role — check `tests/*.suite.yml` before assuming a module (e.g. `Db`) is available in a given suite:
- `tests/Unit` — plain unit tests (actor `UnitTester`), has DB module enabled against `tests/_data/test-database.sqlite`
- `tests/Functional` — framework-level tests (actor `FunctionalTester`), configured but currently has no `*Cest`/`*Test` files
- `tests/Acceptance` — black-box HTTP tests via `PhpBrowser` (actor `AcceptanceTester`) against a real running instance; DB is seeded from `tests/_data/dump.sqlite` before the run (`populate: true`, `cleanup: false`, i.e. state persists across tests in the run)

Fixtures live in `tests/Fixtures/` and `tests/Support/Data/` (sample/invalid GPX files).