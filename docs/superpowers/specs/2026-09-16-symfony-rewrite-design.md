# Symfony 7.4 rewrite — design

## Goal

Replace pixel-tracks's custom PHP micro-framework (FastRoute + PHP-DI + hand-rolled
middleware pipeline + Doctrine DBAL with raw SQL) with a Symfony 7.4 LTS application
using Doctrine ORM over SQLite, while preserving full feature parity: every current
route, magic-link authentication, GPX upload/parsing, mail sending, and
templates/assets.

Explicitly out of scope: any test suite. The current Codeception suites (Unit,
Acceptance) and the untracked `tests/Functional/` are not carried forward as part of
this plan; tests are added later, separately.

## Global constraints

- **Docker-only.** All tooling — Composer, `bin/console`, Doctrine migrations, the
  dev server — runs inside Docker (`make cli` or equivalent), matching this
  project's existing preferred workflow (`make start`/`make cli` per `CLAUDE.md`).
  This plan does not need to support or document a non-Docker path.
- **Fresh database.** There is no production data to preserve. The database is
  created from scratch; migrations are authored the normal way (generated from the
  entity mapping via Doctrine's own tooling), not reverse-engineered to match the
  current hand-written schema column-for-column, and there is no existing file to
  reconcile.
- **No `carob-mailer`.** Only two mail transports are needed: `smtp` and a no-op
  `log`/`null` transport for local dev. The custom Carob HTTP-API mailer is dropped,
  not ported.

## Relationship to the prior attempt

A previous attempt at this same conversion exists on branch `pt-symfony-conversion`
(design: `docs/superpowers/specs/2026-09-15-symfony-conversion-design.md` on that
branch; plan: `docs/superpowers/plans/2026-09-15-symfony-conversion.md`; progress
ledger: `.superpowers/sdd/2026-09-15-symfony-conversion/progress.md`). That attempt
used an incremental "legacy bridge" strategy — the old app kept serving every
unconverted route while Symfony routes were added one at a time — specifically to
keep a test suite green at every commit. It completed Milestone 1 (kernel boots
alongside the legacy app) and was mid-way through Milestone 2 (porting the HTTP
layer / Task 2.3, login+logout+auth subscriber) when it stopped.

Per explicit user decision, that branch, its two stashes, and the untracked files it
left behind on `main` (`config/reference.php`, `src/Controller/`,
`src/EventSubscriber/`, `tests/Functional/`) are left untouched and are **not**
inputs to this plan. This is a fresh design, executed as a direct rewrite rather than
an incremental bridge, since dropping the test-suite requirement removes the main
reason for the bridge approach.

## Current app inventory

### Routes (`src/Routes/Web.php`)

| Method | Path | Controller::method | Auth |
|---|---|---|---|
| GET | `/` | `HomeController::index` (redirects to `/profile`) | required |
| GET | `/send-magic-link` | `MagicLinkController::requestMagicLink` | public |
| POST | `/send-magic-link` | `MagicLinkController::sendMagicLink` | public |
| GET | `/profile/` | `HomeController::profile` | required |
| GET | `/profile/?page={page}` | `HomeController::profile` | required |
| GET | `/login/{loginKey}` | `LoginController::login` | public |
| GET | `/logout` | `LogoutController::index` | required |
| GET | `/map/{trackKey}` | `MapController::index` | required |
| POST | `/track/upload` | `UploadController::uploadTrack` | required |
| GET | `/track/info/{trackKey}` | `TrackController::index` | required |
| POST | `/track/delete` | `TrackController::deleteTrack` | required |

"required" is enforced today by `AuthenticationMiddleware`, which allow-lists any
path starting with `/login` or `/send-magic-link` and otherwise checks
`GateKeeper::isAuthenticated()` (a `userKey` in session that still resolves to a
user).

### Schema (`src/Database/Migrations/*.php`, applied in filename order)

```
users:  id INTEGER PK, key VARCHAR, email VARCHAR, login_key VARCHAR NULL,
        updated_at VARCHAR NULL
tracks: id INTEGER PK, user_id INTEGER, name VARCHAR, key VARCHAR,
        shared_key VARCHAR NULL, filename VARCHAR, total_points INTEGER NULL,
        elevation REAL NULL, distance REAL NULL, created_at TEXT
```

`shared_key` is created by migration but not read or written anywhere in current
`TrackRepository`/controllers — dead column today.

### Config / env (`src/Service/Config.php`, `.env.dist`)

`BASE_URL`, `APPLICATION_MODE`, `DATABASE_DSN`/`DATABASE_NAME`, `EMAIL_FROM`,
`RATE_LIMITER_REFILL_PERIOD`, `RATE_LIMITER_MAX_CAPACITY`, `MAIL_PROVIDER`/
`MAIL_PROVIDER_DSN` (dropped — replaced by a single Mailer DSN env var, see Mail
below), `LOGIN_TOLERANCE_TIME` (minutes), `PAGINATION_IPP`, `ALLOW_COUNTRY_CODE`.

Data/log/db paths: `var/data/profile-{userId}/`, `var/logs/error.log`,
`var/database/{DATABASE_NAME}`.

## Target architecture

- Symfony 7.4 LTS skeleton via Flex (`symfony/skeleton` + framework-bundle,
  twig-bundle, security-bundle, doctrine-bundle, doctrine-migrations-bundle,
  mailer, rate-limiter, http-client).
- Root namespace `App\`.
- Controllers under `src/Controller/`, one class per current controller, routes as
  `#[Route]` attributes, autowired constructor dependencies (mirrors current PHP-DI
  usage almost directly).
- `templates/` (Symfony convention) replaces `src/Templates/`, `.html.twig`
  extension.
- `public/index.php` becomes the standard Symfony front controller; the existing
  `bin/copy-assets.php` / `composer copy-assets` step is kept unchanged for
  `public/{css,js,plugins,images}`.
- Doctrine ORM (attribute mapping) + `doctrine/doctrine-migrations-bundle` over a
  fresh SQLite database, replacing `src/Service/Database.php` and hand-written
  repositories' raw SQL.
- `bin/console` becomes the standard Symfony console; the custom
  `Command/Migration/*` and `GenerateTransferCommand` are deleted (superseded by
  `doctrine:migrations:*`; transfer-object generation is no longer needed — see
  below).

## Data model

Two entities, mapped with ordinary Doctrine conventions — there is no existing data
to preserve, so column names/shapes follow what's idiomatic for the ORM rather than
mirroring the current hand-written schema:

```php
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User implements UserInterface
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private int $id;
    #[ORM\Column(unique: true)] private string $key; // UUID, still generated; no longer the auth identifier
    #[ORM\Column(unique: true)] private string $email;
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Track::class)] private Collection $tracks;
}

#[ORM\Entity]
#[ORM\Table(name: 'tracks')]
class Track
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private int $id;
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tracks')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;
    #[ORM\Column] private string $name;
    #[ORM\Column(unique: true)] private string $key;
    #[ORM\Column] private string $filename;
    #[ORM\Column(nullable: true)] private ?int $totalPoints = null;
    #[ORM\Column(nullable: true)] private ?float $elevation = null;
    #[ORM\Column(nullable: true)] private ?float $distance = null;
    #[ORM\Column] private \DateTimeImmutable $createdAt;
}
```

There is no `login_key`/`updated_at` on `User` at all — see Authentication below for
why. There is no `shared_key` on `Track` either: it's dead in the current app (no
reader or writer anywhere in `TrackRepository`/the controllers), and with a fresh
database there's no reason to recreate a column nothing uses. If track sharing is
wanted later, it's a new, deliberately-designed feature rather than a carried-over
unused column.

The initial Doctrine migration is generated normally
(`doctrine:migrations:diff`) from this mapping — there's no existing database file
to reconcile it against.

`UserRepository`/`TrackRepository` become thin Doctrine `EntityRepository` subclasses
exposing the same query shapes the controllers need today (`findOneByKey`,
`findOneByEmail`, paginated tracks for a user, etc.) instead of hand-written SQL.

The generated-DTO machinery (`tuxonice/transfer-objects`, `bin/console t:g`,
`src/DataTransfers/Definitions/*.json`, `src/DataTransfers/DataTransferObjects/*`) is
deleted entirely: `User`/`Track` entities replace `UserTransfer`/`TrackTransfer`;
`Symfony\Mime\Email` replaces `MailMessageTransfer`/`MailRecipientTransfer`; Doctrine
ORM's `Paginator` replaces `PaginatedTrackTransfer`.

## Authentication

Replace `GateKeeper` + the `login_key`/`updated_at` mechanism with Symfony
Security's built-in `login_link` firewall feature:

- `MagicLinkController::sendMagicLink` generates a signed login-link URL via
  `LoginLinkHandlerInterface::createLoginLink($user)` instead of writing a
  `login_key` to the DB, and emails it exactly as today.
- `/login/{...}` is handled by Security's login-link check route
  (`check_route`), which verifies the signature and expiry
  (`lifetime` config, replacing `LOGIN_TOLERANCE_TIME`) and logs the user in — this
  replaces the current `LoginController::login`.
- Single-use is preserved via a small used-link cache (Symfony's `used_link_cache`
  config option, backed by the existing `Cache` pool), functionally equivalent to
  today's `resetLoginKey()` call after a successful login.
- The firewall's `access_control` rules replace `AuthenticationMiddleware`'s
  allow-list (`/login`, `/send-magic-link` public; everything else requires
  `IS_AUTHENTICATED_FULLY`), and unauthenticated access redirects to
  `/send-magic-link` exactly as today (custom `AuthenticationEntryPoint`).
- `User` becomes a Symfony `UserInterface` implementation, identified by `email`
  (see Decisions below); `LogoutController` is replaced by Security's logout
  listener configured to redirect to `/send-magic-link`.
- `GateKeeper` itself is deleted; anywhere the app needs "the current user," it uses
  Symfony's `Security` service / `#[CurrentUser]`.

This changes behavior in one small way worth flagging: today's link is valid for
`LOGIN_TOLERANCE_TIME` minutes *since the last time a link was requested* (stored in
`updated_at`) and is invalidated by successful login; the Symfony equivalent is
valid for a configured lifetime *since that specific link was created* and is
invalidated the same way. For a single-user-facing flow like this they're
observably identical.

## Controllers

Each current controller ports to a Symfony controller class, one file per
controller, under `src/Controller/`:

- `HomeController` — `index()` (redirect), `profile()` (paginated track list).
- `MagicLinkController` — `requestMagicLink()`, `sendMagicLink()`.
- `LoginController` — thin wrapper if any post-login logic remains beyond what
  Security's login-link success handler covers (e.g. redirect target `/profile/`).
- `LogoutController` — likely removed entirely in favor of `security.yaml`'s
  `logout.path`.
- `MapController` — track ownership check + GPX map render.
- `TrackController` — `index()` (info) and `deleteTrack()`.
- `UploadController` — `uploadTrack()`.

Session/flash-message usage (`$request->getSession()`, `getFlashBag()`) is unchanged
since the current app already runs on Symfony's `HttpFoundation` Session — this is a
near-verbatim port for that part.

Pagination: Doctrine ORM's `Paginator` utility replaces
`src/Pagination/{Paginator,PaginatorQuery}.php` and `PaginatedTrackTransfer`.

## Mail

`symfony/mailer` replaces the hand-rolled `MailProviderInterface` +
`{Smtp,Log}Mailer` (the `carob-mailer` provider is dropped entirely, not ported —
see Global constraints), configured via a single Mailer DSN env var instead of the
current `MAIL_PROVIDER`/`MAIL_PROVIDER_DSN` pair:

- Local/dev (Mailpit) and any real SMTP provider both use the native
  `smtp://user:pass@host:port` DSN (drops the PHPMailer dependency entirely).
- A `null://null` DSN covers the current `log` provider's behavior (no-op send,
  matching today's `LogMailer::send()` always returning `true` without sending).

`Symfony\Mime\Email` (with `html()`/`text()` bodies rendered from the same two Twig
mail templates) replaces `MailMessageTransfer`/`MailRecipientTransfer`.

## GPX upload / parsing

`src/Gps/GpsTrack.php`, `src/Service/{GpxValidator,FileUploaderService}.php`,
`src/Validator/XmlValidator.php` are framework-agnostic already (no PHP-DI- or
FastRoute-specific code) — ported with only namespace changes and Symfony-style
autowiring registration. `Config::getSchemaPath()`/`getDataPath()`/
`getUserDataPath()` move onto Symfony's parameter/config system
(`%kernel.project_dir%`-relative paths via `services.yaml` parameters) instead of
hard-coded `dirname(__DIR__, N)` calls.

## Templates / assets

`src/Templates/**/*.twig` → `templates/**/*.html.twig`, loaded via TwigBundle's
default `templates/` path. No structural changes to the Twig code itself beyond
what's forced by the template rename and by controllers now passing an
`app.user`/`app.flashes`-style context instead of manually building the `flashes`/
`_token`/`showLogout` array (CSRF tokens come from Symfony's CSRF component, not a
hand-rolled session value).

Static assets (`public/css`, `public/js`, `public/plugins`, `public/images`) keep
being populated by the existing `bin/copy-assets.php` / `composer copy-assets` step,
copying from `src/Resources/{css,js,plugins,images}` — unchanged.

## Ancillary services (full parity, ported using Symfony-native equivalents)

- **CSRF** — Symfony's built-in CSRF protection (`security-csrf`) replaces
  `CsrfMiddleware`/`CsrfTokenException`. Forms use Symfony's token
  generate/validate calls instead of the hand-rolled session-stored token.
- **Magic-link rate limiting** — `symfony/rate-limiter`'s token-bucket policy
  replaces the hand-rolled `src/RateLimiter/RateLimiter.php`, keeping the same two
  limiters (per-IP, per-email-hash) and the same `RATE_LIMITER_REFILL_PERIOD`/
  `RATE_LIMITER_MAX_CAPACITY` env values.
- **Country restriction** — `RestrictCountryMiddleware` + `IpApiService` become a
  `kernel.request` event subscriber using `symfony/http-client` (already the
  underlying client `IpApiService` uses) and the `ALLOW_COUNTRY_CODE` env var,
  behavior unchanged (no-op when empty).
- **Security headers** — `SecurityHeadersMiddleware` becomes a `kernel.response`
  event subscriber, same headers.

## What gets deleted once the cutover is complete

`src/App.php`, `src/di-config.php`, `src/Routes/`, `src/Middleware/`,
`src/Service/Database.php`, `src/Service/{Twig,TwigLoader}.php`,
`src/Command/{Migration,GenerateTransferCommand}.php`,
`src/Database/{MigrationInterface,MigrationProvider,Migrations}/`,
`src/DataTransfers/`, `src/Pagination/`, `src/RateLimiter/RateLimiter.php`,
`src/Service/GateKeeper.php`, `src/Mail/` (`MailProviderInterface`, `SmtpMailer`,
`CarobMailer`, `LogMailer`), the custom `bin/console`, and the
`phpmailer/phpmailer`/`tuxonice/transfer-objects` composer dependencies.

## Explicitly out of scope

- Any test suite (Codeception, PHPUnit, or otherwise). No test files are written or
  ported as part of this plan.
- The abandoned `pt-symfony-conversion` branch, its stashes, and the untracked files
  it left on `main` — not read from, not merged, not deleted.
- `shared_key` track-sharing — the column isn't recreated (see Data model); no new
  share-link functionality is added.

## Decisions carried into the plan

- **Security identifier**: `User::getUserIdentifier()` returns `email`. It's the
  natural lookup for the login-link flow (the input to `sendMagicLink` is always an
  email address), and it matches `findUserByEmail` already being the primary lookup
  today. `key` remains on the entity as a generated UUID but is no longer the auth
  identifier.
