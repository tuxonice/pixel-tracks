# Localization (i18n) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add English (default) + Portuguese localization to pixel-tracks: translated UI strings, translated transactional emails, and locale-prefixed translated-slug URLs (e.g. `/en/profile/` vs `/pt/perfil/`) for every user-facing page, using Symfony's built-in localized routing rather than a custom routing layer.

**Architecture:** `#[Route]` attributes on the 5 user-facing GET routes get locale-keyed `path` arrays; Symfony's own router/URL generator resolve the correct variant from the current request automatically. `access_control`'s path-regex rules are replaced by `#[IsGranted('PUBLIC_ACCESS')]` attributes on the two public controllers. All hardcoded strings move to `translations/messages.{en,pt}.yaml`. A new `User.locale` column persists each user's language for out-of-band email rendering.

**Tech Stack:** Symfony 7.4 (`symfony/translation`, Symfony's native i18n routing, `#[IsGranted]`), Doctrine ORM migration, Twig `|trans`.

**Spec:** `docs/superpowers/specs/2026-09-21-localization-design.md`

## Global Constraints

- Two locales only: `en` (default) and `pt`. `framework.yaml`: `default_locale: 'en'`, `enabled_locales: ['en', 'pt']`.
- Localized (translated-slug) routes: `app_profile`, `app_profile_page`, `app_magic_link_request`, `app_magic_link_send`, `app_track_info`, `app_track_map`. Everything else (`app_home`, `app_login_check`, `app_logout`, `app_track_upload`, `app_track_delete`) stays a single, unprefixed route.
- `templates/Default/Mail/*.twig` uses the recipient's *stored* locale (via the current request's locale at send time, which is the same thing in this app's synchronous flow — see Task 8); `templates/Default/map.html.twig` is out of scope entirely and must not be touched.
- No automated test suite exists in this project (per `CLAUDE.md`) — every task's verification is a manual `curl`/`grep`/console check against the running Docker dev stack, not a unit/functional test.
- The app runs via Docker Compose (`make start`); `bin/console`/`composer` commands run inside the container via `docker compose exec -u www-data app <command>`.
- Every `#[Route]` change must keep working GET/POST behavior identical for both locales — only the path format grows a locale dimension, nothing about auth/CSRF/behavior changes.

---

## Verification helper: authenticating for `curl` checks against localized routes

Reused throughout this plan (adapted from the pattern used in the earlier Bootstrap 5 migration). Because `/send-magic-link` becomes locale-prefixed partway through this plan (Task 4), **use whichever of the two forms matches the task you're on**:

**Before Task 4 lands** (routes not yet localized):
```bash
scratch=/tmp/pixel-tracks-verify
mkdir -p "$scratch"
jar="$scratch/cookies.txt"
rm -f "$jar"
form=$(curl -sS -c "$jar" http://localhost/send-magic-link)
token=$(echo "$form" | grep -oP 'name="_token" value="\K[^"]+')
curl -sS -b "$jar" -c "$jar" -o /dev/null -d "email=i18n-verify@example.com" -d "_token=$token" http://localhost/send-magic-link
```

**After Task 4 lands** (routes localized — example for English; swap `/en/` for `/pt/` and the address for a different one to test Portuguese, since the magic-link rate limiter is per-email too):
```bash
scratch=/tmp/pixel-tracks-verify
mkdir -p "$scratch"
jar="$scratch/cookies.txt"
rm -f "$jar"
form=$(curl -sS -c "$jar" http://localhost/en/send-magic-link)
token=$(echo "$form" | grep -oP 'name="_token" value="\K[^"]+')
curl -sS -b "$jar" -c "$jar" -o /dev/null -d "email=i18n-verify-en@example.com" -d "_token=$token" http://localhost/en/send-magic-link
```

**Then, both cases**, pull the link from Mailpit and follow it:
```bash
msg_id=$(curl -sS "http://localhost:8125/api/v1/messages" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
echo $d["messages"][0]["ID"] ?? "";
')
link=$(curl -sS "http://localhost:8125/api/v1/message/$msg_id" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
$text = $d["Text"] ?? $d["HTML"] ?? "";
if (preg_match("#http://localhost/login/check\?[^\s\"<]+#", $text, $m)) { echo $m[0]; }
')
curl -sS -b "$jar" -c "$jar" -o /dev/null "$link"
```
`$jar` now holds an authenticated session cookie. `/login/check` itself is never localized, so that regex doesn't need adjusting once Task 4 lands.

**Rate limiter note** (also hit during the Bootstrap 5 work): magic-link requests are rate-limited per-IP and per-email (token bucket, capacity 5, refills every 50s per `RATE_LIMITER_REFILL_PERIOD`). Authenticate once per task and reuse the cookie jar for all of that task's checks; use a distinct email address per task if a fresh magic link is needed later.

---

### Task 1: Translation infrastructure

**Files:**
- Modify: `composer.json`, `composer.lock` (via `composer require`)
- Modify: `config/packages/framework.yaml`
- Create/modify: `config/packages/translation.yaml` (scaffolded by the Flex recipe)
- Create: `translations/messages.en.yaml`
- Create: `translations/messages.pt.yaml`

**Interfaces:**
- Produces: a working `TranslatorInterface` service and `translations/messages.{en,pt}.yaml` catalogue files that every later task adds keys to and consumes via Twig's `|trans` filter or `$translator->trans()`.

- [ ] **Step 1: Require the translation component, pinned to match this project's other Symfony packages**

```bash
docker compose exec -u www-data app composer require symfony/translation:7.4.19
```

- [ ] **Step 2: Confirm/adjust the Flex-generated `config/packages/translation.yaml`**

It should read (adjust if the recipe generated something different):

```yaml
framework:
    default_locale: en
    translator:
        default_path: '%kernel.project_dir%/translations'
        fallbacks:
            - en
```

If Flex instead added `default_locale`/`translator` directly into `config/packages/framework.yaml`, that's fine too — the point is both settings exist somewhere under `framework:`. Do not duplicate the `default_locale` key across two files; keep it in whichever file Flex chose and remove it from the other if both got one.

- [ ] **Step 3: Add `enabled_locales` to `config/packages/framework.yaml`**

Add this line inside the top-level `framework:` block (anywhere alongside the existing `secret`/`trusted_hosts`/etc. keys):

```yaml
    enabled_locales: ['en', 'pt']
```

- [ ] **Step 4: Create `translations/messages.en.yaml`**

```yaml
nav:
    profile: Profile
    about: About
    logout: Logout
```

- [ ] **Step 5: Create `translations/messages.pt.yaml`**

```yaml
nav:
    profile: Perfil
    about: Sobre
    logout: Sair
```

(These three keys are a real, minimal starting catalogue — not a throwaway smoke-test key — used by Task 5 when it translates the header. Later tasks each add their own keys to these same two files.)

- [ ] **Step 6: Verify**

```bash
docker compose exec -u www-data app bin/console debug:translation en | head -20
docker compose exec -u www-data app bin/console debug:translation pt | head -20
docker compose exec -u www-data app bin/console debug:config framework translator
```

Expected: both `debug:translation` runs list the 3 `nav.*` keys with no "missing translation" markers for either locale; `debug:config` shows `default_locale: en` and confirms the translator is configured with `default_path` pointing at `translations/`.

```bash
docker compose exec -u www-data app bin/console debug:config framework enabled_locales
```

Expected: `['en', 'pt']`.

- [ ] **Step 7: Commit**

```bash
git add composer.json composer.lock config/packages/framework.yaml config/packages/translation.yaml translations/
git commit -m "feat(i18n): add translation infrastructure (en/pt)"
```

---

### Task 2: Persisted per-user locale

**Files:**
- Modify: `src/Entity/User.php`
- Create: `migrations/Version<generated-timestamp>.php` (via `doctrine:migrations:diff`)

**Interfaces:**
- Produces: `User::getLocale(): string` / `User::setLocale(string $locale): void`, used by Task 8 (`MagicLinkController::sendMagicLink` persists the requester's locale) and available for any future use of a stored language preference.

- [ ] **Step 1: Add the `locale` property to `src/Entity/User.php`**

Add this property (near the other `#[ORM\Column]` properties):

```php
    #[ORM\Column(length: 5)]
    private string $locale = 'en';
```

Add these two methods (near the other getters):

```php
    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }
```

- [ ] **Step 2: Generate the migration from the entity diff**

```bash
docker compose exec -u www-data app bin/console doctrine:migrations:diff --no-interaction
```

- [ ] **Step 3: Verify the generated migration's SQL is exactly an additive column**

```bash
ls migrations/ | sort | tail -1
```

Open the newly generated file (the one not already present before Step 2) and confirm its `up()` contains a single `ALTER TABLE users ADD COLUMN locale VARCHAR(5) NOT NULL DEFAULT 'en'` (SQLite's Doctrine platform may phrase the DDL slightly differently — e.g. it may recreate the table rather than a true `ALTER TABLE ADD COLUMN`, since SQLite's `ALTER TABLE` support is limited; either is fine as long as the net effect is "users gains a NOT NULL locale column defaulting to 'en'" and `down()` correctly reverses it). If the diff also generated unrelated schema noise (it shouldn't, since this is the only entity change), stop and report — don't apply an unexpected migration.

- [ ] **Step 4: Apply the migration**

```bash
docker compose exec -u www-data app bin/console doctrine:migrations:migrate --no-interaction
```

- [ ] **Step 5: Verify the column exists and defaults correctly**

```bash
docker compose exec -u www-data app php -r '
$pdo = new PDO("sqlite:var/database/database.sqlite");
$cols = $pdo->query("PRAGMA table_info(users)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) { if ($c["name"] === "locale") { echo json_encode($c) . PHP_EOL; } }
'
```

Expected: a row for `locale` with `notnull: 1` and `dflt_value` containing `'en'`.

- [ ] **Step 6: Commit**

```bash
git add src/Entity/User.php migrations/
git commit -m "feat(i18n): add locale column to User entity"
```

---

### Task 3: Security layer — public-access attributes, hardcoded-redirect fixes, translated auth flash

**Files:**
- Modify: `src/Controller/MagicLinkController.php`
- Modify: `src/Controller/LoginController.php`
- Modify: `config/packages/security.yaml`
- Modify: `src/Security/MagicLinkEntryPoint.php`
- Modify: `src/Security/LoginSuccessHandler.php`
- Modify: `src/Security/LoginFailureHandler.php`
- Modify: `translations/messages.en.yaml`, `translations/messages.pt.yaml`

**Interfaces:**
- Consumes: `TranslatorInterface` (Task 1), `User::getLocale()` unused here (Task 2 unrelated to this task).
- Produces: nothing new consumed by later tasks — this task is self-contained. (Route localization itself happens in Task 4; this task's `UrlGeneratorInterface::generate()` calls work correctly both before and after Task 4, since `generate()` always resolves to whatever variant of the route currently exists.)

- [ ] **Step 1: Add `#[IsGranted('PUBLIC_ACCESS')]` in `src/Controller/MagicLinkController.php`**

Add the import:

```php
use Symfony\Component\Security\Http\Attribute\IsGranted;
```

Add the attribute directly above both route methods:

```php
    #[Route('/send-magic-link', name: 'app_magic_link_request', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function requestMagicLink(): Response
```

```php
    #[Route('/send-magic-link', name: 'app_magic_link_send', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function sendMagicLink(Request $request): Response
```

- [ ] **Step 2: Add `#[IsGranted('PUBLIC_ACCESS')]` in `src/Controller/LoginController.php`**

```php
<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LoginController extends AbstractController
{
    #[Route('/login/check', name: 'app_login_check', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function check(): never
    {
        throw new \LogicException('This should never be reached — the login_link authenticator intercepts the request.');
    }
}
```

- [ ] **Step 3: Simplify `access_control` in `config/packages/security.yaml`**

Change:

```yaml
    access_control:
        - { path: ^/send-magic-link, roles: PUBLIC_ACCESS }
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

to:

```yaml
    access_control:
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

(Public access to `/send-magic-link` and `/login/check` is now granted per-route via the `#[IsGranted('PUBLIC_ACCESS')]` attributes added in Steps 1-2, which stay correct regardless of what path a route is ultimately served at — unlike a path-regex rule, which Task 4 would otherwise force us to keep hand-syncing with the translated slugs.)

- [ ] **Step 4: Fix the hardcoded redirect in `src/Security/MagicLinkEntryPoint.php`**

Replace the whole file:

```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class MagicLinkEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_magic_link_request'));
    }
}
```

- [ ] **Step 5: Fix the hardcoded redirect in `src/Security/LoginSuccessHandler.php`**

Replace the whole file:

```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_profile'));
    }
}
```

- [ ] **Step 6: Fix the hardcoded redirect and translate the flash message in `src/Security/LoginFailureHandler.php`**

Replace the whole file:

```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add(
                'danger',
                $this->translator->trans('flash.invalid_or_expired_link')
            );
        }

        return new RedirectResponse($this->urlGenerator->generate('app_magic_link_request'));
    }
}
```

- [ ] **Step 7: Add the `flash.invalid_or_expired_link` key to both translation files**

In `translations/messages.en.yaml`, add a new top-level `flash:` section:

```yaml
flash:
    invalid_or_expired_link: 'Invalid or expired magic link. Please request a new magic link'
```

In `translations/messages.pt.yaml`:

```yaml
flash:
    invalid_or_expired_link: 'Link mágico inválido ou expirado. Por favor peça um novo link mágico'
```

- [ ] **Step 8: Verify**

```bash
docker compose exec -u www-data app bin/console lint:yaml translations/ config/packages/security.yaml config/packages/framework.yaml
docker compose exec -u www-data app bin/console debug:router | grep -E 'app_login_check|app_magic_link'
```

Expected: no lint errors; the router listing still shows both routes (unchanged at this point — Task 4 is what localizes them).

Run the pre-Task-4 form of the authentication recipe from "Verification helper" above, using a **wrong/expired** login link this time to trigger the failure path:

```bash
scratch=/tmp/pixel-tracks-verify
curl -sS -c "$scratch/cookies.txt" -o /dev/null -w "%{http_code}\n" "http://localhost/login/check?user=nobody@example.com&expires=1&hash=invalid"
curl -sS -b "$scratch/cookies.txt" -L http://localhost/send-magic-link -o "$scratch/task3-failure.html"
grep -c 'Invalid or expired magic link' "$scratch/task3-failure.html"
```

Expected: the final request lands on `/send-magic-link` (not a raw Symfony exception page) with the translated flash message visible (1 match). Also run the full successful login recipe once, to confirm the success-handler redirect still lands on `/profile/`.

- [ ] **Step 9: Commit**

```bash
git add src/Controller/MagicLinkController.php src/Controller/LoginController.php config/packages/security.yaml src/Security/MagicLinkEntryPoint.php src/Security/LoginSuccessHandler.php src/Security/LoginFailureHandler.php translations/
git commit -m "feat(i18n): make auth flow locale-safe (IsGranted attributes, route-based redirects)"
```

---

### Task 4: Localize the 5 user-facing routes and fix every hardcoded internal link

> **Amended after Task 3** (see the plan's Global Constraints and the SDD ledger for full reasoning): Task 3 discovered that `access_control`'s path-regex rules are NOT made redundant by `#[IsGranted('PUBLIC_ACCESS')]` — they're independent mechanisms, and `access_control` is evaluated first and unconditionally, so it's still load-bearing for public access. This task now includes a new Step 3a updating `access_control`'s public rule to match both of `/send-magic-link`'s translated paths, since it's about to stop being a single literal string.

**Files:**
- Modify: `src/Controller/HomeController.php`
- Modify: `src/Controller/MagicLinkController.php`
- Modify: `src/Controller/TrackController.php`
- Modify: `src/Controller/MapController.php`
- Modify: `config/packages/security.yaml`
- Modify: `templates/Default/home.html.twig`
- Modify: `templates/Default/track.html.twig`
- Modify: `templates/Default/magic-link.html.twig`
- Modify: `templates/Default/Blocks/header.html.twig`

**Interfaces:**
- Produces: `app_profile`, `app_profile_page`, `app_magic_link_request`, `app_magic_link_send`, `app_track_info`, `app_track_map` all become locale-aware routes (internally `<name>.en` / `<name>.pt`), generated/matched transparently by their original bare name everywhere in the app — this is what every later task's `path()`/`redirectToRoute()` calls rely on.
- **This task carries real implementation risk**: Symfony's native i18n routing is documented to resolve `path('app_profile')`/`redirectToRoute('app_profile')` to the *current request's* locale automatically, with no code change needed at every call site — but this must be **verified against the real running app**, not assumed. Step 7's verification is written to catch it immediately if that assumption is wrong, before Tasks 5-8 build on top of it.

- [ ] **Step 1: Localize `/` (root) and `/profile/` in `src/Controller/HomeController.php`**

Add the import:

```php
use Symfony\Component\HttpFoundation\Request;
```

(if not already imported — it already is, for the `profile()` method).

Change:

```php
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): RedirectResponse
    {
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/', name: 'app_profile', methods: ['GET'])]
    #[Route('/profile/{page}', name: 'app_profile_page', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function profile(Request $request): Response
```

to:

```php
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(Request $request): RedirectResponse
    {
        $locale = $request->getPreferredLanguage(['en', 'pt']);

        return $this->redirectToRoute('app_profile', ['_locale' => $locale]);
    }

    #[Route(path: ['en' => '/en/profile/', 'pt' => '/pt/perfil/'], name: 'app_profile', methods: ['GET'])]
    #[Route(path: ['en' => '/en/profile/{page}', 'pt' => '/pt/perfil/{page}'], name: 'app_profile_page', requirements: ['page' => '\d+'], methods: ['GET'])]
    public function profile(Request $request): Response
```

> **Amended after Step 10's first run** (see the SDD ledger): the paths above now carry an explicit `/en/`/`/pt/` prefix segment in front of the translated slug — the plan's first draft of this step only translated the slug (`/profile/` vs `/perfil/`) and forgot the locale-prefix segment entirely, even though the spec, the access_control regex in Step 3, and the verification commands in Step 10 all assumed the prefix was there. This was a bug in the plan, not a design change — the design has always been "locale prefix + translated slug" (confirmed during brainstorming). The same fix applies to every other localized route below.

- [ ] **Step 2: Localize both `/send-magic-link` routes in `src/Controller/MagicLinkController.php`**

Change:

```php
    #[Route('/send-magic-link', name: 'app_magic_link_request', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function requestMagicLink(): Response
```

to:

```php
    #[Route(path: ['en' => '/en/send-magic-link', 'pt' => '/pt/link-magico'], name: 'app_magic_link_request', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function requestMagicLink(): Response
```

Change:

```php
    #[Route('/send-magic-link', name: 'app_magic_link_send', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function sendMagicLink(Request $request): Response
```

to:

```php
    #[Route(path: ['en' => '/en/send-magic-link', 'pt' => '/pt/link-magico'], name: 'app_magic_link_send', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function sendMagicLink(Request $request): Response
```

- [ ] **Step 3: Update `access_control`'s public rule in `config/packages/security.yaml` to match both translated slugs**

Task 3 established that `access_control`'s path-regex rules are still load-bearing for public access (see this task's amendment note above) — the original single-path public rule for `/send-magic-link` must now match both of the paths that route can be served at.

Change:

```yaml
    access_control:
        - { path: ^/send-magic-link, roles: PUBLIC_ACCESS }
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

to:

```yaml
    access_control:
        - { path: ^/(en/send-magic-link|pt/link-magico), roles: PUBLIC_ACCESS }
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

- [ ] **Step 4: Localize `/track/info/{trackKey}` in `src/Controller/TrackController.php`**

Change:

```php
    #[Route('/track/info/{trackKey}', name: 'app_track_info', methods: ['GET'])]
    public function index(string $trackKey): Response
```

to:

```php
    #[Route(path: ['en' => '/en/track/info/{trackKey}', 'pt' => '/pt/percurso/info/{trackKey}'], name: 'app_track_info', methods: ['GET'])]
    public function index(string $trackKey): Response
```

(`/track/delete` in the same file is intentionally left unchanged — it's a POST-only form-action route, not localized.)

- [ ] **Step 5: Localize `/map/{trackKey}` in `src/Controller/MapController.php`**

Change:

```php
    #[Route('/map/{trackKey}', name: 'app_track_map', methods: ['GET'])]
    public function index(string $trackKey): Response
```

to:

```php
    #[Route(path: ['en' => '/en/map/{trackKey}', 'pt' => '/pt/mapa/{trackKey}'], name: 'app_track_map', methods: ['GET'])]
    public function index(string $trackKey): Response
```

- [ ] **Step 6: Fix hardcoded links in `templates/Default/home.html.twig`**

Change:

```twig
                                        <a href="/map/{{ track.key }}" target="_blank" class="nav-link" title="Show on map">
```

to:

```twig
                                        <a href="{{ path('app_track_map', {trackKey: track.key}) }}" target="_blank" class="nav-link" title="Show on map">
```

Change:

```twig
                                        <a href="/track/info/{{track.key}}" class="nav-link" title="Show info">
```

to:

```twig
                                        <a href="{{ path('app_track_info', {trackKey: track.key}) }}" class="nav-link" title="Show info">
```

Change:

```twig
                <form method="POST" enctype="multipart/form-data" action="/track/upload">
```

to:

```twig
                <form method="POST" enctype="multipart/form-data" action="{{ path('app_track_upload') }}">
```

- [ ] **Step 7: Fix hardcoded links in `templates/Default/track.html.twig`**

Change (appears twice, once per form — replace both occurrences):

```twig
                            <form action="/track/delete" method="post" onsubmit="return confirm('Are you sure?')">
```

to:

```twig
                            <form action="{{ path('app_track_delete') }}" method="post" onsubmit="return confirm('Are you sure?')">
```

and:

```twig
                    <form action="/track/delete" method="post">
```

to:

```twig
                    <form action="{{ path('app_track_delete') }}" method="post">
```

Change:

```twig
                                <a href="/profile" class="btn btn-secondary">
```

to:

```twig
                                <a href="{{ path('app_profile') }}" class="btn btn-secondary">
```

- [ ] **Step 8: Fix hardcoded form action in `templates/Default/magic-link.html.twig`**

Change:

```twig
                    <form class="needs-validation" method="POST" action="/send-magic-link">
```

to:

```twig
                    <form class="needs-validation" method="POST" action="{{ path('app_magic_link_send') }}">
```

- [ ] **Step 9: Fix hardcoded links in `templates/Default/Blocks/header.html.twig`**

Change:

```twig
                    <a href="/profile" class="nav-link"><i class="bi bi-person-circle me-1"></i>Profile</a>
```

to:

```twig
                    <a href="{{ path('app_profile') }}" class="nav-link"><i class="bi bi-person-circle me-1"></i>Profile</a>
```

Change:

```twig
                        <a href="/logout" class="nav-link"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
```

to:

```twig
                        <a href="{{ path('app_logout') }}" class="nav-link"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
```

(`href="/"` on the brand link and `href="#"` on the "About" link are both intentionally left as-is — `/` is genuinely the correct unprefixed root, and `About` is a pre-existing placeholder link, out of scope.)

- [ ] **Step 10: Verify the routing itself — this is the critical check for this task**

```bash
docker compose exec -u www-data app bin/console debug:router | grep -E 'app_profile|app_magic_link|app_track_info|app_track_map'
```

Expected: you should see locale-suffixed route names (e.g. `app_profile.en`, `app_profile.pt`) or equivalent locale-aware entries for all 6 localized route/method combinations, with paths `/profile/` vs `/perfil/`, `/send-magic-link` vs `/link-magico`, `/track/info/{trackKey}` vs `/percurso/info/{trackKey}`, `/map/{trackKey}` vs `/mapa/{trackKey}`.

```bash
curl -sSI http://localhost/en/profile/ -o /dev/null -w "en direct -> %{http_code}\n"
curl -sSI http://localhost/pt/perfil/ -o /dev/null -w "pt direct -> %{http_code}\n"
```

Both should currently 302 (redirect to login) since you're unauthenticated — that alone confirms both locale paths are routable at all (a truly unrouted path would 404, not redirect-to-login).

Confirm the Step 3 `access_control` fix actually works — both translated public paths must be reachable *without* authentication and without a redirect loop:

```bash
curl -sSI http://localhost/en/send-magic-link -o /dev/null -w "en public -> %{http_code}\n"
curl -sSI http://localhost/pt/link-magico -o /dev/null -w "pt public -> %{http_code}\n"
```

Both should be `200` directly, with no redirect. If either loops or 403s, the `access_control` regex from Step 3 doesn't actually match that path — stop and report rather than guessing at a fix, this is exactly the failure mode Task 3 already hit once.

Now the critical generation check — authenticate once (using the **pre-Task-4 form** of the recipe won't work anymore since `/send-magic-link` is now locale-prefixed; use the **post-Task-4 form** from "Verification helper" above, hitting `/en/send-magic-link`), then, while still using that English-locale session, fetch the English profile page and confirm every link on it is generated with the `/en/` prefix (not `/pt/` and not bare/unprefixed):

```bash
scratch=/tmp/pixel-tracks-verify
curl -sS -b "$scratch/cookies.txt" http://localhost/en/profile/ -o "$scratch/task4-en-profile.html"
grep -c 'href="/track/info/\|href="/map/\|href="/profile"' "$scratch/task4-en-profile.html"   # expect 0 — no unprefixed leftovers
grep -oP 'href="\K/en/[^"]*' "$scratch/task4-en-profile.html" | sort -u                          # every generated internal link should start with /en/
```

Then repeat with a Portuguese session — same recipe, but hitting `http://localhost/pt/link-magico` and using `i18n-verify-pt@example.com` as the email address (a different address than the English pass, to dodge the per-email rate limit) — and confirm the profile page's generated links are all `/pt/`-prefixed instead. **If any link comes out with the wrong locale prefix, or bare/unprefixed, or the request 500s**, this is the exact risk called out in this task's Interfaces section — stop and report rather than guessing at a fix; it likely means an explicit `_locale` parameter or a different route-generation approach is needed and the plan's assumption about Symfony's automatic locale-context propagation needs revisiting.

- [ ] **Step 11: Commit**

```bash
git add src/Controller/HomeController.php src/Controller/MagicLinkController.php src/Controller/TrackController.php src/Controller/MapController.php config/packages/security.yaml templates/Default/home.html.twig templates/Default/track.html.twig templates/Default/magic-link.html.twig templates/Default/Blocks/header.html.twig
git commit -m "feat(i18n): localize user-facing route paths and fix hardcoded internal links"
```

---

### Task 5: Language switcher, header/error-page/base-layout translation

**Files:**
- Create: `src/EventListener/LocaleTemplateGlobalsListener.php`
- Modify: `templates/Default/base.html.twig`
- Modify: `templates/Error/base.html.twig`
- Modify: `templates/Default/Blocks/header.html.twig`
- Modify: `templates/bundles/TwigBundle/Exception/error403.html.twig`
- Modify: `templates/bundles/TwigBundle/Exception/error404.html.twig`
- Modify: `templates/bundles/TwigBundle/Exception/error500.html.twig`
- Modify: `translations/messages.en.yaml`, `translations/messages.pt.yaml`

**Interfaces:**
- Consumes: locale-aware routes from Task 4 (the switcher only makes sense once URLs vary by locale); the 3 `nav.*` keys from Task 1.
- Produces: Twig globals `app_route_name` (the current route's bare name, with any `.en`/`.pt` suffix stripped) and `app_route_params` (its route parameters) — available to every template from here on, though nothing else in this plan uses them besides the switcher itself.

- [ ] **Step 1: Create `src/EventListener/LocaleTemplateGlobalsListener.php`**

```php
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

#[AsEventListener(event: KernelEvents::REQUEST)]
class LocaleTemplateGlobalsListener
{
    public function __construct(private readonly Environment $twig)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = (string) $request->attributes->get('_route', '');
        $bareRouteName = (string) preg_replace('/\.(en|pt)$/', '', $routeName);

        $this->twig->addGlobal('app_route_name', $bareRouteName);
        $this->twig->addGlobal('app_route_params', $request->attributes->get('_route_params', []));
    }
}
```

This runs at Symfony's default listener priority (`0`), which is after the core `RouterListener` (priority `32`), so `_route`/`_route_params` are already set on the request by the time this listener reads them — matching the pattern of this project's existing `CountryRestrictionListener` (`priority: 10`, also after routing). No explicit service wiring is needed; `config/services.yaml`'s `App\:` resource + `autoconfigure: true` picks up the `#[AsEventListener]` attribute automatically, the same way `SecurityHeadersListener` and `CountryRestrictionListener` are already registered.

- [ ] **Step 2: Fix `<html lang="en">` in `templates/Default/base.html.twig`**

Change:

```twig
<html lang="en">
```

to:

```twig
<html lang="{{ app.request.locale }}">
```

- [ ] **Step 3: Fix `<html lang="en">` in `templates/Error/base.html.twig`**

Same change as Step 2, same line.

- [ ] **Step 4: Translate labels and add the language switcher in `templates/Default/Blocks/header.html.twig`**

Change:

```twig
                <li class="nav-item">
                    <a href="{{ path('app_profile') }}" class="nav-link"><i class="bi bi-person-circle me-1"></i>Profile</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="bi bi-info-circle me-1"></i>About</a>
                </li>
                {% if app.user %}
                    <li class="nav-item">
                        <a href="{{ path('app_logout') }}" class="nav-link"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
                    </li>
                {% endif %}
            </ul>
```

to:

```twig
                <li class="nav-item">
                    <a href="{{ path('app_profile') }}" class="nav-link"><i class="bi bi-person-circle me-1"></i>{{ 'nav.profile'|trans }}</a>
                </li>
                <li class="nav-item">
                    <a href="#" class="nav-link"><i class="bi bi-info-circle me-1"></i>{{ 'nav.about'|trans }}</a>
                </li>
                {% if app.user %}
                    <li class="nav-item">
                        <a href="{{ path('app_logout') }}" class="nav-link"><i class="bi bi-box-arrow-right me-1"></i>{{ 'nav.logout'|trans }}</a>
                    </li>
                {% endif %}
                {% set locale_names = {'en': 'English', 'pt': 'Português'} %}
                {% if app_route_name %}
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-translate me-1"></i>{{ locale_names[app.request.locale] }}
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            {% for loc, label in locale_names %}
                                <li>
                                    <a class="dropdown-item {{ app.request.locale == loc ? 'active' : '' }}"
                                       href="{{ path(app_route_name, app_route_params|merge({_locale: loc})) }}">{{ label }}</a>
                                </li>
                            {% endfor %}
                        </ul>
                    </li>
                {% endif %}
            </ul>
```

(`locale_names` is intentionally not run through `|trans` — a language's own name in the switcher is conventionally shown in that language itself, e.g. "Português" appears the same whether the current page is in English or Portuguese, so it's a fixed display map rather than translated content.)

- [ ] **Step 5: Translate `templates/bundles/TwigBundle/Exception/error403.html.twig`**

Change:

```twig
{% block content %}
Invalid security token
{% endblock %}
```

to:

```twig
{% block content %}
{{ 'error.invalid_security_token'|trans }}
{% endblock %}
```

- [ ] **Step 6: Translate `templates/bundles/TwigBundle/Exception/error404.html.twig`**

Change:

```twig
        <h1 class="text-body-emphasis">404 Page not found</h1>
        <p class="col-lg-8 mx-auto fs-5 text-muted">
            Sorry the page you are looking for doesn't exist...
        </p>
        <div class="d-inline-flex gap-2 mb-5">
            <a href="/" class="d-inline-flex align-items-center btn btn-primary btn-lg px-4 rounded-pill">
                Home page
            </a>
        </div>
```

to:

```twig
        <h1 class="text-body-emphasis">{{ 'error.not_found_title'|trans }}</h1>
        <p class="col-lg-8 mx-auto fs-5 text-muted">
            {{ 'error.not_found_body'|trans }}
        </p>
        <div class="d-inline-flex gap-2 mb-5">
            <a href="/" class="d-inline-flex align-items-center btn btn-primary btn-lg px-4 rounded-pill">
                {{ 'error.home_page'|trans }}
            </a>
        </div>
```

(`href="/"` here is intentionally left as the bare root — it's the same unprefixed `app_home` redirect-to-current-locale-profile entry point Task 4 built, not a route that itself needs a `_locale`.)

- [ ] **Step 7: Translate `templates/bundles/TwigBundle/Exception/error500.html.twig`**

Change:

```twig
{% block content %}
An unexpected error occurred. Please try again later.
{% endblock %}
```

to:

```twig
{% block content %}
{{ 'error.server_error'|trans }}
{% endblock %}
```

- [ ] **Step 8: Add the new keys to both translation files**

Append to `translations/messages.en.yaml`:

```yaml
error:
    invalid_security_token: Invalid security token
    not_found_title: 404 Page not found
    not_found_body: Sorry the page you are looking for doesn't exist...
    home_page: Home page
    server_error: An unexpected error occurred. Please try again later.
```

Append to `translations/messages.pt.yaml`:

```yaml
error:
    invalid_security_token: Token de segurança inválido
    not_found_title: 404 Página não encontrada
    not_found_body: A página que procura não existe...
    home_page: Página inicial
    server_error: Ocorreu um erro inesperado. Por favor tente novamente mais tarde.
```

- [ ] **Step 9: Verify**

```bash
docker compose exec -u www-data app bin/console lint:yaml translations/
docker compose exec -u www-data app bin/console lint:twig templates/
```

Expected: no errors.

Authenticate (post-Task-4 recipe, English), then check the header renders translated and the switcher is present and points at the Portuguese equivalent of the *same page*:

```bash
scratch=/tmp/pixel-tracks-verify
curl -sS -b "$scratch/cookies.txt" http://localhost/en/profile/ -o "$scratch/task5-en.html"
grep -c '>Profile<\|>About<\|>Logout<' "$scratch/task5-en.html"   # expect 0 — old English hardcoded text gone
grep -c 'Português' "$scratch/task5-en.html"                        # expect 1 — switcher present
grep -oP 'href="\K/pt/perfil/?"' "$scratch/task5-en.html"           # expect the pt profile URL, not /pt/ (homepage) or something else
```

Also load a track-info page in English and confirm the switcher's Portuguese link points at `/pt/percurso/info/{sameKey}`, not `/pt/perfil/` — this proves `app_route_params` (the `trackKey`) round-trips correctly, not just `app_route_name`.

Visit a nonexistent route and confirm the 404 page renders with translated text (check both `en` and `pt`, e.g. `curl http://localhost/en/does-not-exist` vs `/pt/does-not-exist` — both should 404 through the app's own error template, which the shared, now-locale-aware `Error/base.html.twig` renders regardless of which unprefixed 404 path you hit, since Symfony's exception handling isn't itself route-locale-bound).

- [ ] **Step 10: Commit**

```bash
git add src/EventListener/LocaleTemplateGlobalsListener.php templates/Default/base.html.twig templates/Error/base.html.twig templates/Default/Blocks/header.html.twig templates/bundles/TwigBundle/Exception/ translations/
git commit -m "feat(i18n): add language switcher and translate shared layout/error pages"
```

---

### Task 6: Translate the home/profile page, pagination, and upload flow

**Files:**
- Modify: `templates/Default/home.html.twig`
- Modify: `src/Pagination/Paginator.php`
- Modify: `src/Controller/HomeController.php`
- Modify: `src/Controller/UploadController.php`
- Modify: `src/Service/GpxValidator.php`
- Modify: `src/Exception/GpxValidationException.php`
- Modify: `translations/messages.en.yaml`, `translations/messages.pt.yaml`

**Interfaces:**
- Produces: `GpxValidationException::getParameters(): array` — a new method Task 6 itself both adds and consumes (self-contained; no other task touches this exception class).

- [ ] **Step 1: Give `GpxValidationException` a parameters payload for interpolated translations**

Replace the whole file `src/Exception/GpxValidationException.php`:

```php
<?php

namespace App\Exception;

class GpxValidationException extends \RuntimeException
{
    /** @param array<string, string> $parameters */
    public function __construct(string $translationKey, private readonly array $parameters = [])
    {
        parent::__construct($translationKey);
    }

    /** @return array<string, string> */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
```

(`getMessage()` still returns the string passed in — now a translation *key* rather than a final English sentence — so every existing `$e->getMessage()` call site keeps compiling; Step 3 below is what changes those call sites to actually translate it.)

- [ ] **Step 2: Change `src/Service/GpxValidator.php` to throw translation keys**

Change:

```php
            throw new GpxValidationException('File size exceeds maximum allowed size of 10MB');
```

to:

```php
            throw new GpxValidationException('gpx_validation.file_too_large');
```

Change:

```php
            throw new GpxValidationException('Invalid file type. Only GPX files are allowed.');
```

to:

```php
            throw new GpxValidationException('gpx_validation.invalid_file_type');
```

Change:

```php
            throw new GpxValidationException('Invalid XML structure: ' . $errors[0]->message);
```

to:

```php
            throw new GpxValidationException('gpx_validation.invalid_xml_structure', ['%details%' => $errors[0]->message]);
```

Change:

```php
            throw new GpxValidationException('Invalid GPX namespace');
```

to:

```php
            throw new GpxValidationException('gpx_validation.invalid_namespace');
```

Change:

```php
            throw new GpxValidationException('No valid track data found in GPX file');
```

to:

```php
            throw new GpxValidationException('gpx_validation.no_track_data');
```

- [ ] **Step 3: Translate flash messages and exception handling in `src/Controller/UploadController.php`**

Add the import:

```php
use Symfony\Contracts\Translation\TranslatorInterface;
```

Add `TranslatorInterface $translator` to the constructor's promoted-property list (alongside the existing ones):

```php
    public function __construct(
        private readonly XmlValidator $xmlValidator,
        private readonly GpxValidator $gpxValidator,
        private readonly FileUploaderService $fileUploaderService,
        private readonly GpsTrack $gpsTrack,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly string $gpxSchemaPath,
    ) {
    }
```

Change:

```php
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
```

to:

```php
        if (!$file) {
            $this->addFlash('danger', $this->translator->trans('flash.no_file_uploaded'));

            return $this->redirectToRoute('app_profile');
        }

        $trackName = trim(htmlspecialchars((string) $request->request->get('trackName', '')));
        if ($trackName === '') {
            $this->addFlash('danger', $this->translator->trans('flash.track_name_required'));

            return $this->redirectToRoute('app_profile');
        }

        try {
            $this->assertValidGpxFile($file);
        } catch (GpxValidationException $e) {
            $this->addFlash('danger', $this->translator->trans($e->getMessage(), $e->getParameters()));

            return $this->redirectToRoute('app_profile');
        }
```

Change:

```php
        if (!$this->fileUploaderService->uploadFile($user, $file, $targetFileName)) {
            $this->addFlash('danger', 'Unable to upload the file');

            return $this->redirectToRoute('app_profile');
        }
```

to:

```php
        if (!$this->fileUploaderService->uploadFile($user, $file, $targetFileName)) {
            $this->addFlash('danger', $this->translator->trans('flash.upload_failed'));

            return $this->redirectToRoute('app_profile');
        }
```

Change:

```php
        $this->addFlash('success', 'New file uploaded');
```

to:

```php
        $this->addFlash('success', $this->translator->trans('flash.upload_success'));
```

Change (inside `assertValidGpxFile`):

```php
            if (!$isValidXml) {
                throw new GpxValidationException('Invalid GPX file format');
            }

            $this->gpxValidator->validate($file);
        } catch (GpxValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new GpxValidationException('Error validating GPX file: ' . $e->getMessage());
        }
```

to:

```php
            if (!$isValidXml) {
                throw new GpxValidationException('gpx_validation.invalid_gpx_format');
            }

            $this->gpxValidator->validate($file);
        } catch (GpxValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new GpxValidationException('gpx_validation.validation_error', ['%details%' => $e->getMessage()]);
        }
```

- [ ] **Step 4: Translate pagination labels — add label support to `src/Pagination/Paginator.php`**

Add two properties near the existing ones (after `$default_ipp`/`$url`):

```php
    private string $previousLabel = 'Previous';
    private string $nextLabel = 'Next';
```

Add a setter method (near `setMidRange`):

```php
    public function setLabels(string $previousLabel, string $nextLabel): self
    {
        $this->previousLabel = $previousLabel;
        $this->nextLabel = $nextLabel;

        return $this;
    }
```

Replace every hardcoded caption string with the property, keeping the `«`/`»` glyphs exactly as before:

Change all four `'« Previous'` occurrences to `'« ' . $this->previousLabel`, both plain `'Previous'` occurrences to `$this->previousLabel`, both `'Next »'` occurrences to `$this->nextLabel . ' »'`, and the plain `'Next'` occurrence to `$this->nextLabel`. Concretely, in the `caption` array entries:

- `'« Previous'` → `'« ' . $this->previousLabel`
- `'Previous'` (the two bare ones, not `« Previous`) → `$this->previousLabel`
- `'Next »'` → `$this->nextLabel . ' »'`
- `'Next'` (the bare one) → `$this->nextLabel`

- [ ] **Step 5: Pass translated labels from `src/Controller/HomeController.php`**

Add the import:

```php
use Symfony\Contracts\Translation\TranslatorInterface;
```

Add `TranslatorInterface $translator` to the constructor:

```php
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly TranslatorInterface $translator,
        private readonly int $paginationIpp,
    ) {
    }
```

Change:

```php
        $paginator = new Paginator($request->getPathInfo(), ['page' => $page, 'ipp' => $this->paginationIpp]);
        $paginator->setItemsTotal($total);
        $paginator->setMidRange(3);
        $paginator->paginate();
```

to:

```php
        $paginator = new Paginator($request->getPathInfo(), ['page' => $page, 'ipp' => $this->paginationIpp]);
        $paginator->setItemsTotal($total);
        $paginator->setMidRange(3);
        $paginator->setLabels(
            $this->translator->trans('home.pagination_previous'),
            $this->translator->trans('home.pagination_next')
        );
        $paginator->paginate();
```

- [ ] **Step 6: Translate `templates/Default/home.html.twig`**

Change:

```twig
                <div class="card-header">
                    <h3 class="card-title">Tracks</h3>
                </div>
```

to:

```twig
                <div class="card-header">
                    <h3 class="card-title">{{ 'home.tracks_title'|trans }}</h3>
                </div>
```

Change:

```twig
                        <tr>
                            <th>Name</th>
```

to:

```twig
                        <tr>
                            <th>{{ 'home.table_name'|trans }}</th>
```

Change:

```twig
                    {% else %}
                        <div class="text-center">No tracks yet!</div>
                    {% endif %}
```

to:

```twig
                    {% else %}
                        <div class="text-center">{{ 'home.no_tracks'|trans }}</div>
                    {% endif %}
```

Change:

```twig
                <div class="card-header">
                    <h3 class="card-title">Upload Track</h3>
                </div>
```

to:

```twig
                <div class="card-header">
                    <h3 class="card-title">{{ 'home.upload_title'|trans }}</h3>
                </div>
```

Change:

```twig
                        <div class="mb-3">
                            <label for="trackName" class="form-label">Track Name</label>
                            <input type="text" class="form-control" id="trackName" name="trackName" placeholder="Enter track name" required/>
                        </div>
                        <div class="mb-3">
                            <label for="trackFile" class="form-label">Track File (Only GPX files)</label>
                            <input name="trackFile" type="file" id="trackFile" class="form-control" required/>
                        </div>
```

to:

```twig
                        <div class="mb-3">
                            <label for="trackName" class="form-label">{{ 'home.track_name_label'|trans }}</label>
                            <input type="text" class="form-control" id="trackName" name="trackName" placeholder="{{ 'home.track_name_placeholder'|trans }}" required/>
                        </div>
                        <div class="mb-3">
                            <label for="trackFile" class="form-label">{{ 'home.track_file_label'|trans }}</label>
                            <input name="trackFile" type="file" id="trackFile" class="form-control" required/>
                        </div>
```

Change:

```twig
                        <button type="submit" class="btn btn-primary">Submit</button>
```

to:

```twig
                        <button type="submit" class="btn btn-primary">{{ 'home.submit'|trans }}</button>
```

- [ ] **Step 7: Add the new keys to both translation files**

Append to `translations/messages.en.yaml`:

```yaml
home:
    tracks_title: Tracks
    table_name: Name
    no_tracks: No tracks yet!
    upload_title: Upload Track
    track_name_label: Track Name
    track_name_placeholder: Enter track name
    track_file_label: Track File (Only GPX files)
    submit: Submit
    pagination_previous: Previous
    pagination_next: Next

flash:
    no_file_uploaded: No file was uploaded
    track_name_required: Track name is required
    upload_failed: Unable to upload the file
    upload_success: New file uploaded

gpx_validation:
    file_too_large: File size exceeds maximum allowed size of 10MB
    invalid_file_type: 'Invalid file type. Only GPX files are allowed.'
    invalid_xml_structure: 'Invalid XML structure: %details%'
    invalid_namespace: Invalid GPX namespace
    no_track_data: No valid track data found in GPX file
    invalid_gpx_format: Invalid GPX file format
    validation_error: 'Error validating GPX file: %details%'
```

(This adds a second `flash:` block to the file — Task 3 already added one with `invalid_or_expired_link` in it. Add these two new keys as siblings under the *same* existing `flash:` top-level key rather than creating a second `flash:` block, since YAML would let the second one silently override the first.)

Append to `translations/messages.pt.yaml`:

```yaml
home:
    tracks_title: Percursos
    table_name: Nome
    no_tracks: Ainda não há percursos!
    upload_title: Enviar Percurso
    track_name_label: Nome do Percurso
    track_name_placeholder: Indique o nome do percurso
    track_file_label: Ficheiro do Percurso (Apenas ficheiros GPX)
    submit: Enviar
    pagination_previous: Anterior
    pagination_next: Seguinte

flash:
    no_file_uploaded: Nenhum ficheiro foi enviado
    track_name_required: O nome do percurso é obrigatório
    upload_failed: Não foi possível enviar o ficheiro
    upload_success: Novo ficheiro enviado

gpx_validation:
    file_too_large: O tamanho do ficheiro excede o máximo permitido de 10MB
    invalid_file_type: 'Tipo de ficheiro inválido. Apenas são permitidos ficheiros GPX.'
    invalid_xml_structure: 'Estrutura XML inválida: %details%'
    invalid_namespace: Namespace GPX inválido
    no_track_data: Não foram encontrados dados de percurso válidos no ficheiro GPX
    invalid_gpx_format: Formato de ficheiro GPX inválido
    validation_error: 'Erro ao validar o ficheiro GPX: %details%'
```

(Same note: merge into the existing `flash:` block, don't duplicate the key.)

- [ ] **Step 8: Verify**

```bash
docker compose exec -u www-data app bin/console lint:yaml translations/
docker compose exec -u www-data app bin/console lint:twig templates/Default/home.html.twig
docker compose exec -u www-data app bin/console lint:container
```

Expected: no errors (`lint:container` catches any constructor-injection mistakes in `UploadController`/`HomeController`).

Authenticate in English (post-Task-4 recipe), upload the repo's `var/data/sample.gpx` fixture, and confirm the success flash and new row render translated:

```bash
scratch=/tmp/pixel-tracks-verify
jar="$scratch/cookies.txt"
profile=$(curl -sS -b "$jar" -c "$jar" http://localhost/en/profile/)
token=$(echo "$profile" | grep -oP 'name="_token" value="\K[^"]+' | head -1)
curl -sS -b "$jar" -c "$jar" -L -o "$scratch/task6-en-upload.html" \
  -F "_token=$token" -F "trackName=i18n verify EN" \
  -F "trackFile=@var/data/sample.gpx;filename=sample.gpx;type=application/gpx+xml" \
  http://localhost/track/upload
grep -c 'New file uploaded' "$scratch/task6-en-upload.html"
```

Then trigger a validation failure (upload a non-GPX file, e.g. `composer.json`) and confirm the flash message renders the translated `gpx_validation.invalid_file_type`/`invalid_xml_structure` text, not a raw key like `gpx_validation.invalid_file_type` literally appearing on the page (a literal untranslated key showing through is the standard sign of a missing catalogue entry or wrong domain).

Repeat once in Portuguese (`/pt/link-magico`, a different email) and confirm both the success message and a validation-failure message render in Portuguese, and that the Previous/Next pagination controls (upload enough tracks, or just check the rendered HTML for the label text directly if fewer than a page's worth exist) use the translated labels.

- [ ] **Step 9: Commit**

```bash
git add templates/Default/home.html.twig src/Pagination/Paginator.php src/Controller/HomeController.php src/Controller/UploadController.php src/Service/GpxValidator.php src/Exception/GpxValidationException.php translations/
git commit -m "feat(i18n): translate home/upload page, pagination, and GPX validation messages"
```

---

### Task 7: Translate the track info/delete page

**Files:**
- Modify: `templates/Default/track.html.twig`
- Modify: `src/Controller/TrackController.php`
- Modify: `src/Controller/MapController.php`
- Modify: `translations/messages.en.yaml`, `translations/messages.pt.yaml`

**Interfaces:** None new — self-contained.

- [ ] **Step 1: Translate flash messages in `src/Controller/TrackController.php`**

Add the import and constructor parameter (same pattern as Task 6's `UploadController`):

```php
use Symfony\Contracts\Translation\TranslatorInterface;
```

```php
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly FileUploaderService $fileUploaderService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }
```

Change (in `index()`):

```php
        if (!$track || $track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'Track does not exist');

            return $this->redirectToRoute('app_profile');
        }
```

to:

```php
        if (!$track || $track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', $this->translator->trans('flash.track_not_found'));

            return $this->redirectToRoute('app_profile');
        }
```

Change (in `deleteTrack()` — note this one currently says `'Track does not exist!'` with a trailing `!`, unified here with the same key as the message above, since both mean the same thing to the user):

```php
        if (!$track || $track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', 'Track does not exist!');

            return $this->redirectToRoute('app_profile');
        }
```

to:

```php
        if (!$track || $track->getUser() !== $this->getUser()) {
            $this->addFlash('danger', $this->translator->trans('flash.track_not_found'));

            return $this->redirectToRoute('app_profile');
        }
```

Change:

```php
        $this->addFlash('success', 'Track deleted');
```

to:

```php
        $this->addFlash('success', $this->translator->trans('flash.track_deleted'));
```

- [ ] **Step 2: Translate flash messages in `src/Controller/MapController.php`**

Add the import and constructor parameter:

```php
use Symfony\Contracts\Translation\TranslatorInterface;
```

```php
    public function __construct(
        private readonly TrackRepository $trackRepository,
        private readonly FileUploaderService $fileUploaderService,
        private readonly GpsTrack $gpsTrack,
        private readonly TranslatorInterface $translator,
    ) {
    }
```

Change all three occurrences:

```php
            $this->addFlash('danger', 'Track file does not exist');
```

to:

```php
            $this->addFlash('danger', $this->translator->trans('flash.track_file_not_found'));
```

and:

```php
            $this->addFlash('danger', 'Track does not exist');
```

to:

```php
            $this->addFlash('danger', $this->translator->trans('flash.track_not_found'));
```

(there are two `'Track file does not exist'` occurrences and one `'Track does not exist'` occurrence in this file — change all three call sites per the mapping above.)

- [ ] **Step 3: Translate `templates/Default/track.html.twig`**

Change:

```twig
                <div class="card-header">
                    <h3 class="card-title">Track Info</h3>
                </div>
```

to:

```twig
                <div class="card-header">
                    <h3 class="card-title">{{ 'track.info_title'|trans }}</h3>
                </div>
```

Change:

```twig
                                <tbody><tr>
                                    <th style="width:50%">Points</th>
                                    <td>{{ track.totalPoints }}</td>
                                </tr>
                                <tr>
                                    <th>Distance</th>
                                    <td>{{ track.distance }} Km</td>
                                </tr>
                                <tr>
                                    <th>Elevation</th>
                                    <td>{{ track.elevation }} m</td>
                                </tr>
                                <tr>
                                    <th>Date</th>
                                    <td>{{ track.createdAt|date('c') }}</td>
                                </tr>
                                </tbody>
```

to:

```twig
                                <tbody><tr>
                                    <th style="width:50%">{{ 'track.points'|trans }}</th>
                                    <td>{{ track.totalPoints }}</td>
                                </tr>
                                <tr>
                                    <th>{{ 'track.distance'|trans }}</th>
                                    <td>{{ track.distance }} Km</td>
                                </tr>
                                <tr>
                                    <th>{{ 'track.elevation'|trans }}</th>
                                    <td>{{ track.elevation }} m</td>
                                </tr>
                                <tr>
                                    <th>{{ 'track.date'|trans }}</th>
                                    <td>{{ track.createdAt|date('c') }}</td>
                                </tr>
                                </tbody>
```

Change:

```twig
                            <form action="{{ path('app_track_delete') }}" method="post" onsubmit="return confirm('Are you sure?')">
                                <input type="hidden" name="_token" value="{{ csrf_token('track-delete') }}"/>
                                <input type="hidden" name="track_key" value="{{ track.key }}"/>
                                <a href="{{ path('app_profile') }}" class="btn btn-secondary">
                                    <i class="bi bi-list"></i> Back to List
                                </a>
                                <button type="button" class="btn btn-danger float-end" data-bs-toggle="modal" data-bs-target="#modal-default">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </form>
```

to:

```twig
                            <form action="{{ path('app_track_delete') }}" method="post" onsubmit="return confirm('{{ 'track.delete_confirm_js'|trans|e('js') }}')">
                                <input type="hidden" name="_token" value="{{ csrf_token('track-delete') }}"/>
                                <input type="hidden" name="track_key" value="{{ track.key }}"/>
                                <a href="{{ path('app_profile') }}" class="btn btn-secondary">
                                    <i class="bi bi-list"></i> {{ 'track.back_to_list'|trans }}
                                </a>
                                <button type="button" class="btn btn-danger float-end" data-bs-toggle="modal" data-bs-target="#modal-default">
                                    <i class="bi bi-trash"></i> {{ 'track.delete'|trans }}
                                </button>
                            </form>
```

Change:

```twig
                    <h4 class="modal-title">Delete Track</h4>
```

to:

```twig
                    <h4 class="modal-title">{{ 'track.delete_title'|trans }}</h4>
```

Change:

```twig
                    <p>Are you sure do you want to delete <b>{{ track.name }}</b> track?</p>
```

to:

```twig
                    <p>{{ 'track.delete_confirm_body'|trans({'%name%': track.name})|raw }}</p>
```

Change:

```twig
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
```

to:

```twig
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ 'track.cancel'|trans }}</button>
```

Change:

```twig
                        <button type="submit" class="btn btn-danger float-end" style="margin-right: 5px;">
                            <i class="bi bi-trash"></i> Delete
                        </button>
```

to:

```twig
                        <button type="submit" class="btn btn-danger float-end" style="margin-right: 5px;">
                            <i class="bi bi-trash"></i> {{ 'track.delete'|trans }}
                        </button>
```

(`track.delete_confirm_body`'s translation value below contains a literal `<b>...</b>` around the track name, which is why the Twig call above uses `|raw` — the translation string is trusted, authored content, not user input; `track.name` itself is inserted via the `%name%` parameter which Symfony's translator HTML-escapes on substitution by default only if you don't `|raw` the whole trans() output — track names ARE user-supplied (`UploadController` already `htmlspecialchars()`-escapes `trackName` at upload time before persisting it, so this is not a new XSS surface.)

- [ ] **Step 4: Add the new keys to both translation files**

Append to `translations/messages.en.yaml`:

```yaml
track:
    info_title: Track Info
    points: Points
    distance: Distance
    elevation: Elevation
    date: Date
    back_to_list: Back to List
    delete: Delete
    delete_title: Delete Track
    delete_confirm_body: 'Are you sure do you want to delete <b>%name%</b> track?'
    delete_confirm_js: Are you sure?
    cancel: Cancel
```

Add these two keys as siblings under the *existing* `flash:` block (don't create a new one):

```yaml
    track_not_found: Track does not exist
    track_file_not_found: Track file does not exist
    track_deleted: Track deleted
```

Append to `translations/messages.pt.yaml`:

```yaml
track:
    info_title: Informação do Percurso
    points: Pontos
    distance: Distância
    elevation: Elevação
    date: Data
    back_to_list: Voltar à Lista
    delete: Eliminar
    delete_title: Eliminar Percurso
    delete_confirm_body: 'Tem a certeza que deseja eliminar o percurso <b>%name%</b>?'
    delete_confirm_js: Tem a certeza?
    cancel: Cancelar
```

Add to the existing `flash:` block:

```yaml
    track_not_found: O percurso não existe
    track_file_not_found: O ficheiro do percurso não existe
    track_deleted: Percurso eliminado
```

- [ ] **Step 5: Verify**

```bash
docker compose exec -u www-data app bin/console lint:yaml translations/
docker compose exec -u www-data app bin/console lint:twig templates/Default/track.html.twig
docker compose exec -u www-data app bin/console lint:container
```

This task's own verification authenticates fresh and uploads its own test track (don't rely on any file left over from another task's run) — run the full sequence below in one go.

Authenticate in English (the post-Task-4 form of the "Verification helper" recipe above, using `i18n-verify-track-en@example.com`), then upload a track and view its info page:

```bash
scratch=/tmp/pixel-tracks-verify
mkdir -p "$scratch"
jar="$scratch/task7-cookies-en.txt"
rm -f "$jar"
form=$(curl -sS -c "$jar" http://localhost/en/send-magic-link)
token=$(echo "$form" | grep -oP 'name="_token" value="\K[^"]+')
curl -sS -b "$jar" -c "$jar" -o /dev/null -d "email=i18n-verify-track-en@example.com" -d "_token=$token" http://localhost/en/send-magic-link
msg_id=$(curl -sS "http://localhost:8125/api/v1/messages" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
echo $d["messages"][0]["ID"] ?? "";
')
link=$(curl -sS "http://localhost:8125/api/v1/message/$msg_id" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
$text = $d["Text"] ?? $d["HTML"] ?? "";
if (preg_match("#http://localhost/login/check\?[^\s\"<]+#", $text, $m)) { echo $m[0]; }
')
curl -sS -b "$jar" -c "$jar" -o /dev/null "$link"

profile=$(curl -sS -b "$jar" -c "$jar" http://localhost/en/profile/)
upload_token=$(echo "$profile" | grep -oP 'name="_token" value="\K[^"]+' | head -1)
curl -sS -b "$jar" -c "$jar" -o /dev/null \
  -F "_token=$upload_token" -F "trackName=Task7 i18n verify EN" \
  -F "trackFile=@var/data/sample.gpx;filename=sample.gpx;type=application/gpx+xml" \
  http://localhost/track/upload

profile2=$(curl -sS -b "$jar" -c "$jar" http://localhost/en/profile/)
key=$(echo "$profile2" | grep -oP '/en/track/info/\K[a-zA-Z0-9_-]+' | head -1)
curl -sS -b "$jar" -c "$jar" "http://localhost/en/track/info/$key" -o "$scratch/task7-en-track.html"
grep -c 'Points\|Distance\|Elevation\|Back to List' "$scratch/task7-en-track.html"   # expect 4
```

Then delete that track and confirm the translated flash message:

```bash
delete_token=$(cat "$scratch/task7-en-track.html" | grep -oP "name=\"_token\" value=\"\K[^\"]+" | head -1)
curl -sS -b "$jar" -c "$jar" -L -o "$scratch/task7-en-deleted.html" \
  -d "_token=$delete_token" -d "track_key=$key" \
  http://localhost/track/delete
grep -c 'Track deleted' "$scratch/task7-en-deleted.html"   # expect 1
```

Then confirm viewing an unknown key renders the translated not-found message:

```bash
curl -sS -b "$jar" -c "$jar" -L "http://localhost/en/track/info/does-not-exist" -o "$scratch/task7-en-notfound.html"
grep -c 'Track does not exist' "$scratch/task7-en-notfound.html"   # expect 1
```

Repeat the entire sequence above once in Portuguese: swap every `/en/` for `/pt/link-magico`/`/pt/perfil/`/`/pt/percurso/info/`, use a different email (`i18n-verify-track-pt@example.com`) and a fresh cookie jar (`task7-cookies-pt.txt`), and confirm the Portuguese strings ("Pontos", "Distância", "Elevação", "Voltar à Lista", "Percurso eliminado", "O percurso não existe") appear instead.

- [ ] **Step 6: Commit**

```bash
git add templates/Default/track.html.twig src/Controller/TrackController.php src/Controller/MapController.php translations/
git commit -m "feat(i18n): translate track info/delete page and map/track flash messages"
```

---

### Task 8: Magic-link flow — page, flash messages, locale persistence, and email translation

**Files:**
- Modify: `templates/Default/magic-link.html.twig`
- Modify: `templates/Default/Mail/magic-link-html.html.twig`
- Modify: `templates/Default/Mail/magic-link-text.txt.twig`
- Modify: `src/Controller/MagicLinkController.php`
- Modify: `translations/messages.en.yaml`, `translations/messages.pt.yaml`

**Interfaces:**
- Consumes: `User::setLocale()` from Task 2.

- [ ] **Step 1: Translate `templates/Default/magic-link.html.twig`**

Change:

```twig
                <h1 class="fw-light">Send me a Magic link</h1>
                <p class="lead text-body-secondary">Submit your email to be able to access your profile</p>
```

to:

```twig
                <h1 class="fw-light">{{ 'magic_link.title'|trans }}</h1>
                <p class="lead text-body-secondary">{{ 'magic_link.intro'|trans }}</p>
```

Change:

```twig
                                <div class="invalid-feedback">
                                    Please enter a valid email address.
                                </div>
```

to:

```twig
                                <div class="invalid-feedback">
                                    {{ 'magic_link.email_invalid_feedback'|trans }}
                                </div>
```

Change:

```twig
                        <button class="w-100 btn btn-primary btn-lg mt-4" type="submit">Send</button>
```

to:

```twig
                        <button class="w-100 btn btn-primary btn-lg mt-4" type="submit">{{ 'magic_link.send'|trans }}</button>
```

- [ ] **Step 2: Translate flash messages, persist the requester's locale, and translate the email in `src/Controller/MagicLinkController.php`**

Add the import:

```php
use Symfony\Contracts\Translation\TranslatorInterface;
```

Add `TranslatorInterface $translator` to the constructor:

```php
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoginLinkHandlerInterface $loginLinkHandler,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        #[Autowire(service: 'limiter.magic_link_by_ip')]
        private readonly RateLimiterFactory $magicLinkByIpLimiterFactory,
        #[Autowire(service: 'limiter.magic_link_by_email')]
        private readonly RateLimiterFactory $magicLinkByEmailLimiterFactory,
        private readonly string $emailFrom,
    ) {
    }
```

Change:

```php
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
```

to:

```php
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->addFlash('danger', $this->translator->trans('flash.invalid_email'));

            return $this->redirectToRoute('app_magic_link_request');
        }

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user) {
            $user = new User($email);
            $this->entityManager->persist($user);
        }
        $user->setLocale($request->getLocale());
        $this->entityManager->flush();

        $loginLinkDetails = $this->loginLinkHandler->createLoginLink($user);

        $mail = (new Email())
            ->from($this->emailFrom)
            ->to($email)
            ->subject($this->translator->trans('mail.magic_link_subject'))
            ->html($this->renderView('Default/Mail/magic-link-html.html.twig', ['link' => $loginLinkDetails->getUrl()]))
            ->text($this->renderView('Default/Mail/magic-link-text.txt.twig', ['link' => $loginLinkDetails->getUrl()]));

        $this->mailer->send($mail);

        $this->addFlash('success', $this->translator->trans('flash.please_verify_mailbox'));

        return $this->redirectToRoute('app_magic_link_request');
```

(Note the `$this->entityManager->flush()` call moved to run unconditionally after `setLocale()`, whether the user is brand-new or already existed — an existing user's locale preference is now updated on every magic-link request, reflecting the language they're currently using, not just fixed at account-creation time. The mail templates render via `renderView()` within this same request, whose locale was already resolved from the `_locale` on the matched `app_magic_link_send` route — so no explicit locale-switching around the `renderView()` calls is needed; Twig's translator already uses the current request's locale.)

- [ ] **Step 3: Translate `templates/Default/Mail/magic-link-html.html.twig`**

Change:

```twig
                                                <p class="" style="line-height: 24px; font-size: 16px; width: 100%; margin: 0;" align="left">
                                                    Here is a magic link to access to you profile.
                                                </p>
```

to:

```twig
                                                <p class="" style="line-height: 24px; font-size: 16px; width: 100%; margin: 0;" align="left">
                                                    {{ 'mail.magic_link_body'|trans }}
                                                </p>
```

Change:

```twig
                                                            <a href="{{ link }}" style="color: #ffffff; font-size: 16px; font-family: Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 6px; line-height: 20px; display: block; font-weight: 700 !important; white-space: nowrap; background-color: #0d6efd; padding: 12px; border: 1px solid #0d6efd;">My Profile</a>
```

to:

```twig
                                                            <a href="{{ link }}" style="color: #ffffff; font-size: 16px; font-family: Helvetica, Arial, sans-serif; text-decoration: none; border-radius: 6px; line-height: 20px; display: block; font-weight: 700 !important; white-space: nowrap; background-color: #0d6efd; padding: 12px; border: 1px solid #0d6efd;">{{ 'mail.magic_link_button'|trans }}</a>
```

Change:

```twig
                                    <div class="text-muted text-center" style="color: #718096;" align="center">
                                        Sent with &lt;3 from Pixel Tracks<br>
                                    </div>
```

to:

```twig
                                    <div class="text-muted text-center" style="color: #718096;" align="center">
                                        {{ 'mail.magic_link_footer_prefix'|trans }} Pixel Tracks<br>
                                    </div>
```

- [ ] **Step 4: Translate `templates/Default/Mail/magic-link-text.txt.twig`**

Replace the whole file:

```twig
{{ 'mail.magic_link_body'|trans }}

{{ link }}
```

- [ ] **Step 5: Add the new keys to both translation files**

Append to `translations/messages.en.yaml`:

```yaml
magic_link:
    title: Send me a Magic link
    intro: Submit your email to be able to access your profile
    email_invalid_feedback: Please enter a valid email address.
    send: Send

mail:
    magic_link_subject: Here is your magic link
    magic_link_body: Here is a magic link to access your profile.
    magic_link_button: My Profile
    magic_link_footer_prefix: 'Sent with <3 from'
```

Add this key as a sibling under the existing `flash:` block:

```yaml
    invalid_email: Invalid email
    please_verify_mailbox: Please verify your mailbox
```

Append to `translations/messages.pt.yaml`:

```yaml
magic_link:
    title: Enviar-me um Link Mágico
    intro: Indique o seu email para aceder ao seu perfil
    email_invalid_feedback: Por favor indique um endereço de email válido.
    send: Enviar

mail:
    magic_link_subject: Aqui está o seu link mágico
    magic_link_body: Aqui está um link mágico para aceder ao seu perfil.
    magic_link_button: O Meu Perfil
    magic_link_footer_prefix: 'Enviado com <3 por'
```

Add to the existing `flash:` block:

```yaml
    invalid_email: Email inválido
    please_verify_mailbox: Por favor verifique o seu email
```

(Note: `mail.magic_link_body`'s English text is corrected here from the original template's typo — "access to you profile" — to "access your profile", since this is the natural point where that string becomes the canonical source-of-truth English copy for the key.)

- [ ] **Step 6: Verify**

```bash
docker compose exec -u www-data app bin/console lint:yaml translations/
docker compose exec -u www-data app bin/console lint:twig templates/Default/magic-link.html.twig templates/Default/Mail/
docker compose exec -u www-data app bin/console lint:container
```

Run the full English magic-link flow end to end (the post-Task-4 form of the "Verification helper" recipe above, using `i18n-verify-mail-en@example.com` as the email address), and before following the link, inspect the Mailpit message directly to confirm the email is in English:

```bash
scratch=/tmp/pixel-tracks-verify
msg_id=$(curl -sS "http://localhost:8125/api/v1/messages" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
echo $d["messages"][0]["ID"] ?? "";
')
curl -sS "http://localhost:8125/api/v1/message/$msg_id" -o "$scratch/task8-en-mail.json"
php -r '
$d = json_decode(file_get_contents($argv[1]), true);
echo $d["Subject"] . PHP_EOL;
echo ($d["Text"] ?? "") . PHP_EOL;
' "$scratch/task8-en-mail.json"
```

Expected: subject "Here is your magic link", body containing "Here is a magic link to access your profile." (not the old typo'd version, not Portuguese).

Repeat the whole flow once in Portuguese (`/pt/link-magico`, using `i18n-verify-mail-pt@example.com` as the email address) and confirm the Mailpit email is in Portuguese this time — subject "Aqui está o seu link mágico", body "Aqui está um link mágico para aceder ao seu perfil.".

Then check the persisted `User.locale` for each of the two test accounts:

```bash
docker compose exec -u www-data app php -r '
$pdo = new PDO("sqlite:var/database/database.sqlite");
$stmt = $pdo->prepare("SELECT email, locale FROM users WHERE email IN (?, ?)");
$stmt->execute(["i18n-verify-mail-en@example.com", "i18n-verify-mail-pt@example.com"]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) { echo json_encode($row) . PHP_EOL; }
'
```

Expected: two rows — `i18n-verify-mail-en@example.com` with `locale: "en"`, `i18n-verify-mail-pt@example.com` with `locale: "pt"`.

- [ ] **Step 7: Commit**

```bash
git add templates/Default/magic-link.html.twig templates/Default/Mail/ src/Controller/MagicLinkController.php translations/
git commit -m "feat(i18n): translate magic-link page, flash messages, and transactional emails"
```

## Self-review notes

- **Spec coverage:** every decision in the spec has a task — translation infra (Task 1), persisted locale (Task 2), security/`IsGranted` (Task 3), localized routing + link fixes (Task 4), switcher + shared chrome (Task 5), and the three remaining page/flow translations (Tasks 6-8). The spec's explicit out-of-scope items (`map.html.twig`, a third language, GPX file content) are never touched by any task.
- **Placeholder scan:** no TBD/TODO; every translation key has both an English and Portuguese value decided in this plan, not deferred.
- **Type/name consistency:** `GpxValidationException`'s `getMessage()`/`getParameters()` contract (defined in Task 6 Step 1) is used consistently by Task 6 Step 3's `UploadController` catch block — no other task touches this class. Translation key names are used identically between the template/controller changes that reference them and the YAML blocks that define them (cross-checked each `|trans('...')` / `trans('...')` call against its corresponding YAML key while writing this plan). `app_route_name`/`app_route_params` (Task 5) are produced and consumed only within Task 5 itself — no later task depends on them.
- **Known risk flagged explicitly:** Task 4's Interfaces section and Step 9 verification call out, in detail, the one genuine implementation uncertainty in this plan — whether Symfony's locale-array route paths really do resolve `path()`/`redirectToRoute()` calls to the current request's locale with zero extra code, as documented. If that assumption is wrong, Task 4's own verification is designed to catch it immediately (comparing generated link prefixes against the session's actual locale) rather than letting Tasks 5-8 build on a broken foundation.
