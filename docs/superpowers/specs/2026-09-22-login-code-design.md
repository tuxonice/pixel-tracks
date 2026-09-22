# Replace magic-link auth with a numeric login code — design

## Goal

Replace pixel-tracks's clickable magic-link authentication with a two-step,
same-session numeric-code flow: a visitor submits their email, receives a
6-digit code by email, and types it into a form on the same page/tab to log
in. This fully replaces the magic link as the sole authentication factor — it
is not an addition on top of it, and there is no login path left that doesn't
go through a code.

## Decisions

- **6-digit numeric code**, generated with `random_int()` (CSPRNG, not
  `mt_rand()`), zero-padded (e.g. `049213`).
- **Same-session continuation**: after submitting the request-code form, the
  browser is taken straight to a "enter your code" page; the pending login is
  tracked server-side by session, so the user never re-types their email.
  Trade-off (explicitly accepted): the code must be entered in the same
  browser/tab that requested it — unlike the magic link, this doesn't work
  across devices (e.g. request on a laptop, open the email on a phone).
- **Hashed at rest.** The code is stored via `password_hash()`/
  `password_verify()`, the same treatment as a password, even though it's
  short-lived — never stored in plaintext.
- **Stored directly on `User`**, not a new entity/table: three new nullable
  columns (`loginCodeHash`, `loginCodeExpiresAt`, `loginCodeAttempts`).
  Mirrors how `User.locale` was added for a single-purpose need. A user only
  ever has one *valid* pending code — requesting a new one overwrites and
  invalidates any previous one.
- **5-minute expiry**, reusing the existing `LOGIN_TOLERANCE_TIME` env var
  (its purpose — "how long the credential is valid" — carries over
  unchanged; only the credential type changes).
- **Brute-force protection via a per-code attempt cap, not a new rate
  limiter.** A 6-digit code is only 1-in-1,000,000, guessable in principle
  (unlike a cryptographically signed link), so each pending code gets a hard
  cap of **5 verification attempts** (`User::loginCodeAttempts`); exceeding
  it invalidates the code and forces a fresh request. The existing
  IP/email send-rate limiters (renamed `login_code_by_ip`/
  `login_code_by_email`) still guard the *request* step, unchanged in
  policy. No additional IP-based limiter on the verify step — the attempt
  cap alone gives a blind guesser 0.0005% odds within the cap, judged
  sufficient for this app's threat model.
- **Single-use.** A successful verification immediately clears the stored
  hash/expiry/attempts — same guarantee the magic link had via
  `used_link_cache`/`max_uses: 1`.
- **Symfony's `login_link` firewall is removed entirely** and replaced with
  a minimal custom `AuthenticatorInterface` implementation
  (`App\Security\LoginCodeAuthenticator`), registered via
  `custom_authenticators`. This is a verified requirement, not a
  convenience choice — see "Why a custom authenticator, precisely" below;
  a bare `Security::login($user)` call with zero authenticators configured
  throws.
- **All verification/business logic lives in the controller**, not in the
  authenticator. `LoginCodeAuthenticator` is deliberately thin: its
  `supports()` always returns `false` (it never activates for an incoming
  request the normal way) and its `authenticate()` is unreachable dead code
  required only for interface compliance. Only `createToken()` (builds the
  token) and `onAuthenticationSuccess()` are ever actually invoked, via
  Symfony's `authenticateUser()` — see below.

## Why a custom authenticator, precisely

Verified by reading the installed `vendor/symfony/security-bundle/Security.php`
and `vendor/symfony/security-http/Authentication/AuthenticatorManager.php`
directly (Symfony 7.4), not assumed from memory:

- `Security::login(UserInterface $user)` (no `$authenticatorName` given)
  calls a private `getAuthenticator()` that requires **exactly one**
  authenticator registered on the current firewall — zero throws
  `LogicException('No authenticators found for firewall "main".')`, more
  than one throws a different `LogicException` asking for an explicit name.
- The resolved authenticator is then passed to
  `AuthenticatorManager::authenticateUser()`, which does **not** call
  `supports()` or `authenticate()` at all. It builds a
  `SelfValidatingPassport` itself, calls
  `$authenticator->createToken($passport, $firewallName)`, dispatches the
  token-created event, then calls `handleAuthenticationSuccess()` — which
  is what actually invokes the authenticator's `onAuthenticationSuccess()`
  hook and stores the token in the session.

`createToken()` doesn't even need writing: `Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator`
(also confirmed by reading its source) provides exactly the needed default —
`return new PostAuthenticationToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());`
— so `LoginCodeAuthenticator extends AbstractAuthenticator` and only needs to
override the remaining three interface methods, of which only one does real
work:

```php
public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
{
    return null; // the controller issues its own redirect after calling Security::login()
}
```

`supports()` returns `false` unconditionally; `authenticate()` throws
`\LogicException('LoginCodeAuthenticator never authenticates a request directly — verification happens in LoginController::verifyCode().')`;
`onAuthenticationFailure()` returns `null` — all three are unreachable in
this design (the controller detects a wrong/expired/exhausted code itself
and redisplays the verify form with a flash message; it never lets Symfony's
authenticator failure machinery see an `AuthenticationException`).

## Current state (for reference)

- `MagicLinkController` (routes `app_magic_link_request` GET,
  `app_magic_link_send` POST, both `/en/send-magic-link` /
  `/pt/link-magico`): renders the email form, rate-limits by IP/email
  (`magic_link_by_ip`/`magic_link_by_email` in `framework.yaml`), looks up
  or creates a `User`, persists their current locale, and emails a link via
  `LoginLinkHandlerInterface`.
- `LoginController` (`app_login_check`, `/login/check`): an unreachable stub
  whose only job is to exist so the `login_link` firewall's authenticator
  has a route to intercept.
- `config/packages/security.yaml`'s `main` firewall has a `login_link:`
  block (`check_route: app_login_check`, `signature_properties: ['email']`,
  `lifetime: LOGIN_TOLERANCE_TIME`, `max_uses: 1`,
  `used_link_cache: cache.app`, `success_handler`/`failure_handler`) and an
  `entry_point: App\Security\MagicLinkEntryPoint`.
- `LoginSuccessHandler`/`LoginFailureHandler` implement
  `AuthenticationSuccessHandlerInterface`/`AuthenticationFailureHandlerInterface`,
  redirecting to `/profile` or back to the request form with a translated
  flash, respectively — both are tied to the `login_link` authenticator's
  lifecycle and become dead code once it's removed.
- `MagicLinkEntryPoint` redirects unauthenticated access attempts to
  `app_magic_link_request`.
- `access_control` grants `PUBLIC_ACCESS` to both locale variants of
  `/send-magic-link` and to `^/login` (covering `/login/check`).
- Templates: `templates/Default/magic-link.html.twig` (email form),
  `templates/Default/Mail/magic-link-{html,text}.{html.twig,txt.twig}`
  (the email itself, containing a clickable button).
- Translations: `magic_link.*` keys (title/intro/email_invalid_feedback/
  send), `mail.*` keys, and `flash.invalid_or_expired_link`.
- Tests: `tests/Functional/MagicLinkControllerTest.php`,
  `AuthenticationFlowTest.php`, `AccessControlTest.php` all exercise the
  current link-based flow end-to-end (request a link via Mailpit-less
  direct `LoginLinkHandlerInterface` calls in test setup, visit it, assert
  redirects).

## Out of scope

- Any second factor on top of the code (this replaces the single factor,
  it doesn't add a second one).
- "Remember me" / persistent sessions beyond what exists today.
- Cross-device code entry (explicitly accepted trade-off above).
- Any change to how a `User` is identified (still keyed by email) or to
  GPX/track features.
- SMS or any non-email delivery channel.

## Routing

| Route name | Path (en / pt) | Methods | Notes |
|---|---|---|---|
| `app_login_request` | `/en/login` / `/pt/entrar` | GET | Email-entry form (replaces `app_magic_link_request`) |
| `app_login_send` | `/en/login` / `/pt/entrar` | POST | Validates email, rate-limits, generates+emails code, redirects to verify (replaces `app_magic_link_send`) |
| `app_login_verify` | `/en/login/verify` / `/pt/entrar/verificar` | GET | Code-entry form; redirects back to `app_login_request` if there's no pending session |
| `app_login_verify_submit` | `/en/login/verify` / `/pt/entrar/verificar` | POST | Validates the code, logs in on success or redisplays with a flash on failure |

`app_login_check` is deleted outright (no replacement needed — nothing
intercepts a route the way the `login_link` authenticator did; verification
is a normal controller action now).

`access_control`'s public rule becomes (mirroring the exact pattern used for
the current magic-link paths, including the locale-array literal-path
lesson learned during the localization work — no loose prefix matching):

```yaml
access_control:
    - { path: ^/(en/login(/verify)?|pt/entrar(/verificar)?)$, roles: PUBLIC_ACCESS }
    - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

Note the trailing `$` anchor — without it, the regex would also match
`/en/login-evil` or similar, the same unanchored-regex gap the final
whole-branch review flagged (as pre-existing, non-regression tech debt) at
the end of the localization work. `access_control` matches against
`Request::getPathInfo()`, which never includes the query string, so
anchoring at `$` is safe.

(The old `^/login` rule for `/login/check` is removed along with that route.)

## Session state

On a successful `app_login_send`, the controller stores the user's id in the
session under a single dedicated key (e.g. `pending_login_user_id`). This is
cleared on successful verification, and overwritten (not accumulated) if the
user requests another code. `app_login_verify` (GET) checks for this key and
redirects to `app_login_request` if it's absent — covers the case where a
verify-page bookmark/reload happens after the session state is gone (browser
restart, expired session, etc.).

## Data layer

- `src/Entity/User.php`: three new nullable properties —
  `loginCodeHash: ?string`, `loginCodeExpiresAt: ?\DateTimeImmutable`,
  `loginCodeAttempts: int` (default `0`) — with getters/setters, and a
  small `clearLoginCode(): void` helper that nulls all three
  (hash/expiresAt) and resets attempts to `0`, used both after a successful
  login and when attempts are exhausted.
- New migration adding these three columns to `users`.

## Controller design (`src/Controller/LoginController.php`, replacing `MagicLinkController` + the old `LoginController`)

- `requestLogin()` (GET `app_login_request`): renders the email form.
  Unchanged in shape from today's `requestMagicLink()`.
- `sendCode()` (POST `app_login_send`): same CSRF + IP/email rate-limit +
  email-validation steps as today's `sendMagicLink()`. On a valid email:
  look up or create the `User`, persist their locale (unchanged from
  today), generate a 6-digit code, hash it, set
  `loginCodeExpiresAt = now + LOGIN_TOLERANCE_TIME seconds`, reset
  `loginCodeAttempts = 0`, flush, email the code, store
  `pending_login_user_id` in the session, redirect to `app_login_verify`
  (not back to the request page — the current magic-link flow re-renders
  the *same* request form with a flash; this flow moves the visitor
  forward to the next step, matching a standard OTP UX).
- `showVerifyForm()` (GET `app_login_verify`): if no
  `pending_login_user_id` in session, redirect to `app_login_request`
  with a flash explaining why. Otherwise render the code-entry form.
- `verifyCode()` (POST `app_login_verify_submit`): CSRF check; if no
  `pending_login_user_id` in session, redirect to `app_login_request`.
  Load the `User`; if `loginCodeHash` is null or `loginCodeExpiresAt` has
  passed, clear session + flash "expired, request a new one" + redirect to
  `app_login_request`. If `loginCodeAttempts >= 5`, same expired-style
  handling (the cap has already been reached). Otherwise
  `password_verify()` the submitted code against `loginCodeHash`:
  - **Match**: call `$user->clearLoginCode()`, flush, remove
    `pending_login_user_id` from the session, call
    `$security->login($user)` (via the injected
    `Symfony\Bundle\SecurityBundle\Security` service), then
    `redirectToRoute('app_profile', ['_locale' => $user->getLocale()])`
    (the same locale-aware redirect pattern already used elsewhere in this
    app for actions on non-locale-prefixed routes).
  - **No match**: increment `loginCodeAttempts`, flush, flash a translated
    "incorrect code" message, redisplay the verify form (redirect back to
    `app_login_verify` — a fresh GET, not staying on the POST response —
    so a reload doesn't resubmit).

## Files touched

**New:**
- `src/Security/LoginCodeAuthenticator.php`
- `templates/Default/login-verify.html.twig`

**Renamed (content also changes):**
- `src/Controller/MagicLinkController.php` → `src/Controller/LoginController.php`
  (absorbing the old stub `LoginController`, which is deleted)
- `src/Security/MagicLinkEntryPoint.php` → `src/Security/LoginEntryPoint.php`
- `templates/Default/magic-link.html.twig` → `templates/Default/login-request.html.twig`
- `templates/Default/Mail/magic-link-html.html.twig` → `templates/Default/Mail/login-code-html.html.twig`
- `templates/Default/Mail/magic-link-text.txt.twig` → `templates/Default/Mail/login-code-text.txt.twig`

**Deleted:**
- `src/Security/LoginSuccessHandler.php`
- `src/Security/LoginFailureHandler.php`

**Modified:**
- `src/Entity/User.php` (three new columns + `clearLoginCode()`)
- new migration file
- `config/packages/security.yaml` (`login_link:` → `custom_authenticators:`,
  `entry_point` renamed, `access_control` updated)
- `config/packages/framework.yaml` (rate limiter names
  `magic_link_by_ip`/`magic_link_by_email` → `login_code_by_ip`/
  `login_code_by_email`)
- `templates/Default/Blocks/header.html.twig` — no route names referenced
  here today besides `app_profile`/`app_logout`, so likely untouched;
  confirm during implementation.
- `translations/messages.{en,pt}.yaml` — `magic_link.*` → `login.*`,
  `mail.*` keys reworded for a code instead of a link/button, new keys for
  the verify step (title/intro/invalid code/expired/too many attempts/
  resend-or-start-over link), `flash.invalid_or_expired_link` reworded or
  replaced by the new expiry/attempt-specific keys.
- `CLAUDE.md`, `README.md` — both describe the magic-link mechanism by
  name in several places (Auth section, routing table, env var docs,
  `APP_SECRET`'s "for magic-link signatures" comment, Mailpit blurb) and
  need updating to describe the code flow instead.
- Tests: `tests/Functional/MagicLinkControllerTest.php` → rewritten as
  `LoginControllerTest.php` covering both steps; `AuthenticationFlowTest.php`
  and `AccessControlTest.php` updated for the new routes/flow. This is a
  substantial rewrite, not a find-and-replace — the current tests drive
  `LoginLinkHandlerInterface` directly to get a link; the new tests need to
  drive the two-step controller flow (submit email → read the code back out
  via a test-only accessor or by re-fetching the `User` and reading a
  freshly-added test helper, since the real code is only ever emailed, not
  handed back in the HTTP response).

## Testing / verification

No automated test suite existed for a while during this app's rewrite, but
one now does (`tests/`, PHPUnit via `make tests`) — this change must keep it
green, not just be manually verified. Plan for:

1. Unit-level: a fake/testable code-hashing round trip, and the attempt-cap
   logic, are straightforward to unit test without a browser client.
2. Functional: full request → verify → profile round trip via
   `KernelBrowser`, reading the real code out of the test's own database
   query (or a test-mode accessor) since there's no link to extract from an
   email body anymore. Wrong-code, expired-code, and attempts-exhausted
   paths each need their own test.
3. Manual, using the `run` skill against the Docker dev stack with Mailpit,
   in both `en` and `pt`: request a code, confirm the email contains a
   6-digit code (not a link), enter it correctly and confirm login, then
   separately confirm a wrong code increments attempts and shows the
   correct flash, and that 5 wrong attempts forces a fresh request.
