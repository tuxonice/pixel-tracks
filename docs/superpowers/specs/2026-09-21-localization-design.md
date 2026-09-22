# Localization (i18n) — design

## Goal

Add English + Portuguese localization to pixel-tracks: translated UI strings across
every template, translated transactional emails, and locale-prefixed, per-language
URLs (e.g. `/en/profile` vs `/pt/perfil`) for every user-facing page — while keeping
the app's existing standard-Symfony routing/security architecture (attribute-based
`#[Route]`, `access_control`) rather than adopting a bespoke routing layer.

## Decisions

- **Two languages: English (default) and Portuguese.** `enabled_locales: ['en', 'pt']`,
  `default_locale: 'en'` in `framework.yaml`. Adding a third language later is just
  another translations file plus another array entry on each localized route's `path`.
- **Symfony's built-in localized routing, not a custom routing layer.** Symfony's
  `#[Route]` attribute accepts a `path` as a locale-keyed array
  (`path: ['en' => '/profile/', 'pt' => '/perfil/']`); Symfony registers one route per
  locale internally and its own `path()`/`url()` Twig functions and
  `UrlGeneratorInterface::generate()` already resolve the correct locale variant from
  the current request's locale. The
  [weather-dashboard](https://github.com/tuxonice/weather-dashboard) reference project
  (raised during the earlier Bootstrap 5 work) builds this itself with a custom
  `RouteLoader`/`LocalizedRoutingExtension` — but that project doesn't use Symfony's
  standard router at all (it has its own micro-framework `Kernel`). pixel-tracks
  already uses standard attribute routing, so the native mechanism gets the same
  translated-URL behavior with far less code and no new routing infrastructure.
- **Only user-facing GET pages get translated slugs; technical/action routes stay
  single and unprefixed.** See "Routing" below for the exact split and why.
- **`#[IsGranted('PUBLIC_ACCESS')]` attributes replace path-regex `access_control`
  rules for the two public routes**, because translated slugs make regex-matching a
  raw request path (`^/send-magic-link`) fragile — it would need to match every
  locale's translated slug and stay hand-synced with the translation file.
  `access_control` collapses to a single default-deny rule.
- **Locale resolution relies on Symfony's built-in behavior**: when a matched route
  carries a `_locale` (which happens automatically for locale-keyed `path` routes),
  Symfony's `RouterListener` already calls `Request::setLocale()` and the Translator
  picks it up — no custom `LocaleSubscriber`-equivalent is needed for this part
  (unlike the reference project, which needs one only because its custom kernel
  doesn't have Symfony's `RouterListener` either). A small new listener is still
  needed, but only for exposing the current route/params to templates for the
  language switcher (see "Language switcher" below) — a much narrower piece than the
  reference project's subsystem.
- **Emails are translated too**, which requires a persisted per-`User` locale
  preference (new `locale` column + migration), because a magic-link email is
  composed and sent server-side, not rendered as part of a page a browser is
  currently viewing in a particular language.
- **Translation storage**: `translations/messages.{en,pt}.yaml`, the standard Symfony
  location/format (matches the reference project's convention too).

## Current state (for reference)

No localization exists today: no `symfony/translation` dependency, no
`translations/` directory, no `|trans` usage anywhere, `framework.yaml` has no
`default_locale`/`enabled_locales`. Every user-facing string is hardcoded English, in
both Twig templates and PHP (`addFlash()` calls, GPX validation exception messages).

### Full route inventory

| Route name | Path | Methods | Localize? |
|---|---|---|---|
| `app_home` | `/` | GET | No — stays root, redirects to localized `app_profile` |
| `app_profile` | `/profile/` | GET | **Yes** |
| `app_profile_page` | `/profile/{page}` | GET | **Yes** |
| `app_magic_link_request` | `/send-magic-link` | GET | **Yes** |
| `app_magic_link_send` | `/send-magic-link` | POST | **Yes** (same slug as the GET route — the form posts back to the page it's on) |
| `app_login_check` | `/login/check` | GET | No — email auth-callback link, never viewed as content |
| `app_logout` | `/logout` | GET | No — action link, not bookmarked/shared |
| `app_track_map` | `/map/{trackKey}` | GET | **Yes** |
| `app_track_upload` | `/track/upload` | POST | No — form-action target only |
| `app_track_info` | `/track/info/{trackKey}` | GET | **Yes** |
| `app_track_delete` | `/track/delete` | POST | No — form-action target only |

Exact translated Portuguese slugs (e.g. `perfil`, `percurso`, `mapa`) are a content
decision made during implementation, not an architectural one — any reasonable
translation is fine as long as it's consistent.

### Hardcoded paths that must change

Three places build a `RedirectResponse` from a literal path string rather than
generating it from a route name, and would silently redirect to the wrong locale
(or a 404, since the literal path won't exist once English/Portuguese are the real
paths) once routes are localized:

- `src/Security/MagicLinkEntryPoint.php:14` — `new RedirectResponse('/send-magic-link')`
- `src/Security/LoginSuccessHandler.php:14` — `new RedirectResponse('/profile/')`
- `src/Security/LoginFailureHandler.php` — `new RedirectResponse('/send-magic-link')`,
  plus a hardcoded English flash message

All three must instead inject `Symfony\Component\Routing\Generator\UrlGeneratorInterface`
and call `->generate('app_profile', …)` / `->generate('app_magic_link_request', …)`,
which resolve to the request's current locale automatically. Every other redirect in
the app already goes through `$this->redirectToRoute(...)` (a route name, not a raw
path) and needs no change.

### Magic-link email locale

`MagicLinkController::sendMagicLink()` calls `$this->renderView(...)` synchronously,
within the same request whose locale was just resolved from the page the user
submitted the form on. Twig's translator already renders in the current request's
locale by default, so **the email is correctly localized with no special handling** —
the only addition needed is persisting that same locale onto the `User` record
(`$user->setLocale($request->getLocale())`) as their stored preference, both for the
language switcher default and for correctness if magic links are ever triggered
outside a synchronous request in the future.

## Out of scope

- A third language, or any language beyond en/pt.
- Translating GPX file *content* (track names, filenames) — only app UI/chrome.
- `templates/Default/map.html.twig` and its Leaflet/jsPanel UI — this is a standalone
  page outside the app's shared layout; localizing it is a separate, later effort if
  wanted (it currently has almost no user-facing text besides "Information panel" and
  the info-panel labels).
- Any automated test suite — none exists per `CLAUDE.md`; not introduced here.
- Locale auto-detection sophistication beyond a simple `Accept-Language` header check
  for the one case that needs it (the root `/` redirect for a not-yet-authenticated,
  no-session visitor).

## Routing implementation notes

- Localized routes use the array-path form directly on the existing `#[Route]`
  attributes, e.g.:
  ```php
  #[Route(path: ['en' => '/profile/', 'pt' => '/perfil/'], name: 'app_profile', methods: ['GET'])]
  ```
  Symfony registers this internally as `app_profile.en` / `app_profile.pt`; templates
  and controllers keep calling `path('app_profile', ...)` /
  `$this->redirectToRoute('app_profile', ...)` unchanged — Symfony's generator picks
  the variant matching the current locale.
- `app_home` (`/`) has no locale prefix. Its controller inspects
  `Request::getPreferredLanguage(['en', 'pt'])` (Symfony's own `Accept-Language`
  negotiation, matched against `enabled_locales`) and redirects to
  `$this->redirectToRoute('app_profile', ['_locale' => $preferred])`.

## Language switcher

A small link/toggle (in the navbar) that re-visits the *current page* in the other
language. Building its target URL needs the current route's *locale-stripped* name
and its route parameters. A new lightweight `kernel.request` listener
(`src/EventListener/LocaleTemplateGlobalsListener.php` or similar) reads
`$request->attributes->get('_route')` (e.g. `app_profile.en`), strips the trailing
`.en`/`.pt` suffix, and exposes the bare route name + `_route_params` as Twig globals
(`app_route_name`, `app_route_params`), alongside the current locale (`app_locale`).
The header template then renders, for the *other* enabled locale:
`path(app_route_name, app_route_params|merge({_locale: other_locale}))`.

For routes that are *not* localized (technical routes), the switcher isn't shown or
simply isn't relevant — those pages don't vary by locale to begin with.

## String extraction scope

Every hardcoded string becomes a `translations/messages.{en,pt}.yaml` key, referenced
via Twig's `|trans` filter or `TranslatorInterface::trans()` in PHP:

- **Templates**: `Default/Blocks/header.html.twig` (nav labels + new switcher),
  `Default/home.html.twig` (headings, table header, form labels, button, empty
  state), `Default/track.html.twig` (headings, labels, buttons, modal text),
  `Default/magic-link.html.twig` (headings, copy, button, validation message),
  the error page templates under `templates/bundles/TwigBundle/Exception/` and
  `templates/Error/base.html.twig`.
- **Controller flash messages** (`addFlash()` calls) in `MagicLinkController`,
  `TrackController`, `UploadController`, `MapController`, `LoginFailureHandler`.
- **GPX validation messages** in `App\Service\GpxValidator` — these are exception
  messages today; they become translation keys, translated at the point a controller
  catches the exception and turns it into a flash message (the exception itself can
  carry a key/reason rather than a final English sentence).
- **Mail templates**: `Default/Mail/magic-link-html.html.twig` and
  `magic-link-text.txt.twig` (subject line, body copy, button label).
- **Not translated**: the footer's "beyond the mountains" tagline (a brand phrase,
  not content) and anything inside `Default/map.html.twig` (out of scope, above).

## Data layer

- `src/Entity/User.php`: new `locale` property (`string`, default `'en'`), getter/
  setter.
- New migration adding a `locale` column (`VARCHAR`, `NOT NULL DEFAULT 'en'`) to the
  `users` table, generated the normal way via
  `bin/console doctrine:migrations:diff` / hand-adjusted like the project's existing
  migrations.

## Testing / verification

No automated test suite exists (per `CLAUDE.md`). Verification is manual, using the
`run` skill against the Docker dev stack, in both `en` and `pt`:

1. Visit `/` with no `Accept-Language` preference set (defaults to `en`) and with a
   `pt`-preferring `Accept-Language` header — confirm the redirect lands on the
   correctly localized `/en/profile` or `/pt/perfil`.
2. Visit each localized page directly in both locales (`/en/...` and `/pt/...`) and
   confirm all strings render in the right language, with no missing-translation
   fallback markers.
3. Use the language switcher from several different pages (profile, track info, map
   link) and confirm it lands on the *same* page in the other language, not the
   homepage.
4. Confirm the technical routes (`/login/check`, `/logout`, `/track/upload`,
   `/track/delete`) are reachable and still work, unprefixed, in both locale
   contexts.
5. Request a magic link while on a Portuguese page; confirm the received email
   (via Mailpit) is in Portuguese, and that the `User` row's `locale` column was
   updated.
6. Confirm an unauthenticated visit to a private, localized page (e.g.
   `/pt/percurso/...`) still redirects to the localized `send-magic-link` page,
   not a 404 or an English one.
7. Confirm GPX validation error flash messages render translated in both locales
   (trigger by uploading an invalid file).
