# Symfony 7.4 Rewrite Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace pixel-tracks's custom PHP micro-framework (FastRoute + PHP-DI + hand-rolled middleware + raw-SQL DBAL) with a Symfony 7.4 LTS application using Doctrine ORM over a fresh SQLite database, with full feature parity: every route, magic-link authentication, GPX upload/parsing, mail, templates/assets, and the ancillary services (CSRF, rate limiting, country restriction, security headers).

**Architecture:** A direct rewrite, not an incremental bridge. Task 1 deletes the entire legacy `src/` tree and the old front controller/console in one sweep, installs the Symfony skeleton via Flex, and cuts over immediately — the app is intentionally non-functional end-to-end until later tasks land, verified via `bin/console`/lint commands until Task 3 restores the first working page. Every task after that is purely additive.

**Tech Stack:** PHP 8.4, Symfony 7.4 (framework-bundle, security-bundle, twig-bundle, doctrine-bundle, doctrine-migrations-bundle, mailer, rate-limiter, monolog-bundle), Doctrine ORM, SQLite, Twig, sibyx/phpgpx (kept). Docker only (`docker compose`, matching this repo's existing `Makefile`).

**Spec:** `docs/superpowers/specs/2026-09-16-symfony-rewrite-design.md`

## Global Constraints

- **Docker-only.** Every command in this plan runs inside the `app` container: `docker compose exec -T -u www-data app <command>`. Ensure containers are up first: `docker compose up -d` (or `make start`). No command in this plan assumes a local PHP/Composer install.
- **No test suite.** No PHPUnit/Codeception code is written. No task is gated on `composer tests`, `phpcs`, or `phpstan` passing — those tools are not part of this plan's verification. Each task instead specifies exact `bin/console`/`curl` verification commands and expected output.
- **Fixture GPX files are kept, not deleted.** `tests/Fixtures/sample.gpx` and `tests/Fixtures/invalid-sample.gpx` are plain data files (not test code) and are reused in Task 5's manual upload verification.
- **Fresh database.** `var/database/database.sqlite` is recreated from scratch by this plan; no existing data is migrated.
- **No `carob-mailer`.** Only `smtp` and `null` mailer transports are implemented.
- **Root namespace is `App\`.** The legacy `PixelTrack\` namespace is deleted outright in Task 1, not renamed.
- **Mailpit** (already running as the `mailpit` service in `docker-compose.yml`, UI at `http://localhost:8025`) is used for real end-to-end verification of the magic-link email flow via its JSON API: `curl -s http://localhost:8025/api/v1/messages`.
- **Abandoned-attempt leftovers.** Per explicit user decision, the untracked leftovers from the abandoned `pt-symfony-conversion` attempt (`src/Controller/`, `src/EventSubscriber/`, `tests/Functional/`, `config/reference.php`) are not preserved — Task 1, Step 1 deletes them along with the rest of the legacy tree. The `pt-symfony-conversion` branch and its two stashes are untouched (this plan never runs a `git branch -D`/`git stash drop`).

---

## File Structure

**Deleted in Task 1 (entire legacy application):** `src/App.php`, `src/di-config.php`, `src/Cache/`, `src/Command/`, `src/Controllers/`, `src/Database/`, `src/DataTransfers/`, `src/Exception/`, `src/Gps/`, `src/Mail/`, `src/Middleware/`, `src/Pagination/`, `src/RateLimiter/`, `src/Repository/`, `src/Routes/`, `src/Service/`, `src/Validator/`, `bootstrap.php`, `bin/console` (old), `public/index.php` (old). `src/Templates/` is moved (not deleted) to `templates/`.

**Created across this plan:**
- `src/Entity/User.php`, `src/Entity/Track.php` — Doctrine entities (Task 2)
- `src/Repository/UserRepository.php`, `src/Repository/TrackRepository.php` — Doctrine repositories (Task 2)
- `src/Security/MagicLinkEntryPoint.php`, `src/Security/LoginSuccessHandler.php`, `src/Security/LoginFailureHandler.php` — Security glue (Task 3)
- `src/Controller/MagicLinkController.php`, `src/Controller/LoginController.php`, `src/Controller/LogoutController.php` — auth controllers (Task 3)
- `src/Controller/HomeController.php`, `src/Pagination/Paginator.php` — profile/pagination (Task 4)
- `src/Controller/UploadController.php`, `src/Gps/GpsTrack.php`, `src/Service/GpxValidator.php`, `src/Validator/XmlValidator.php`, `src/Service/FileUploaderService.php`, `src/Exception/GpxValidationException.php` — upload (Task 5)
- `src/Controller/MapController.php`, `src/Controller/TrackController.php` — map/track (Task 6)
- `src/EventListener/SecurityHeadersListener.php`, `src/EventListener/CountryRestrictionListener.php`, `src/Service/IpApiService.php` — ancillary services (Task 7)
- `templates/**/*.html.twig` (moved from `src/Templates/`, Task 1), content edited per-feature in Tasks 3, 4, 6, 7

Each task lists its own exact file paths again under **Files** so no cross-referencing is required to execute a single task in isolation.

---

## Task 1: Symfony 7.4 skeleton — clean sweep, Flex install, Docker cutover

**Files:**
- Delete: `src/App.php`, `src/Cache/`, `src/Command/`, `src/Controllers/`, `src/Database/`, `src/DataTransfers/`, `src/Exception/`, `src/Gps/`, `src/Mail/`, `src/Middleware/`, `src/Pagination/`, `src/RateLimiter/`, `src/Repository/`, `src/Routes/`, `src/Service/`, `src/Validator/`, `src/di-config.php`, `bootstrap.php`, `bin/console`, `public/index.php`
- Move: `src/Templates/**/*.twig` → `templates/**/*.html.twig` (see mapping below)
- Modify: `composer.json`, `.env.dist`
- Create (via Flex recipes, verified not hand-written): `src/Kernel.php`, `public/index.php`, `bin/console`, `config/bundles.php`, `config/services.yaml`, `config/packages/*.yaml`, `config/routes.yaml`, `.env`

**Interfaces:**
- Produces: a booting Symfony kernel (`App\Kernel`) served by `public/index.php`, an `App\` PSR-4 autoload root at `src/`, `templates/` as the Twig root. Every later task assumes these exist.

- [ ] **Step 1: Delete the entire legacy application**

Per explicit user decision, the untracked leftovers from the abandoned `pt-symfony-conversion` attempt (`src/Controller/`, `src/EventSubscriber/`, `tests/Functional/`, `config/reference.php`) are no longer being preserved — this plan proceeds without special-casing them, and its own files simply take those paths.

```bash
docker compose exec -T -u www-data app rm -rf \
  src/App.php src/Cache src/Command src/Controller src/Controllers src/Database \
  src/DataTransfers src/EventSubscriber src/Exception src/Gps src/Mail src/Middleware \
  src/Pagination src/RateLimiter src/Repository src/Routes src/Service \
  src/Validator src/di-config.php bootstrap.php bin/console public/index.php \
  tests/Functional config/reference.php
```

Keep `src/Schemas/` (the GPX XSD, still needed in Task 5) and `src/Templates/` (moved next, not deleted).

- [ ] **Step 2: Move and rename templates**

```bash
docker compose exec -T -u www-data app mkdir -p templates/Default/Blocks templates/Default/Mail templates/Error
docker compose exec -T -u www-data app bash -c '
  git mv src/Templates/Default/base.twig templates/Default/base.html.twig
  git mv src/Templates/Default/home.twig templates/Default/home.html.twig
  git mv src/Templates/Default/track.twig templates/Default/track.html.twig
  git mv src/Templates/Default/map.twig templates/Default/map.html.twig
  git mv src/Templates/Default/magic-link.twig templates/Default/magic-link.html.twig
  git mv src/Templates/Default/Blocks/header.twig templates/Default/Blocks/header.html.twig
  git mv src/Templates/Default/Blocks/flash-messages.twig templates/Default/Blocks/flash-messages.html.twig
  git mv src/Templates/Default/Blocks/pagination.twig templates/Default/Blocks/pagination.html.twig
  git mv src/Templates/Default/Mail/magic-link-html.twig templates/Default/Mail/magic-link-html.html.twig
  git mv src/Templates/Default/Mail/magic-link-text.twig templates/Default/Mail/magic-link-text.txt.twig
  git mv src/Templates/Error/base.twig templates/Error/base.html.twig
  git rm src/Templates/Default/not-found.twig
  git rm src/Templates/Default/Blocks/notifications.twig
  git rm src/Templates/Error/403.twig
  git rm src/Templates/Error/500.twig
  rmdir src/Templates/Default/Blocks src/Templates/Default/Mail src/Templates/Default src/Templates/Error src/Templates 2>/dev/null || true
'
```

`notifications.twig` is only ever referenced from a commented-out `{# ... #}` block in `header.twig` — dead, not ported. `403.twig`/`500.twig`/`not-found.twig` are removed here and recreated with content in Task 7 at Symfony's special error-template path (`templates/bundles/TwigBundle/Exception/`); no content is lost, they're just not needed until Task 7 wires exception handling.

Update every moved template's internal references from `.twig` to `.html.twig`:

```bash
docker compose exec -T -u www-data app bash -c "
  sed -i \"s/Default\\/base\\.twig/Default\\/base.html.twig/; s/Default\\/Blocks\\/header\\.twig/Default\\/Blocks\\/header.html.twig/; s/Default\\/Blocks\\/flash-messages\\.twig/Default\\/Blocks\\/flash-messages.html.twig/\" \
    templates/Default/base.html.twig templates/Default/home.html.twig templates/Default/track.html.twig \
    templates/Default/magic-link.html.twig
"
```

`map.html.twig` and the error/mail templates don't `extend`/`include` anything, so they need no reference fixes.

- [ ] **Step 3: Delete the old `PixelTrack\` autoload entry and add `App\`**

Edit `composer.json`: in `"autoload"."psr-4"`, replace `"PixelTrack\\": "src/"` with `"App\\": "src/"`.

- [ ] **Step 4: Relax pinned Symfony/Doctrine versions so Flex can move them to 7.4**

Edit `composer.json`'s `require` block: change these exact-pinned lines to `^7.4`:
```
"symfony/http-foundation": "^7.4",
"symfony/console": "^7.4",
"symfony/cache": "^7.4",
"symfony/uid": "^7.4",
"symfony/http-client": "^7.4",
"symfony/mime": "^7.4",
"doctrine/dbal": "^4.4",
```
Remove these lines entirely (no longer used by anything after Step 1's deletion): `"vlucas/phpdotenv"`, `"nikic/fast-route"`, `"php-di/php-di"`, `"illuminate/support"`, `"phpmailer/phpmailer"`, `"tuxonice/transfer-objects"`, `"monolog/monolog"` (superseded by `symfony/monolog-bundle`, added below).

Run: `docker compose exec -T -u www-data app composer update --no-scripts` to sync `composer.lock` with the edited `composer.json` before installing new packages (`--no-scripts` because the old `composer.json` scripts reference `bin/copy-assets.php`, which still exists and is fine, but we don't want a stale post-install hook running against half-migrated code).

- [ ] **Step 5: Install Symfony Flex, then the framework in one constrained resolve**

```bash
docker compose exec -T -u www-data app composer require symfony/flex --no-scripts
```

Immediately after Flex is present, edit `composer.json`'s `"extra"` block (create it if absent) to pin the resolution floor **before** requiring anything else:

```json
"extra": {
    "symfony": {
        "require": "7.4.*"
    }
}
```

Use `"7.4.*"`, not `"7.4"` — the bare form resolves to the exact vulnerable `7.4.0` of several components (a real, verified pitfall: `7.4.0` sits inside several components' advisory ranges, fixed only from `7.4.12`/`7.4.13`). `"7.4.*"` lets Flex resolve to the latest 7.4.x patch instead.

```bash
docker compose exec -T -u www-data app composer require \
  symfony/framework-bundle symfony/runtime symfony/twig-bundle \
  symfony/security-bundle symfony/monolog-bundle \
  doctrine/doctrine-bundle doctrine/orm doctrine/doctrine-migrations-bundle \
  symfony/mailer symfony/rate-limiter
```

- [ ] **Step 6: Verify a clean, fully-7.4 resolve**

```bash
docker compose exec -T -u www-data app composer show symfony/http-kernel symfony/dependency-injection symfony/routing symfony/http-foundation
```
Expected: every listed package's version starts with `7.4.`. If any shows `8.x`, stop — re-check that `extra.symfony.require` is `"7.4.*"` (not `"7.4"`) and that `symfony/flex` was required before the framework packages, then re-run `composer update`.

```bash
docker compose exec -T -u www-data app composer audit
```
Expected: `No security vulnerability advisories found`.

- [ ] **Step 7: Confirm Flex recipes produced the expected scaffold**

```bash
docker compose exec -T -u www-data app ls src/Kernel.php public/index.php bin/console config/bundles.php config/services.yaml config/routes.yaml
```
All six paths must exist (Flex recipes create them automatically once `symfony/framework-bundle`/`symfony/routing` are installed with `symfony/flex` present). `config/routes.yaml` should contain a `controllers:` entry importing `../src/Controller/` via the `attribute` type — confirm with:
```bash
docker compose exec -T -u www-data app cat config/routes.yaml
```

The legacy app's `bootstrap.php` called `date_default_timezone_set($_ENV['TIMEZONE'])` (`Europe/Lisbon` by default) — nothing in the new Flex-generated boot path does this, so preserve it explicitly in `src/Kernel.php`:
```php
<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function __construct(string $environment, bool $debug)
    {
        parent::__construct($environment, $debug);
        date_default_timezone_set($_ENV['TIMEZONE'] ?? 'UTC');
    }
}
```
(Keep whatever `use` statements Flex's recipe already generated; only the constructor is new.)

- [ ] **Step 8: Reconcile `.env` and `.env.dist`**

Flex's recipes append `APP_ENV`, `APP_SECRET`, and (from the doctrine-bundle recipe) a `DATABASE_URL` placeholder to `.env`. Check what was added:
```bash
docker compose exec -T -u www-data app cat .env
```

Rewrite `.env.dist` (the checked-in template this project's developers copy via `cp .env.dist .env`) to:

```
APP_ENV=dev
APP_SECRET='6c9f3b1a2e8d4f7c0a5b9e2d1f6a8c3b'

TIMEZONE='Europe/Lisbon'
BASE_URL="http://localhost"

DATABASE_URL="sqlite:///%kernel.project_dir%/var/database/database.sqlite"

EMAIL_FROM=user@example.com
MAILER_DSN=smtp://mailpit:1025

RATE_LIMITER_REFILL_PERIOD=50
RATE_LIMITER_MAX_CAPACITY=5

LOGIN_TOLERANCE_TIME=300

PAGINATION_IPP=10

ALLOW_COUNTRY_CODE=""
```

(`LOGIN_TOLERANCE_TIME` changes unit from minutes to seconds — Symfony's `login_link.lifetime` config in Task 3 takes seconds; `300` preserves the same real-world 5-minute window as before.) Update the developer's own `.env` to match this shape (keep any Flex-added `APP_SECRET` value already there rather than overwriting it). Remove `DATABASE_DSN`, `DATABASE_NAME`, `APPLICATION_MODE`, `MAIL_PROVIDER`, `MAIL_PROVIDER_DSN` from both files — nothing reads them anymore.

- [ ] **Step 9: Boot verification**

```bash
docker compose exec -T -u www-data app bin/console about
```
Expected: prints Symfony version `7.4.x`, environment `dev`, PHP version, no errors.

```bash
docker compose exec -T -u www-data app bin/console debug:container --deprecations
```
Expected: exits 0 (no deprecations from our own code — vendor deprecations, if any, are not our concern here).

```bash
docker compose exec -T -u www-data app bin/console lint:twig templates/
```
Expected: `[OK] All 10 Twig files contain valid syntax.` (base, home, track, magic-link, header, flash-messages, pagination, magic-link-html, magic-link-text, Error/base — map.html.twig makes 11; count isn't load-bearing, "no syntax errors" is).

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/
```
Expected: `404` (Symfony's own not-found handling — no route registered yet, proving the kernel is live and routing real HTTP requests through Apache → `public/index.php` → `App\Kernel`).

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: install Symfony 7.4 skeleton, delete legacy micro-framework"
```

---

## Task 2: Doctrine ORM, SQLite, entities, and repositories

**Files:**
- Modify: `config/packages/doctrine.yaml` (Flex-generated, confirm/adjust), `.env`/`.env.dist` (already has `DATABASE_URL` from Task 1)
- Create: `src/Entity/User.php`, `src/Entity/Track.php`, `src/Repository/UserRepository.php`, `src/Repository/TrackRepository.php`, `migrations/VersionXXXXXXXXXXXXXX.php` (generated)

**Interfaces:**
- Consumes: nothing from earlier tasks beyond the booted kernel (Task 1).
- Produces: `App\Entity\User` (constructor `__construct(string $email)`, `getId(): ?int`, `getKey(): string`, `getEmail(): string`, `getTracks(): Collection`, implements `UserInterface` with `getUserIdentifier(): string` returning email), `App\Entity\Track` (constructor `__construct(User $user, string $name, string $filename)`, `getId(): ?int`, `getUser(): User`, `getName(): string`, `getKey(): string`, `getFilename(): string`, `getTotalPoints(): ?int`/`setTotalPoints(?int): static`, `getElevation(): ?float`/`setElevation(?float): static`, `getDistance(): ?float`/`setDistance(?float): static`, `getCreatedAt(): \DateTimeImmutable`), `App\Repository\UserRepository::findOneByEmail(string): ?User`, `App\Repository\TrackRepository::findOneByKey(string): ?Track`, `::countForUser(User): int`, `::findPageForUser(User, int $offset, int $limit): Track[]`. Every later task that touches persistence uses exactly these signatures.

- [ ] **Step 1: Confirm the Doctrine DBAL config points at `DATABASE_URL`**

```bash
docker compose exec -T -u www-data app cat config/packages/doctrine.yaml
```
It must contain `url: '%env(resolve:DATABASE_URL)%'` under `doctrine.dbal` (the `resolve:` processor is required — it's what expands `%kernel.project_dir%` inside the `.env` value from Task 1). If the recipe generated something else, replace the `dbal` section with:
```yaml
doctrine:
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        report_fields_where_declared: true
        validate_xml_mapping: true
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        mappings:
            App:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
```

- [ ] **Step 2: Create the database file**

```bash
docker compose exec -T -u www-data app bin/console doctrine:database:create
```
Expected: `Created database ... for connection named default`. Confirm the file exists:
```bash
docker compose exec -T -u www-data app ls -la var/database/database.sqlite
```

- [ ] **Step 3: Write the `User` entity**

Create `src/Entity/User.php`:
```php
<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private string $key;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    /** @var Collection<int, Track> */
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Track::class, orphanRemoval: true)]
    private Collection $tracks;

    public function __construct(string $email)
    {
        $this->email = $email;
        $this->key = (string) Uuid::v4();
        $this->tracks = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    /** @return Collection<int, Track> */
    public function getTracks(): Collection
    {
        return $this->tracks;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }
}
```

- [ ] **Step 4: Write the `Track` entity**

Create `src/Entity/Track.php`:
```php
<?php

namespace App\Entity;

use App\Repository\TrackRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TrackRepository::class)]
#[ORM\Table(name: 'tracks')]
class Track
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'tracks')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    private string $key;

    #[ORM\Column(length: 255)]
    private string $filename;

    #[ORM\Column(nullable: true)]
    private ?int $totalPoints = null;

    #[ORM\Column(nullable: true)]
    private ?float $elevation = null;

    #[ORM\Column(nullable: true)]
    private ?float $distance = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $name, string $filename)
    {
        $this->user = $user;
        $this->name = $name;
        $this->filename = $filename;
        $this->key = (string) Uuid::v4();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getTotalPoints(): ?int
    {
        return $this->totalPoints;
    }

    public function setTotalPoints(?int $totalPoints): static
    {
        $this->totalPoints = $totalPoints;

        return $this;
    }

    public function getElevation(): ?float
    {
        return $this->elevation;
    }

    public function setElevation(?float $elevation): static
    {
        $this->elevation = $elevation;

        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): static
    {
        $this->distance = $distance;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
```

- [ ] **Step 5: Write the repositories**

Create `src/Repository/UserRepository.php`:
```php
<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function findOneByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }
}
```

Create `src/Repository/TrackRepository.php`:
```php
<?php

namespace App\Repository;

use App\Entity\Track;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Track>
 */
class TrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Track::class);
    }

    public function findOneByKey(string $key): ?Track
    {
        return $this->findOneBy(['key' => $key]);
    }

    public function countForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return Track[] */
    public function findPageForUser(User $user, int $offset, int $limit): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
```

- [ ] **Step 6: Generate and run the initial migration**

```bash
docker compose exec -T -u www-data app bin/console doctrine:migrations:diff --no-interaction
docker compose exec -T -u www-data app bin/console doctrine:migrations:migrate --no-interaction
```

- [ ] **Step 7: Verify the schema**

```bash
docker compose exec -T -u www-data app bin/console dbal:run-sql "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name"
```
Expected rows include `users`, `tracks`, and `doctrine_migration_versions` (no `shared_key`, `login_key`, or `updated_at` columns anywhere — confirm with the next command).

```bash
docker compose exec -T -u www-data app bin/console dbal:run-sql "PRAGMA table_info(users)"
docker compose exec -T -u www-data app bin/console dbal:run-sql "PRAGMA table_info(tracks)"
```
`users` columns: `id, key, email`. `tracks` columns: `id, user_id, name, key, filename, total_points, elevation, distance, created_at`.

- [ ] **Step 8: Verify persistence end-to-end via the console**

```bash
docker compose exec -T -u www-data app bin/console doctrine:query:dql "SELECT COUNT(u) FROM App\Entity\User u"
```
Expected: `0` (no rows yet — proves the DQL/entity mapping resolves without error).

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: add Doctrine ORM entities, repositories, and the initial migration"
```

---

## Task 3: Magic-link authentication, mailer, and logout

**Files:**
- Create: `src/Security/MagicLinkEntryPoint.php`, `src/Security/LoginSuccessHandler.php`, `src/Security/LoginFailureHandler.php`, `src/Controller/MagicLinkController.php`, `src/Controller/LoginController.php`, `src/Controller/LogoutController.php`
- Modify: `config/packages/security.yaml`, `config/services.yaml`, `config/packages/framework.yaml` (rate limiter config), `.env`/`.env.dist` (already has `MAILER_DSN`/`EMAIL_FROM` from Task 1)
- Content already moved in Task 1, edited here: `templates/Default/magic-link.html.twig`, `templates/Default/Blocks/header.html.twig`, `templates/Default/Blocks/flash-messages.html.twig`, `templates/Default/Mail/magic-link-html.html.twig`, `templates/Default/Mail/magic-link-text.txt.twig`

**Interfaces:**
- Consumes: `App\Entity\User` (Task 2 constructor `__construct(string $email)`), `App\Repository\UserRepository::findOneByEmail` (Task 2).
- Produces: routes `app_magic_link_request` (GET `/send-magic-link`), `app_magic_link_send` (POST `/send-magic-link`), `app_login_check` (GET `/login/check`), `app_logout` (GET `/logout`). `$this->getUser(): ?App\Entity\User` becomes available in every controller from here on (via Symfony Security), and `app.user`/`app.flashes` become available in every Twig template.

- [ ] **Step 1: Configure the Doctrine-backed user provider and firewall**

Overwrite `config/packages/security.yaml`:
```yaml
security:
    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false
        main:
            lazy: true
            provider: app_user_provider
            login_link:
                check_route: app_login_check
                signature_properties: ['email']
                lifetime: '%env(int:LOGIN_TOLERANCE_TIME)%'
                max_uses: 1
                used_link_cache: cache.app
                success_handler: App\Security\LoginSuccessHandler
                failure_handler: App\Security\LoginFailureHandler
            entry_point: App\Security\MagicLinkEntryPoint
            logout:
                path: app_logout

    access_control:
        - { path: ^/send-magic-link, roles: PUBLIC_ACCESS }
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

- [ ] **Step 2: Write the entry point and login success/failure handlers**

Create `src/Security/MagicLinkEntryPoint.php`:
```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class MagicLinkEntryPoint implements AuthenticationEntryPointInterface
{
    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse('/send-magic-link');
    }
}
```

Create `src/Security/LoginSuccessHandler.php`:
```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        return new RedirectResponse('/profile/');
    }
}
```

Create `src/Security/LoginFailureHandler.php`:
```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $request->getSession()->getFlashBag()->add(
            'danger',
            'Invalid or expired magic link. Please request a new magic link'
        );

        return new RedirectResponse('/send-magic-link');
    }
}
```

- [ ] **Step 3: Configure the two magic-link rate limiters**

Add to `config/packages/framework.yaml` under the top-level `framework:` key:
```yaml
    rate_limiter:
        magic_link_by_ip:
            policy: 'token_bucket'
            limit: '%env(int:RATE_LIMITER_MAX_CAPACITY)%'
            rate: { interval: '%app.rate_limiter_interval%', amount: '%env(int:RATE_LIMITER_MAX_CAPACITY)%' }
        magic_link_by_email:
            policy: 'token_bucket'
            limit: '%env(int:RATE_LIMITER_MAX_CAPACITY)%'
            rate: { interval: '%app.rate_limiter_interval%', amount: '%env(int:RATE_LIMITER_MAX_CAPACITY)%' }
```

Add to `config/services.yaml` under `parameters:`:
```yaml
    app.rate_limiter_interval: '%env(RATE_LIMITER_REFILL_PERIOD)% seconds'
```

- [ ] **Step 4: Bind `EMAIL_FROM` for controller use**

Add to `config/services.yaml` under `services._defaults.bind:` (create the `bind` key if absent):
```yaml
    _defaults:
        autowire: true
        autoconfigure: true
        bind:
            string $emailFrom: '%env(EMAIL_FROM)%'
```

- [ ] **Step 5: Write `MagicLinkController`**

Create `src/Controller/MagicLinkController.php`:
```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class MagicLinkController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly MailerInterface $mailer,
        #[Autowire(service: 'limiter.magic_link_by_ip')]
        private readonly RateLimiterFactory $magicLinkByIpLimiterFactory,
        #[Autowire(service: 'limiter.magic_link_by_email')]
        private readonly RateLimiterFactory $magicLinkByEmailLimiterFactory,
        private readonly string $emailFrom,
    ) {
    }

    #[Route('/send-magic-link', name: 'app_magic_link_request', methods: ['GET'])]
    public function requestMagicLink(): Response
    {
        return $this->render('Default/magic-link.html.twig');
    }

    #[Route('/send-magic-link', name: 'app_magic_link_send', methods: ['POST'])]
    public function sendMagicLink(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('magic-link', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        if (!$this->magicLinkByIpLimiterFactory->create($request->getClientIp())->consume(1)->isAccepted()) {
            return new Response('<h1>429 Too many requests</h1>', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $email = (string) $request->request->get('email');

        if (!$this->magicLinkByEmailLimiterFactory->create(sha1($email))->consume(1)->isAccepted()) {
            return new Response('<h1>429 Too many requests</h1>', Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->addFlash('danger', 'Invalid email');

            return $this->redirectToRoute('app_magic_link_request');
        }

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user) {
            $user = new User($email);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $loginLinkDetails = $this->loginLinkHandler->createLoginLink($user);

        $mail = (new Email())
            ->from($this->emailFrom)
            ->to($email)
            ->subject('Here is your magic link')
            ->html($this->renderView('Default/Mail/magic-link-html.html.twig', ['link' => $loginLinkDetails->getUrl()]))
            ->text($this->renderView('Default/Mail/magic-link-text.txt.twig', ['link' => $loginLinkDetails->getUrl()]));

        $this->mailer->send($mail);

        $this->addFlash('success', 'Please verify your mailbox');

        return $this->redirectToRoute('app_magic_link_request');
    }
}
```

- [ ] **Step 6: Write the thin `LoginController` and `LogoutController` check routes**

Symfony's `login_link` and `logout` listeners intercept these routes before the controller body runs; the controllers exist only so the routes resolve.

Create `src/Controller/LoginController.php`:
```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class LoginController extends AbstractController
{
    #[Route('/login/check', name: 'app_login_check', methods: ['GET'])]
    public function check(): never
    {
        throw new \LogicException('This should never be reached — the login_link authenticator intercepts the request.');
    }
}
```

Create `src/Controller/LogoutController.php`:
```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;

class LogoutController extends AbstractController
{
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('This should never be reached — the logout listener intercepts the request.');
    }
}
```

- [ ] **Step 7: Update templates for CSRF and `app.user`/`app.flashes`**

In `templates/Default/Blocks/header.html.twig`, replace `{% if showLogout %}` with `{% if app.user %}`.

In `templates/Default/Blocks/flash-messages.html.twig`, replace both `flashes` references so the block reads:
```twig
{# app.flashes empties the flash bag on read (AppVariable::getFlashes() calls
   FlashBag::all(), which is destructive), so it must only be called once per request #}
{% set flashes = app.flashes %}
{% if flashes|length %}
<section class="py-2 text-center container">
{% for label, messages in flashes %}
    {% for message in messages %}
        <div class="alert alert-{{ label }} alert-dismissible fade show" role="alert">
            {{ message }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    {% endfor %}
{% endfor %}
</section>
{% endif %}
```

In `templates/Default/magic-link.html.twig`, replace `<input type="hidden" name="_token" value="{{ _token }}"/>` with `<input type="hidden" name="_token" value="{{ csrf_token('magic-link') }}"/>`.

- [ ] **Step 8: Verify the full flow with a running app and Mailpit**

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/profile/
```
Expected: `302` redirecting to `/send-magic-link` (unauthenticated, `access_control` + entry point).

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/send-magic-link
```
Expected: `200`.

```bash
token=$(curl -s -c /tmp/cookies.txt http://localhost/send-magic-link | grep -o 'name="_token" value="[^"]*"' | sed -E 's/.*value="([^"]*)"/\1/')
curl -s -b /tmp/cookies.txt -c /tmp/cookies.txt -X POST http://localhost/send-magic-link \
  --data-urlencode "email=smoketest@example.com" --data-urlencode "_token=$token"
```
Expected: no error body (redirect response).

```bash
message_id=$(curl -s http://localhost:8025/api/v1/messages | php -r '
$data = json_decode(file_get_contents("php://stdin"), true);
echo $data["messages"][0]["ID"] ?? "";
')
link=$(curl -s "http://localhost:8025/api/v1/message/$message_id" | php -r '
$data = json_decode(file_get_contents("php://stdin"), true);
preg_match("#http://localhost/login/check\?[^\s\"]+#", $data["Text"] ?? "", $m);
echo $m[0] ?? "";
')
echo "$link"
```
Expected: prints a URL starting with `http://localhost/login/check?`. (If Mailpit's API response shape doesn't match, browse `http://localhost:8025` directly and copy the link from the received email instead.)

```bash
curl -s -b /tmp/cookies.txt -c /tmp/cookies.txt -o /dev/null -w '%{http_code}\n' "$link"
```
Expected: `302` to `/profile/`.

```bash
curl -s -b /tmp/cookies.txt -o /dev/null -w '%{http_code}\n' http://localhost/profile/
```
Expected: `500` is acceptable at this point (`HomeController`/`app_profile` route doesn't exist until Task 4) as long as it's not a `302` back to `/send-magic-link` — a 500 here proves authentication succeeded and the firewall let the request through to routing.

```bash
curl -s -c /tmp/cookies-reuse-check.txt -o /dev/null -w '%{http_code}\n' "$link"
```
Run the same login link again with a fresh cookie jar: expected `302` to `/send-magic-link` (the failure handler's "Invalid or expired" flash) — proves `max_uses: 1` works.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: magic-link authentication via Symfony Security's login_link"
```

---

## Task 4: Profile page and pagination

**Files:**
- Create: `src/Controller/HomeController.php`, `src/Pagination/Paginator.php`
- Modify: `templates/Default/home.html.twig`, `config/services.yaml` (bind `$paginationIpp`)

**Interfaces:**
- Consumes: `App\Repository\TrackRepository::countForUser`/`::findPageForUser` (Task 2), `$this->getUser(): App\Entity\User` (Task 3's security config).
- Produces: routes `app_home` (GET `/`), `app_profile` (GET `/profile/`), `app_profile_page` (GET `/profile/{page}`).

- [ ] **Step 1: Bind the pagination page size**

Add to `config/services.yaml` under `services._defaults.bind:` (alongside `$emailFrom` from Task 3):
```yaml
            int $paginationIpp: '%env(int:PAGINATION_IPP)%'
```

- [ ] **Step 2: Port the pagination link-computation class unchanged**

Create `src/Pagination/Paginator.php` (pure presentation logic — no database access, ported as-is from the legacy app under the new namespace):
```php
<?php

namespace App\Pagination;

class Paginator
{
    private int $itemsPerPage;
    private int $items_total;
    private int $current_page;
    private int $num_pages;
    private int $mid_range;

    /** @var array<int|string,mixed> */
    private array $pageLinks;

    private int $default_ipp;
    private string $url;

    /** @var array<string,mixed> */
    private array $params;

    /** @param array<string,mixed> $params */
    public function __construct(string $url, array $params)
    {
        $this->default_ipp = 10;
        $this->current_page = isset($params['page']) ? (int) $params['page'] : 1;
        $this->mid_range = 7;

        if (isset($params['ipp'])) {
            $this->itemsPerPage = (int) $params['ipp'];
        } else {
            $this->itemsPerPage = $this->default_ipp;
            $params['ipp'] = $this->default_ipp;
        }
        $this->pageLinks = [];
        $this->url = $url;
        $this->params = $params;
    }

    public function setItemsTotal(int $totalItems): self
    {
        $this->items_total = $totalItems;

        return $this;
    }

    public function setMidRange(int $midRange): self
    {
        $this->mid_range = $midRange;

        return $this;
    }

    public function paginate(): void
    {
        if ($this->itemsPerPage <= 0) {
            $this->itemsPerPage = $this->default_ipp;
        }
        $this->num_pages = (int) ceil($this->items_total / $this->itemsPerPage);

        if ($this->current_page < 1) {
            $this->current_page = 1;
        }
        if ($this->current_page > $this->num_pages) {
            $this->current_page = $this->num_pages;
        }

        if ($this->num_pages > 10) {
            $this->processMoreThanTenPages();
        } else {
            $this->processLessThanTenPages();
        }
    }

    private function processLessThanTenPages(): void
    {
        $prev_page = $this->current_page - 1;
        $next_page = $this->current_page + 1;

        if ($this->current_page != 1 && $this->items_total >= 10) {
            $this->pageLinks[0] = ['caption' => '« Previous', 'link' => $this->url . '?' . $this->makeParams($prev_page), 'isCurrent' => false];
        } else {
            $this->pageLinks[0] = ['caption' => 'Previous', 'link' => '', 'isCurrent' => false];
        }

        for ($i = 1; $i <= $this->num_pages; $i++) {
            if ($i == $this->current_page) {
                $this->pageLinks[] = ['caption' => (string) $i, 'link' => '', 'isCurrent' => true];
            } else {
                $this->pageLinks[] = ['caption' => (string) $i, 'link' => $this->url . '?' . $this->makeParams($i), 'isCurrent' => false];
            }
        }

        if ($this->current_page != $this->num_pages && $this->items_total >= 10) {
            $this->pageLinks[] = ['caption' => 'Next »', 'link' => $this->url . '?' . $this->makeParams($next_page), 'isCurrent' => false];
        } else {
            $this->pageLinks[] = ['caption' => 'Next', 'link' => '', 'isCurrent' => false];
        }
    }

    private function processMoreThanTenPages(): void
    {
        $prev_page = $this->current_page - 1;
        $next_page = $this->current_page + 1;

        if ($this->current_page != 1 && $this->items_total >= 10) {
            $this->pageLinks[0] = ['caption' => '« Previous', 'link' => $this->url . '?' . $this->makeParams($prev_page), 'isCurrent' => false];
        } else {
            $this->pageLinks[0] = ['caption' => '« Previous', 'link' => '', 'isCurrent' => false];
        }

        $start_range = $this->current_page - floor($this->mid_range / 2);
        $end_range = $this->current_page + floor($this->mid_range / 2);

        if ($start_range <= 0) {
            $end_range += abs($start_range) + 1;
            $start_range = 1;
        }
        if ($end_range > $this->num_pages) {
            $start_range -= $end_range - $this->num_pages;
            $end_range = $this->num_pages;
        }
        $range = range((int) $start_range, (int) $end_range);

        for ($i = 1; $i <= $this->num_pages; $i++) {
            if ($range[0] > 2 && $i == $range[0]) {
                $this->pageLinks[] = ['caption' => '...', 'link' => '', 'isCurrent' => false];
            }

            if ($i == 1 || $i == $this->num_pages || in_array($i, $range)) {
                if ($i == $this->current_page) {
                    $this->pageLinks[] = ['caption' => (string) $i, 'link' => '', 'isCurrent' => true];
                } else {
                    $this->pageLinks[] = ['caption' => (string) $i, 'link' => $this->url . '?' . $this->makeParams($i), 'isCurrent' => false];
                }
            }

            if ($range[$this->mid_range - 1] < $this->num_pages - 1 && $i == $range[$this->mid_range - 1]) {
                $this->pageLinks[] = ['caption' => '...', 'link' => '', 'isCurrent' => false];
            }
        }

        if ($this->current_page != $this->num_pages && $this->items_total >= 10) {
            $this->pageLinks[] = ['caption' => 'Next »', 'link' => $this->url . '?' . $this->makeParams($next_page), 'isCurrent' => false];
        } else {
            $this->pageLinks[] = ['caption' => 'Next »', 'link' => '', 'isCurrent' => false];
        }
    }

    private function makeParams(int $page): string
    {
        $_temp_url = [];
        foreach ($this->params as $key => $value) {
            if ($key == 'page') {
                $_temp_url[] = $key . '=' . $page;
                continue;
            }
            if ($key == 'ipp') {
                continue;
            }
            $_temp_url[] = $key . '=' . $value;
        }
        if (!isset($this->params['page'])) {
            $_temp_url[] = 'page=' . $page;
        }

        return implode('&', $_temp_url);
    }

    /** @return array<string,mixed> */
    public function displayPages(): array
    {
        return $this->pageLinks;
    }
}
```

- [ ] **Step 3: Write `HomeController`**

Create `src/Controller/HomeController.php`:
```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Pagination\Paginator;
use App\Repository\TrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly int $paginationIpp,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/', name: 'app_profile', methods: ['GET'])]
    #[Route('/profile/{page}', name: 'app_profile_page', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function profile(Request $request, int $page = 1): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $total = $this->trackRepository->countForUser($user);
        $tracks = $this->trackRepository->findPageForUser($user, ($page - 1) * $this->paginationIpp, $this->paginationIpp);

        $paginator = new Paginator($request->getPathInfo(), ['page' => $page, 'ipp' => $this->paginationIpp]);
        $paginator->setItemsTotal($total);
        $paginator->setMidRange(3);
        $paginator->paginate();

        return $this->render('Default/home.html.twig', [
            'tracks' => $tracks,
            'pages' => $paginator->displayPages(),
        ]);
    }
}
```

- [ ] **Step 4: Update `home.html.twig` for entities and the pagination partial**

Replace the whole `{% if paginatedTracks.tracks %} ... {% endif %}` table block's iteration and the footer:
- `{% if paginatedTracks.tracks %}` → `{% if tracks %}`
- `{% for track in paginatedTracks.tracks %}` → `{% for track in tracks %}`
- `{{ paginatedTracks.template| raw }}` → `{{ include('Default/Blocks/pagination.html.twig', {pages: pages}) }}`

Replace `<input type="hidden" name="_token" value="{{ _token }}"/>` (in the upload form) with `<input type="hidden" name="_token" value="{{ csrf_token('track-upload') }}"/>`.

- [ ] **Step 5: Verify**

```bash
curl -s -b /tmp/cookies.txt http://localhost/profile/ -o /tmp/profile.html -w '%{http_code}\n'
```
Expected: `200`. `grep -c "No tracks yet" /tmp/profile.html` should be `1` (empty state, since Task 5 hasn't added upload yet).

```bash
curl -s -b /tmp/cookies.txt -o /dev/null -w '%{http_code}\n' http://localhost/profile/2
```
Expected: `200` (page 2 of zero tracks renders the empty state too — `Paginator` clamps `current_page` to `num_pages`, and `num_pages` is `0` when there are no tracks, which the ported class already handles the same way the legacy app did).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: profile page with paginated track list"
```

---

## Task 5: GPX track upload

**Files:**
- Create: `src/Controller/UploadController.php`, `src/Gps/GpsTrack.php`, `src/Service/GpxValidator.php`, `src/Validator/XmlValidator.php`, `src/Service/FileUploaderService.php`, `src/Exception/GpxValidationException.php`
- Modify: `config/services.yaml` (bind `$gpxSchemaPath`, `$dataPath`)

**Interfaces:**
- Consumes: `App\Entity\Track` (Task 2 constructor `__construct(User $user, string $name, string $filename)` and setters), `$this->getUser(): App\Entity\User` (Task 3).
- Produces: route `app_track_upload` (POST `/track/upload`), `App\Service\FileUploaderService::getUserDataPath(User): string` — Tasks 6 and 7 reuse this exact method to locate a track's file on disk.

- [ ] **Step 1: Bind the GPX schema and data paths**

Add to `config/services.yaml` under `services._defaults.bind:`:
```yaml
            string $gpxSchemaPath: '%kernel.project_dir%/src/Schemas/gpx.xsd'
            string $dataPath: '%kernel.project_dir%/var/data'
```

- [ ] **Step 2: Write the exception type**

Create `src/Exception/GpxValidationException.php`:
```php
<?php

namespace App\Exception;

class GpxValidationException extends \RuntimeException
{
}
```

- [ ] **Step 3: Port `GpsTrack` unchanged**

Create `src/Gps/GpsTrack.php` (framework-agnostic GPX-distance/elevation calculator, ported verbatim under the new namespace):
```php
<?php

namespace App\Gps;

use phpGPX\Models\GpxFile;
use phpGPX\Models\Point;
use phpGPX\phpGPX;

class GpsTrack
{
    private phpGPX $gpx;
    private GpxFile $gpxFile;

    /** @var array<int,array<string,mixed>> */
    private array $data = [];

    private float $totalDistance = 0.0;
    private float $vDistance = 0.0;

    public function __construct()
    {
        $this->gpx = new phpGPX();
    }

    /** @return array<int,array<string,mixed>> */
    public function getPoints(): array
    {
        return $this->data;
    }

    public function getJsonPoints(): string
    {
        return (string) json_encode($this->data);
    }

    public function process(string $filename): void
    {
        $this->data = [];
        $this->gpxFile = $this->gpx->load($filename);

        $carryHDistance = 0.0;
        foreach ($this->gpxFile->tracks as $track) {
            foreach ($track->segments as $segment) {
                $carryHDistance = 0.0;
                foreach ($segment->points as $key => $point) {
                    if (!isset($segment->points[$key + 1])) {
                        break;
                    }
                    $endPoint = $segment->points[$key + 1];
                    $parseDiffPoints = $this->parseDiffPoints($point, $endPoint, $carryHDistance);
                    $this->data[] = $parseDiffPoints;
                    $this->vDistance += $parseDiffPoints['vDistance'] > 0 ? $parseDiffPoints['vDistance'] : 0.0;
                }
            }
        }

        $this->totalDistance = $carryHDistance;
    }

    /** @return array<string,mixed> */
    private function parseDiffPoints(Point $start, Point $end, float &$carryHDistance): array
    {
        $hDistance = $this->distance($start, $end);
        $vDistance = abs($hDistance) >= 1 ? $end->elevation - $start->elevation : 0.0;
        $carryHDistance += $hDistance;

        return [
            'latitude' => $start->latitude,
            'longitude' => $start->longitude,
            'distance' => $hDistance,
            'elevation' => $start->elevation,
            'totalDistance' => $carryHDistance,
            'vDistance' => $vDistance,
        ];
    }

    // From https://www.movable-type.co.uk/scripts/latlong.html
    private function distance(Point $start, Point $end): float
    {
        $R = 6371e3;
        $fi1 = $start->latitude * M_PI / 180;
        $fi2 = $end->latitude * M_PI / 180;
        $deltaFi = ($end->latitude - $start->latitude) * M_PI / 180;
        $deltaLambda = ($end->longitude - $start->longitude) * M_PI / 180;

        $a = sin($deltaFi / 2) * sin($deltaFi / 2) +
            cos($fi1) * cos($fi2) *
            sin($deltaLambda / 2) * sin($deltaLambda / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    /** @return array{points:int,totalDistance:string,totalHeight:string} */
    public function getInfo(): array
    {
        return [
            'points' => count($this->data),
            'totalDistance' => sprintf('%.02f', $this->totalDistance / 1000),
            'totalHeight' => sprintf('%.02f', $this->vDistance),
        ];
    }
}
```

- [ ] **Step 4: Port `GpxValidator` and `XmlValidator` unchanged (only the exception's namespace changes)**

Create `src/Service/GpxValidator.php`:
```php
<?php

namespace App\Service;

use App\Exception\GpxValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GpxValidator
{
    private const MAX_FILE_SIZE = 10485760;
    private const ALLOWED_MIME_TYPES = ['application/gpx+xml', 'application/xml', 'text/xml'];
    private const GPX_NAMESPACE = 'http://www.topografix.com/GPX/1/1';

    public function validate(UploadedFile $file): void
    {
        $this->validateFileSize($file);
        $this->validateMimeType($file);
        $this->validateXmlStructure($file);
        $this->validateGpxContent($file);
    }

    private function validateFileSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new GpxValidationException('File size exceeds maximum allowed size of 10MB');
        }
    }

    private function validateMimeType(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new GpxValidationException('Invalid file type. Only GPX files are allowed.');
        }
    }

    private function validateXmlStructure(UploadedFile $file): void
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file->getPathname());

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new GpxValidationException('Invalid XML structure: ' . $errors[0]->message);
        }
    }

    private function validateGpxContent(UploadedFile $file): void
    {
        $dom = new \DOMDocument();
        $dom->load($file->getPathname());

        if (!$this->isValidGpxNamespace($dom)) {
            throw new GpxValidationException('Invalid GPX namespace');
        }

        if (!$this->hasValidTracks($dom)) {
            throw new GpxValidationException('No valid track data found in GPX file');
        }
    }

    private function isValidGpxNamespace(\DOMDocument $dom): bool
    {
        $root = $dom->documentElement;

        return $root && $root->namespaceURI === self::GPX_NAMESPACE;
    }

    private function hasValidTracks(\DOMDocument $dom): bool
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('gpx', self::GPX_NAMESPACE);

        $tracks = $xpath->query('//gpx:trk | //gpx:rte');
        if ($tracks->length === 0) {
            return false;
        }

        $points = $xpath->query('//gpx:trkpt | //gpx:rtept');

        return $points->length > 0;
    }
}
```

Create `src/Validator/XmlValidator.php`:
```php
<?php

namespace App\Validator;

class XmlValidator
{
    public function isValid(string $xmlDocument, string $schema): bool
    {
        libxml_use_internal_errors(true);

        $xml = new \DOMDocument();
        $xml->loadXML($xmlDocument);

        $isValid = $xml->schemaValidateSource($schema);
        libxml_clear_errors();

        return $isValid;
    }
}
```

(The legacy `getErrors()`/`libxmlDisplayErrors()` machinery is dropped — nothing ever called `getErrors()` on this class; `UploadController` only used the boolean result.)

- [ ] **Step 5: Write `FileUploaderService`**

Create `src/Service/FileUploaderService.php`:
```php
<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploaderService
{
    public function __construct(
        private readonly string $dataPath,
    ) {
    }

    public function getUserDataPath(User $user): string
    {
        return sprintf('%s/profile-%03d', $this->dataPath, (int) $user->getId());
    }

    public function uploadFile(User $user, UploadedFile $file, string $targetFileName): bool
    {
        $userFolder = $this->getUserDataPath($user);

        if (!is_dir($userFolder) && !mkdir($userFolder, 0775, true) && !is_dir($userFolder)) {
            return false;
        }

        try {
            $file->move($userFolder, $targetFileName);
        } catch (\Throwable) {
            return false;
        }

        return true;
    }
}
```

- [ ] **Step 6: Write `UploadController`**

Create `src/Controller/UploadController.php`:
```php
<?php

namespace App\Controller;

use App\Entity\Track;
use App\Entity\User;
use App\Exception\GpxValidationException;
use App\Gps\GpsTrack;
use App\Service\FileUploaderService;
use App\Service\GpxValidator;
use App\Validator\XmlValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UploadController extends AbstractController
{
    public function __construct(
        private readonly XmlValidator $xmlValidator,
        private readonly GpxValidator $gpxValidator,
        private readonly FileUploaderService $fileUploaderService,
        private readonly GpsTrack $gpsTrack,
        private readonly EntityManagerInterface $entityManager,
        private readonly string $gpxSchemaPath,
    ) {
    }

    #[Route('/track/upload', name: 'app_track_upload', methods: ['POST'])]
    public function uploadTrack(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('track-upload', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        /** @var UploadedFile|null $file */
        $file = $request->files->get('trackFile');
        if (!$file) {
            $this->addFlash('danger', 'No file was uploaded');

            return $this->redirectToRoute('app_profile');
        }

        $trackName = trim(htmlspecialchars((string) $request->request->get('trackName', '')));
        if ($trackName === '') {
            $this->addFlash('danger', 'Track name is required');

            return $this->redirectToRoute('app_profile');
        }

        try {
            $this->assertValidGpxFile($file);
        } catch (GpxValidationException $e) {
            $this->addFlash('danger', $e->getMessage());

            return $this->redirectToRoute('app_profile');
        }

        /** @var User $user */
        $user = $this->getUser();
        $targetFileName = uniqid() . '.gpx';

        if (!$this->fileUploaderService->uploadFile($user, $file, $targetFileName)) {
            $this->addFlash('danger', 'Unable to upload the file');

            return $this->redirectToRoute('app_profile');
        }

        $trackFilePath = $this->fileUploaderService->getUserDataPath($user) . '/' . $targetFileName;
        $this->gpsTrack->process($trackFilePath);
        $trackInfo = $this->gpsTrack->getInfo();

        $track = new Track($user, $trackName, $targetFileName);
        $track->setTotalPoints($trackInfo['points']);
        $track->setElevation((float) $trackInfo['totalHeight']);
        $track->setDistance((float) $trackInfo['totalDistance']);

        $this->entityManager->persist($track);
        $this->entityManager->flush();

        $this->addFlash('success', 'New file uploaded');

        return $this->redirectToRoute('app_profile');
    }

    private function assertValidGpxFile(UploadedFile $file): void
    {
        try {
            $isValidXml = $this->xmlValidator->isValid(
                (string) file_get_contents($file->getPathname()),
                (string) file_get_contents($this->gpxSchemaPath)
            );

            if (!$isValidXml) {
                throw new GpxValidationException('Invalid GPX file format');
            }

            $this->gpxValidator->validate($file);
        } catch (GpxValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new GpxValidationException('Error validating GPX file: ' . $e->getMessage());
        }
    }
}
```

- [ ] **Step 7: Verify with the real fixture files**

```bash
curl -s -o /tmp/reload.html -b /tmp/cookies.txt -c /tmp/cookies.txt http://localhost/profile/
csrf=$(grep -o 'name="_token" value="[^"]*"' /tmp/reload.html | sed -E 's/.*value="([^"]*)"/\1/')
docker compose cp tests/Fixtures/sample.gpx app:/tmp/sample.gpx
curl -s -b /tmp/cookies.txt -X POST http://localhost/track/upload \
  -F "_token=$csrf" -F "trackName=Smoke test track" -F "trackFile=@tests/Fixtures/sample.gpx;type=application/gpx+xml"
```
Expected: no error body.

```bash
docker compose exec -T -u www-data app bin/console dbal:run-sql "SELECT name, filename, total_points, elevation, distance FROM tracks"
```
Expected: one row for "Smoke test track" with a real filename and non-null numeric stats.

```bash
docker compose exec -T -u www-data app find var/data -type f
```
Expected: the uploaded `.gpx` file exists under `var/data/profile-001/`.

```bash
curl -s -b /tmp/cookies.txt -X POST http://localhost/track/upload \
  -F "_token=$csrf" -F "trackName=Invalid" -F "trackFile=@tests/Fixtures/invalid-sample.gpx;type=application/gpx+xml"
curl -s -b /tmp/cookies.txt http://localhost/profile/ | grep -c "Smoke test track"
```
Expected: still `1` — the invalid file was rejected and not persisted (confirm no second row was added via the `dbal:run-sql` query above).

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: GPX track upload with validation"
```

---

## Task 6: Map view and track info/delete

**Files:**
- Create: `src/Controller/MapController.php`, `src/Controller/TrackController.php`
- Modify: `templates/Default/map.html.twig` (no changes needed — verify only), `templates/Default/track.html.twig`

**Interfaces:**
- Consumes: `App\Repository\TrackRepository::findOneByKey` (Task 2), `App\Service\FileUploaderService::getUserDataPath` (Task 5), `App\Gps\GpsTrack::process`/`getJsonPoints`/`getInfo` (Task 5).
- Produces: routes `app_track_map` (GET `/map/{trackKey}`), `app_track_info` (GET `/track/info/{trackKey}`), `app_track_delete` (POST `/track/delete`).

- [ ] **Step 1: Write `MapController`**

Create `src/Controller/MapController.php`:
```php
<?php

namespace App\Controller;

use App\Gps\GpsTrack;
use App\Repository\TrackRepository;
use App\Service\FileUploaderService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends AbstractController
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly FileUploaderService $fileUploaderService,
        private readonly GpsTrack $gpsTrack,
    ) {
    }

    #[Route('/map/{trackKey}', name: 'app_track_map', methods: ['GET'])]
    public function index(string $trackKey): Response
    {
        $track = $this->trackRepository->findOneByKey($trackKey);
        if (!$track) {
            $this->addFlash('danger', 'Track file does not exist');

            return $this->redirectToRoute('app_home');
        }

        if ($track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'Track does not exist');

            return $this->redirectToRoute('app_profile');
        }

        $trackFilePath = $this->fileUploaderService->getUserDataPath($track->getUser()) . '/' . $track->getFilename();
        if (!file_exists($trackFilePath)) {
            $this->addFlash('danger', 'Track file does not exist');

            return $this->redirectToRoute('app_profile');
        }

        $this->gpsTrack->process($trackFilePath);

        return $this->render('Default/map.html.twig', [
            'title' => $track->getName(),
            'points' => $this->gpsTrack->getJsonPoints(),
            'info' => $this->gpsTrack->getInfo(),
        ]);
    }
}
```

`map.html.twig` already reads `title`, `points` (via `{{ points|json_encode|raw }}` — note `points` is already a JSON string from `getJsonPoints()`, and the template re-encodes it; this double-encoding is pre-existing legacy behavior, ported unchanged since the map still renders correctly with it — `JSON.parse` on a JSON-encoded JSON string of an already-JSON string still round-trips correctly) and `info.points`/`info.totalDistance`/`info.totalHeight` — no template changes needed here.

- [ ] **Step 2: Write `TrackController`**

Create `src/Controller/TrackController.php`:
```php
<?php

namespace App\Controller;

use App\Repository\TrackRepository;
use App\Service\FileUploaderService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TrackController extends AbstractController
{
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly FileUploaderService $fileUploaderService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/track/info/{trackKey}', name: 'app_track_info', methods: ['GET'])]
    public function index(string $trackKey): Response
    {
        $track = $this->trackRepository->findOneByKey($trackKey);
        if (!$track || $track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'Track does not exist');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('Default/track.html.twig', ['track' => $track]);
    }

    #[Route('/track/delete', name: 'app_track_delete', methods: ['POST'])]
    public function deleteTrack(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('track-delete', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $trackKey = (string) $request->request->get('track_key');
        $track = $this->trackRepository->findOneByKey($trackKey);

        if (!$track || $track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'Track does not exist!');

            return $this->redirectToRoute('app_profile');
        }

        $trackFilePath = $this->fileUploaderService->getUserDataPath($track->getUser()) . '/' . $track->getFilename();
        if (file_exists($trackFilePath)) {
            unlink($trackFilePath);
        }

        $this->entityManager->remove($track);
        $this->entityManager->flush();

        $this->addFlash('success', 'Track deleted');

        return $this->redirectToRoute('app_profile');
    }
}
```

- [ ] **Step 3: Update `track.html.twig` for the `track` entity and CSRF**

Replace all of: `{{ trackName }}` → `{{ track.name }}`; `{{ points }}` → `{{ track.totalPoints }}`; `{{ distance }}` → `{{ track.distance }}`; `{{ elevation }}` → `{{ track.elevation }}`; `{{ createdAt }}` → `{{ track.createdAt|date('c') }}`; `{{ trackKey }}` → `{{ track.key }}` (both occurrences, in the two `<input type="hidden" name="track_key">` fields); both `<input type="hidden" name="_token" value="{{ _token }}"/>` → `<input type="hidden" name="_token" value="{{ csrf_token('track-delete') }}"/>`.

Also fix `home.html.twig`'s track-list links, which already use `track.key` for `/map/{{ track.key }}` and `/track/info/{{track.key}}` — those already work unchanged against a `Track` entity (Twig's `.key` accessor calls `getKey()` on either an array or an object transparently), no edit needed there.

- [ ] **Step 4: Verify**

```bash
key=$(docker compose exec -T -u www-data app bin/console dbal:run-sql "SELECT key FROM tracks LIMIT 1" | grep -o '"[a-f0-9-]\{36\}"' | tr -d '"')
curl -s -b /tmp/cookies.txt -o /dev/null -w '%{http_code}\n' "http://localhost/track/info/$key"
```
Expected: `200`.

```bash
curl -s -b /tmp/cookies.txt -o /dev/null -w '%{http_code}\n' "http://localhost/map/$key"
```
Expected: `200`.

```bash
csrf=$(curl -s -b /tmp/cookies.txt "http://localhost/track/info/$key" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed -E 's/.*value="([^"]*)"/\1/')
curl -s -b /tmp/cookies.txt -X POST http://localhost/track/delete --data-urlencode "track_key=$key" --data-urlencode "_token=$csrf"
docker compose exec -T -u www-data app bin/console dbal:run-sql "SELECT COUNT(*) FROM tracks"
```
Expected: `0` rows remain, and `docker compose exec -T -u www-data app find var/data -type f` shows the uploaded file is gone too.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: map view and track info/delete"
```

---

## Task 7: Error pages, security headers, and country restriction

**Files:**
- Create: `templates/bundles/TwigBundle/Exception/error404.html.twig`, `templates/bundles/TwigBundle/Exception/error403.html.twig`, `templates/bundles/TwigBundle/Exception/error500.html.twig`, `src/EventListener/SecurityHeadersListener.php`, `src/EventListener/CountryRestrictionListener.php`, `src/Service/IpApiService.php`
- Modify: `config/services.yaml` (bind `$allowCountryCode`)

**Interfaces:**
- Consumes: nothing from earlier feature tasks — these are global, cross-cutting listeners on `kernel.request`/`kernel.response`.
- Produces: nothing consumed by later tasks — this is the last feature-shaped task before cleanup.

- [ ] **Step 1: Write the three error templates**

Create `templates/Error/base.html.twig` was already moved in Task 1 unchanged; reuse it. Create `templates/bundles/TwigBundle/Exception/error404.html.twig`:
```twig
{% extends "Error/base.html.twig" %}

{% block content %}
<div class="container my-5">
    <div class="p-5 text-center bg-body-tertiary rounded-3">
        <h1 class="text-body-emphasis">404 Page not found</h1>
        <p class="col-lg-8 mx-auto fs-5 text-muted">
            Sorry the page you are looking for doesn't exist...
        </p>
        <div class="d-inline-flex gap-2 mb-5">
            <a href="/" class="d-inline-flex align-items-center btn btn-primary btn-lg px-4 rounded-pill">
                Home page
            </a>
        </div>
    </div>
</div>
{% endblock %}
```

Create `templates/bundles/TwigBundle/Exception/error403.html.twig`:
```twig
{% extends "Error/base.html.twig" %}

{% block content %}
Invalid security token
{% endblock %}
```

Create `templates/bundles/TwigBundle/Exception/error500.html.twig`:
```twig
{% extends "Error/base.html.twig" %}

{% block content %}
An unexpected error occurred. Please try again later.
{% endblock %}
```

These paths are Symfony's built-in convention (`templates/bundles/TwigBundle/Exception/error{statusCode}.html.twig`) — no controller or routing wiring is needed; the framework's default `error_controller` picks them up automatically by HTTP status code.

- [ ] **Step 2: Write `SecurityHeadersListener`**

Create `src/EventListener/SecurityHeadersListener.php`:
```php
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::RESPONSE)]
class SecurityHeadersListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; "
            . "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net https://unpkg.com; "
            . "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com; "
            . "font-src 'self' https://fonts.gstatic.com; "
            . "img-src 'self' data: https://*.tile.openstreetmap.org; "
            . "connect-src 'self' https://unpkg.com; "
            . "frame-ancestors 'none'"
        );
    }
}
```

- [ ] **Step 3: Port `IpApiService`**

Create `src/Service/IpApiService.php`:
```php
<?php

namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class IpApiService
{
    private const API_URL = 'http://ip-api.com/json/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getCountryByIp(string $ipAddress): ?string
    {
        $cacheItem = $this->cache->getItem('ip_country_code_' . str_replace(['.', ':'], '_', $ipAddress));
        if ($cacheItem->isHit()) {
            $this->logger->info('cached: ' . $ipAddress);

            return $cacheItem->get();
        }

        try {
            $response = $this->httpClient->request('POST', self::API_URL . $ipAddress);
            $data = json_decode($response->getContent(), true);

            if (isset($data['status']) && $data['status'] === 'success' && isset($data['countryCode'])) {
                $cacheItem->set($data['countryCode'])->expiresAfter(86400);
                $this->cache->save($cacheItem);
                $this->logger->info('Get country code: ' . $ipAddress . ' - ' . $data['countryCode']);

                return $data['countryCode'];
            }

            $this->logger->warning('Could not get country code: ' . $ipAddress . ' - ' . ($data['message'] ?? 'unknown'));
            $cacheItem->set(null)->expiresAfter(432000);
            $this->cache->save($cacheItem);

            return null;
        } catch (\Throwable $e) {
            $this->logger->error($e->getMessage());

            return null;
        }
    }
}
```

(Cache keys can't contain `.`/`:` under PSR-6 — the legacy `Cache` class used Symfony's `FilesystemAdapter` directly, which is more permissive than the PSR-6 key-character restriction that `cache.app` enforces; the key is sanitized here to stay PSR-6-valid.)

- [ ] **Step 4: Write `CountryRestrictionListener`**

Add to `config/services.yaml` under `services._defaults.bind:`:
```yaml
            string $allowCountryCode: '%env(ALLOW_COUNTRY_CODE)%'
```

Create `src/EventListener/CountryRestrictionListener.php`:
```php
<?php

namespace App\EventListener;

use App\Service\IpApiService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class CountryRestrictionListener
{
    public function __construct(
        private readonly IpApiService $ipApiService,
        private readonly string $allowCountryCode,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || $this->allowCountryCode === '') {
            return;
        }

        $countryCode = $this->ipApiService->getCountryByIp((string) $event->getRequest()->getClientIp()) ?? '';
        if (strtolower($countryCode) !== strtolower($this->allowCountryCode)) {
            $event->setResponse(new Response('Forbidden', Response::HTTP_FORBIDDEN));
        }
    }
}
```

No extra service config is needed beyond the `#[Autowire(service: 'cache.app')]` attribute already on `IpApiService`'s constructor above.

- [ ] **Step 5: Verify**

```bash
curl -s -D - -o /dev/null http://localhost/send-magic-link | grep -i "x-frame-options\|content-security-policy"
```
Expected: both headers present with the exact values above.

```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/_error/404
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/_error/403
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/_error/500
```
Expected: `404`, `403`, `500` respectively, each rendering the corresponding custom template (Symfony's built-in dev-environment error preview route). Confirm content: `curl -s http://localhost/_error/404 | grep -c "Page not found"` → `1`.

`ALLOW_COUNTRY_CODE` is empty by default so no behavior change is expected on any route above — confirm with `curl -s -o /dev/null -w '%{http_code}\n' http://localhost/send-magic-link` still returning `200`.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: error pages, security headers, and country restriction"
```

---

## Task 8: Cleanup, documentation, and full smoke verification

**Files:**
- Modify: `CLAUDE.md`, `Makefile` (only if a gap is found), `composer.json` (script cleanup)

**Interfaces:** none — this task only verifies and documents what Tasks 1–7 built.

- [ ] **Step 1: Remove now-dead composer scripts**

Edit `composer.json`'s `"scripts"` block: remove `"static-analysis"` and `"tests"` (they invoke `phpcs`/`phpstan`/`codecept`, none of which are part of this plan — `phpcs`/`phpstan`/`codeception` remain valid dev dependencies to keep or remove is a separate, later decision, not part of this rewrite). Keep `"copy-assets"` unchanged — `bin/copy-assets.php` still copies `src/Resources/{css,js,plugins,images}` into `public/`, untouched by this plan.

- [ ] **Step 2: Rewrite `CLAUDE.md`'s architecture-specific sections**

Update the **Setup**, **Commands**, and **Architecture** sections of `CLAUDE.md` to describe the Symfony app: `docker compose exec -u www-data app bin/console` replaces the custom CLI; `bin/console doctrine:migrations:migrate` replaces `bin/console migration:apply`; there is no more `t:g`/DTO generation step; routing is via `#[Route]` attributes in `src/Controller/*.php`, not `src/Routes/Web.php`; persistence is Doctrine ORM entities in `src/Entity/`, not hand-written DBAL repositories; auth is Symfony Security's `login_link` firewall (`config/packages/security.yaml`), not `GateKeeper`. Remove the entire **Testing** section (no test suite exists after this plan) and the `composer tests`/`phpcs`/`phpstan` command references.

- [ ] **Step 3: Full route-by-route smoke test against the spec's inventory**

Restart clean to prove nothing relies on leftover state from earlier tasks' manual testing:
```bash
docker compose exec -T -u www-data app rm -f var/database/database.sqlite
docker compose exec -T -u www-data app bash -c 'rm -rf var/data/profile-*'
docker compose exec -T -u www-data app bin/console doctrine:database:create
docker compose exec -T -u www-data app bin/console doctrine:migrations:migrate --no-interaction
```

Re-run, in order, the verification sequences from Task 3 Step 8 (request a link, click it via Mailpit), Task 4 Step 5 (profile page), Task 5 Step 7 (upload `tests/Fixtures/sample.gpx`), and Task 6 Step 4 (map, info, delete) against this fresh database, confirming every one still passes.

Additionally confirm the two routes not yet individually re-verified:
```bash
curl -s -o /dev/null -w '%{http_code}\n' http://localhost/logout
```
Expected (with a valid session cookie): `302` to `/send-magic-link`, and a subsequent `curl -b /tmp/cookies.txt http://localhost/profile/` redirects to `/send-magic-link` too (session cleared).

```bash
docker compose exec -T -u www-data app bin/console debug:router
```
Expected: exactly the 11 application routes from the spec's route inventory (`app_home`, `app_magic_link_request`, `app_magic_link_send`, `app_profile`, `app_profile_page`, `app_login_check`, `app_logout`, `app_track_map`, `app_track_upload`, `app_track_info`, `app_track_delete`), plus Symfony's own framework routes (`_wdt`, `_profiler`, `_error_preview`, etc. — those are fine, they're dev-only).

- [ ] **Step 4: Confirm no legacy code remains**

```bash
grep -rl "PixelTrack" src/ templates/ composer.json || echo "clean"
```
Expected: `clean`.

```bash
docker compose exec -T -u www-data app composer audit
```
Expected: `No security vulnerability advisories found`.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "docs: update CLAUDE.md for the Symfony architecture; final cleanup"
```
