# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

pixel-tracks: a small self-hosted PHP app for uploading and browsing GPX tracks (stats, map view, share links). Auth is passwordless via email "magic links". PHP 8.4, Symfony 7.4 LTS, Doctrine ORM over SQLite.

## Setup

```
cp .env.dist .env
composer install
composer copy-assets      # copies src/Resources/{css,js,plugins,images} -> public/
bin/console doctrine:migrations:migrate --no-interaction   # creates/updates var/database/database.sqlite
```

Docker (preferred dev flow): `make start`, then `make cli` to get a shell in the app container (as `www-data`), then run the commands above inside it — or run any of them directly from the host as `docker compose exec -u www-data app <command>`. `make help` lists all targets. Mailpit (catches magic-link emails in dev) is at `http://localhost:8125/`.

Writable folders needed outside Docker: `var/cache/`, `var/log/`, `var/data/` (per-user uploaded GPX files), `var/database/` (SQLite file). Web server document root is `public/`.

## Commands

- `bin/console` — Symfony's console entrypoint (run via `docker compose exec -u www-data app bin/console ...` in the Docker flow)
- `bin/console doctrine:migrations:migrate` — applies pending migrations (use `--no-interaction` to skip the confirmation prompt)
- `bin/console doctrine:migrations:status` / `doctrine:migrations:diff` / `doctrine:migrations:generate` — inspect pending migrations / generate a new one from entity-mapping changes / scaffold an empty one
- `bin/console debug:router` — list all registered routes
- `composer copy-assets` — copies `src/Resources/{css,js,plugins,images}` into `public/`; re-run after changing anything under `src/Resources/`

There is no test suite and no test runner installed in this project at present. `tests/` is empty and `codeception.yml` is gone — the legacy Codeception suites (Acceptance/Unit, referencing the deleted `PixelTrack\` namespace) were removed along with the `codeception/*` composer dev dependencies (removing them also dropped `phpunit/phpunit`, which had only ever been a transitive dependency of Codeception, taking `bin/phpunit`/`phpunit.dist.xml`/`.env.test` with it via Flex's recipe uninstall). Tests will be added the Symfony way later — that means requiring `symfony/phpunit-bridge` fresh when the time comes, not reviving anything currently in the repo. `phpstan`/`phpcs` remain dev dependencies in `composer.json`, and `phpcs.xml`/`phpstan.neon` still exist but target the old codebase (e.g. `src/DataTransfers/DataTransferObjects`) and the now-nonexistent `tests/` suites — expect to revisit these when the new test suite is added. A CI workflow does exist at `.github/workflows/tests.yml` (runs on push to `main`/`pt-**` and on PRs into `main`), but it is currently broken/stale: it still runs the legacy pipeline (`bin/console t:g` — a Transfer-Objects-generation command that no longer exists — followed by `phpcs`/`phpstan`/`codecept run`), left over from before the rewrite.

## Architecture

**Request lifecycle**: standard Symfony — `public/index.php` boots `App\Kernel` via `vendor/autoload_runtime.php`. Routes are attribute-based (`#[Route]` on controller methods under `src/Controller/`) and auto-imported via `config/routes.yaml`. The security firewall (see Auth below), then the matched controller, handle each request; `src/EventListener/` classes hook into kernel events for cross-cutting behavior (see Cross-cutting below).

**Routing**: declared with `#[Route(...)]` attributes directly on controller methods in `src/Controller/*.php` — there's no central routes file to edit. Run `bin/console debug:router` to see the full list. Controllers extend Symfony's `AbstractController`; one class per feature area:
- `HomeController` — `/` (redirects to profile) and `/profile/`, `/profile/{page}` (paginated track list)
- `MagicLinkController` — `/send-magic-link` (GET: form, POST: rate-limited link request + email send)
- `LoginController` — `/login/check`, a route that exists only so the `login_link` firewall has something to intercept; the handler body is unreachable
- `LogoutController` — `/logout`, same pattern (Symfony's logout listener intercepts it)
- `UploadController` — `/track/upload` (POST, GPX file upload + validation)
- `TrackController` — `/track/info/{trackKey}` (GET) and `/track/delete` (POST)
- `MapController` — `/map/{trackKey}` (GET, Leaflet map view)

**Auth**: no passwords — Symfony Security's `login_link` firewall (`config/packages/security.yaml`), keyed on the `User` entity's `email` property. `MagicLinkController::sendMagicLink` rate-limits by IP and by email (via the `magic_link_by_ip`/`magic_link_by_email` limiters configured in `config/packages/framework.yaml`), looks up or creates a `User`, and emails a link built by `LoginLinkHandlerInterface`. Visiting the link hits `app_login_check`, which the firewall's authenticator intercepts before the controller body ever runs. `App\Security\LoginSuccessHandler`/`LoginFailureHandler` redirect post-auth; `App\Security\MagicLinkEntryPoint` redirects unauthenticated access attempts to `/send-magic-link`. `access_control` in `security.yaml` makes `/send-magic-link` and `/login*` public and requires full authentication for everything else.

**Data layer**: Doctrine ORM. Entities (`src/Entity/User.php`, `src/Entity/Track.php`) are attribute-mapped; `User` implements `UserInterface` for Security. Repositories (`src/Repository/`) extend `ServiceEntityRepository` and add query-builder methods (e.g. `TrackRepository::findPageForUser`, `countForUser`, `findOneByKey`). No hand-written SQL, no DTOs/transfer-object generation step — inject `EntityManagerInterface` or a repository and work with entities directly.

**Migrations**: standard `doctrine/doctrine-migrations-bundle`, files in `migrations/` (configured in `config/packages/doctrine_migrations.yaml`), named `Version<timestamp>.php` and generated/applied via `bin/console doctrine:migrations:*`. Note: `bin/console doctrine:database:create` does not work against this SQLite setup (doctrine/dbal 4.4 + doctrine-bundle 3.3 throw `... is not supported by platform`) — running `doctrine:migrations:migrate` directly creates `var/database/database.sqlite` as a side effect of connecting, so that command alone is sufficient to stand up a fresh database.

**GPX handling**: `UploadController` validates the uploaded file (`App\Validator\XmlValidator` against `src/Schemas/gpx.xsd`, then `App\Service\GpxValidator` for size/MIME/GPX-namespace/track-content checks), stores it via `App\Service\FileUploaderService` under `var/data/profile-{userId}/`, and parses it with `sibyx/phpgpx` through `App\Gps\GpsTrack` to compute stats (distance/elevation/point count) persisted on the `Track` entity. `MapController` re-parses the stored file via `GpsTrack` to render the Leaflet map.

**Cross-cutting**: `src/EventListener/SecurityHeadersListener` (kernel.response) sets `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, and a `Content-Security-Policy` on every main-request response. `src/EventListener/CountryRestrictionListener` (kernel.request, priority 10) looks up the client IP's country via `App\Service\IpApiService` (cached in `cache.app`) and returns a 403 if it doesn't match the `ALLOW_COUNTRY_CODE` env var (no-op when that var is empty). `App\Pagination\Paginator` backs the profile track list page-number UI. Mail is sent via Symfony Mailer (`MAILER_DSN` env var, Mailpit in dev). Twig templates live in `templates/` (e.g. `templates/Default/track.html.twig`, error pages under `templates/Error/`).

**Config**: filesystem paths and small app-specific settings (email sender, pagination page size, GPX schema path, upload data path, allowed country code) are bound as autowired scalar parameters in `config/services.yaml`'s `_defaults.bind`, sourced from env vars declared in `.env.dist`. `.env`/`.env.dev`/`.env.test` layer over it per Symfony's usual env-file precedence.
