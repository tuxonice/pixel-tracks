# Numeric Login Code Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace pixel-tracks's magic-link authentication with a two-step, same-session 6-digit numeric login code, fully replacing the `login_link` firewall — not adding a second factor alongside it.

**Architecture:** A minimal custom `AuthenticatorInterface` implementation (verified against the installed Symfony source — see the spec) gives `Security::login()` something to resolve; all real verification logic (hashing, expiry, attempt-capping) lives in a rewritten `LoginController` that owns the whole two-step flow (request → email a code → verify → log in).

**Tech Stack:** Symfony 7.4 Security component (`custom_authenticators`, `Security::login()`), Doctrine ORM migration, Symfony Mailer, PHPUnit (`symfony/phpunit-bridge`, already installed).

**Spec:** `docs/superpowers/specs/2026-09-22-login-code-design.md`

## Global Constraints

- 6-digit numeric code, `random_int()` (CSPRNG), zero-padded, hashed at rest via `password_hash()`/`password_verify()` — never stored in plaintext.
- 5-minute expiry, reusing the existing `LOGIN_TOLERANCE_TIME` env var unchanged.
- Max 5 verification attempts per pending code; exceeding it invalidates the code and requires a fresh request.
- Same-session continuation: the pending login is tracked via `$request->getSession()`, keyed `pending_login_user_id` — the verify step never re-asks for the email.
- This fully replaces the magic link — no coexistence period, no second factor. `login_link:`, `LoginSuccessHandler`, `LoginFailureHandler`, and the old stub `LoginController` are all removed.
- Routes: `app_login_request`/`app_login_send` at `/en/login` / `/pt/entrar`; `app_login_verify`/`app_login_verify_submit` at `/en/login/verify` / `/pt/entrar/verificar`. `app_login_check` is deleted outright (nothing replaces it — there's no link to intercept any more).
- No automated test suite existed for a while during this app's history, but one now does (`tests/`, run via `make tests`) and **must stay green** — this plan includes rewriting the three affected test files and adding new unit tests, not just manual verification.
- The app runs via Docker Compose (`make start`); `bin/console`/`composer` commands run inside the container via `docker compose exec -u www-data app <command>`; `make tests` runs the suite.

---

## Verification helper: manual end-to-end check via Mailpit

Used by Task 3's manual verification. Routes are locale-prefixed; substitute `/pt/entrar`/`/pt/entrar/verificar` and a different email address to test Portuguese (the magic-link rate limiter is per-email too).

```bash
scratch=/tmp/pixel-tracks-verify
mkdir -p "$scratch"
jar="$scratch/cookies.txt"
rm -f "$jar"

# 1. Get the request-code form and its CSRF token
form=$(curl -sS -c "$jar" http://localhost/en/login)
token=$(echo "$form" | grep -oP 'name="_token" value="\K[^"]+')

# 2. Submit it
curl -sS -b "$jar" -c "$jar" -L -o "$scratch/verify-page.html" -w "%{url_effective}\n" \
  -d "email=login-code-verify@example.com" -d "_token=$token" \
  http://localhost/en/login

# 3. Pull the code out of the email Mailpit just received
msg_id=$(curl -sS "http://localhost:8125/api/v1/messages" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
echo $d["messages"][0]["ID"] ?? "";
')
code=$(curl -sS "http://localhost:8125/api/v1/message/$msg_id" | php -r '
$d = json_decode(file_get_contents("php://stdin"), true);
$text = $d["Text"] ?? $d["HTML"] ?? "";
if (preg_match("/\\\\b(\\\\d{6})\\\\b/", $text, $m)) { echo $m[1]; }
')
echo "code: $code"

# 4. Submit it on the verify form
verify_token=$(grep -oP 'name="_token" value="\K[^"]+' "$scratch/verify-page.html")
curl -sS -b "$jar" -c "$jar" -L -o "$scratch/profile.html" -w "%{url_effective}\n" \
  -d "code=$code" -d "_token=$verify_token" \
  http://localhost/en/login/verify
```

Expected: the last request's effective URL ends in `/en/profile/`, and `$scratch/profile.html` renders the profile page (not the login form again).

---

### Task 1: `User` entity — login code storage

**Files:**
- Modify: `src/Entity/User.php`
- Create: `migrations/Version<generated-timestamp>.php` (via `doctrine:migrations:diff`)
- Create: `tests/Unit/Entity/UserTest.php`

**Interfaces:**
- Produces: `User::getLoginCodeHash(): ?string`, `setLoginCodeHash(string $hash): void`,
  `getLoginCodeExpiresAt(): ?\DateTimeImmutable`, `setLoginCodeExpiresAt(\DateTimeImmutable $expiresAt): void`,
  `getLoginCodeAttempts(): int`, `setLoginCodeAttempts(int $attempts): void`,
  `incrementLoginCodeAttempts(): void`, `clearLoginCode(): void` (nulls hash+expiresAt, resets attempts to `0`).
  Every later task's controller/tests use these exact names.

- [ ] **Step 1: Write the failing unit test — `tests/Unit/Entity/UserTest.php`**

```php
<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testANewUserHasNoLoginCodePending(): void
    {
        $user = new User('someone@example.com');

        self::assertNull($user->getLoginCodeHash());
        self::assertNull($user->getLoginCodeExpiresAt());
        self::assertSame(0, $user->getLoginCodeAttempts());
    }

    public function testSettingALoginCodeMakesItReadableAgain(): void
    {
        $user = new User('someone@example.com');
        $expiresAt = new \DateTimeImmutable('+5 minutes');

        $user->setLoginCodeHash('a-hash');
        $user->setLoginCodeExpiresAt($expiresAt);
        $user->setLoginCodeAttempts(2);

        self::assertSame('a-hash', $user->getLoginCodeHash());
        self::assertSame($expiresAt, $user->getLoginCodeExpiresAt());
        self::assertSame(2, $user->getLoginCodeAttempts());
    }

    public function testIncrementLoginCodeAttemptsIncreasesByOneEachCall(): void
    {
        $user = new User('someone@example.com');

        $user->incrementLoginCodeAttempts();
        $user->incrementLoginCodeAttempts();

        self::assertSame(2, $user->getLoginCodeAttempts());
    }

    public function testClearLoginCodeResetsEverything(): void
    {
        $user = new User('someone@example.com');
        $user->setLoginCodeHash('a-hash');
        $user->setLoginCodeExpiresAt(new \DateTimeImmutable('+5 minutes'));
        $user->incrementLoginCodeAttempts();

        $user->clearLoginCode();

        self::assertNull($user->getLoginCodeHash());
        self::assertNull($user->getLoginCodeExpiresAt());
        self::assertSame(0, $user->getLoginCodeAttempts());
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit tests/Unit/Entity/UserTest.php
```

Expected: fatal error / failure — `getLoginCodeHash()` and friends don't exist yet on `User`.

- [ ] **Step 3: Add the columns and methods to `src/Entity/User.php`**

Add these three properties (near the existing `locale` property):

```php
    #[ORM\Column(nullable: true)]
    private ?string $loginCodeHash = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $loginCodeExpiresAt = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $loginCodeAttempts = 0;
```

Add these methods (near the other getters/setters):

```php
    public function getLoginCodeHash(): ?string
    {
        return $this->loginCodeHash;
    }

    public function setLoginCodeHash(string $loginCodeHash): void
    {
        $this->loginCodeHash = $loginCodeHash;
    }

    public function getLoginCodeExpiresAt(): ?\DateTimeImmutable
    {
        return $this->loginCodeExpiresAt;
    }

    public function setLoginCodeExpiresAt(\DateTimeImmutable $loginCodeExpiresAt): void
    {
        $this->loginCodeExpiresAt = $loginCodeExpiresAt;
    }

    public function getLoginCodeAttempts(): int
    {
        return $this->loginCodeAttempts;
    }

    public function setLoginCodeAttempts(int $loginCodeAttempts): void
    {
        $this->loginCodeAttempts = $loginCodeAttempts;
    }

    public function incrementLoginCodeAttempts(): void
    {
        ++$this->loginCodeAttempts;
    }

    public function clearLoginCode(): void
    {
        $this->loginCodeHash = null;
        $this->loginCodeExpiresAt = null;
        $this->loginCodeAttempts = 0;
    }
```

Note the `options: ['default' => 0]` on `loginCodeAttempts` — this project has previously hit a real bug (during the localization work) where Doctrine's migration diff generator drops a PHP-side default from the actual `ADD COLUMN` SQL, causing `doctrine:schema:validate` drift; declaring the DB-level default explicitly in the mapping avoids repeating it. `loginCodeHash`/`loginCodeExpiresAt` don't need a default — `nullable: true` is enough since their unset state is meaningfully `null`, not a fallback value.

- [ ] **Step 4: Run the unit test again to confirm it passes**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit tests/Unit/Entity/UserTest.php
```

Expected: 4 tests, all green.

- [ ] **Step 5: Generate and apply the migration**

```bash
docker compose exec -u www-data app bin/console doctrine:migrations:diff --no-interaction
docker compose exec -u www-data app bin/console doctrine:migrations:migrate --no-interaction
```

- [ ] **Step 6: Verify the schema matches the mapping exactly**

```bash
docker compose exec -u www-data app bin/console doctrine:schema:validate
```

Expected: both the mapping-files check and the sync-with-database check report `[OK]`. If it reports drift on `loginCodeAttempts`'s default, the generated migration's SQL is missing `DEFAULT 0` on that column's `ADD COLUMN` — add it by hand to the migration's `up()` before re-running `migrate`.

- [ ] **Step 7: Run the full suite to confirm nothing else broke**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit
```

Expected: all existing tests still pass (this task is purely additive).

- [ ] **Step 8: Commit**

```bash
git add src/Entity/User.php migrations/ tests/Unit/Entity/UserTest.php
git commit -m "feat(auth): add login code storage to User entity"
```

---

### Task 2: `LoginCodeAuthenticator`

**Files:**
- Create: `src/Security/LoginCodeAuthenticator.php`
- Create: `tests/Unit/Security/LoginCodeAuthenticatorTest.php`

**Interfaces:**
- Produces: `App\Security\LoginCodeAuthenticator`, a concrete, no-constructor-dependency class extending
  `Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator`. Task 3 registers it in
  `security.yaml`'s `custom_authenticators`. Not wired into any firewall yet in this task — it's an inert,
  freestanding class, safe to add without touching anything else.

- [ ] **Step 1: Write the failing unit test — `tests/Unit/Security/LoginCodeAuthenticatorTest.php`**

```php
<?php

namespace App\Tests\Unit\Security;

use App\Security\LoginCodeAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class LoginCodeAuthenticatorTest extends TestCase
{
    public function testItNeverSupportsARequestDirectly(): void
    {
        $authenticator = new LoginCodeAuthenticator();

        self::assertFalse($authenticator->supports(Request::create('/en/login/verify', 'POST')));
    }

    public function testAuthenticateIsUnreachableAndThrows(): void
    {
        $authenticator = new LoginCodeAuthenticator();

        $this->expectException(\LogicException::class);

        $authenticator->authenticate(Request::create('/en/login/verify', 'POST'));
    }

    public function testOnAuthenticationSuccessReturnsNullSoTheControllerHandlesTheRedirect(): void
    {
        $authenticator = new LoginCodeAuthenticator();
        $token = $this->createMock(TokenInterface::class);

        self::assertNull($authenticator->onAuthenticationSuccess(Request::create('/'), $token, 'main'));
    }

    public function testOnAuthenticationFailureReturnsNull(): void
    {
        $authenticator = new LoginCodeAuthenticator();

        self::assertNull($authenticator->onAuthenticationFailure(
            Request::create('/'),
            new \Symfony\Component\Security\Core\Exception\AuthenticationException()
        ));
    }
}
```

- [ ] **Step 2: Run it to confirm it fails**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit tests/Unit/Security/LoginCodeAuthenticatorTest.php
```

Expected: fatal error — the class doesn't exist yet.

- [ ] **Step 3: Create `src/Security/LoginCodeAuthenticator.php`**

```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

/**
 * Exists only so Symfony's Security::login() helper has exactly one authenticator to
 * resolve on the "main" firewall - it never authenticates a request directly.
 * LoginController::verifyCode() does all the actual code verification itself (hash
 * check, expiry, attempt cap) and calls Security::login() only after it has already
 * confirmed the user's identity, which is why supports()/authenticate() are dead code
 * here: Security::login() resolves this authenticator and calls createToken() (inherited
 * from AbstractAuthenticator) and onAuthenticationSuccess() directly, bypassing both.
 */
class LoginCodeAuthenticator extends AbstractAuthenticator
{
    public function supports(Request $request): ?bool
    {
        return false;
    }

    public function authenticate(Request $request): Passport
    {
        throw new \LogicException('LoginCodeAuthenticator never authenticates a request directly — verification happens in LoginController::verifyCode().');
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return null;
    }
}
```

- [ ] **Step 4: Run the unit test again to confirm it passes**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit tests/Unit/Security/LoginCodeAuthenticatorTest.php
```

Expected: 4 tests, all green.

- [ ] **Step 5: Run the full suite to confirm nothing else broke**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit
```

Expected: all tests still pass (this class isn't referenced by any config yet).

- [ ] **Step 6: Commit**

```bash
git add src/Security/LoginCodeAuthenticator.php tests/Unit/Security/LoginCodeAuthenticatorTest.php
git commit -m "feat(auth): add LoginCodeAuthenticator (not yet wired into a firewall)"
```

---

### Task 3: Replace the auth flow — security config, controller, templates, emails, translations

This is the core swap and must land as one commit: removing `login_link:` from `security.yaml` removes the
`LoginLinkHandlerInterface` service, so the old `MagicLinkController` (which autowires it) would fail to
instantiate the moment that config changes — the two must change together. Don't split this task's commit.

**Files:**
- Modify: `config/packages/security.yaml`
- Modify: `config/packages/framework.yaml`
- Modify: `config/services.yaml`
- Delete: `src/Security/LoginSuccessHandler.php`
- Delete: `src/Security/LoginFailureHandler.php`
- Delete: `src/Security/MagicLinkEntryPoint.php`
- Create: `src/Security/LoginEntryPoint.php`
- Delete: `src/Controller/MagicLinkController.php`
- Modify (full rewrite): `src/Controller/LoginController.php`
- Delete: `templates/Default/magic-link.html.twig`
- Create: `templates/Default/login-request.html.twig`
- Create: `templates/Default/login-verify.html.twig`
- Delete: `templates/Default/Mail/magic-link-html.html.twig`
- Delete: `templates/Default/Mail/magic-link-text.txt.twig`
- Create: `templates/Default/Mail/login-code-html.html.twig`
- Create: `templates/Default/Mail/login-code-text.txt.twig`
- Modify: `translations/messages.en.yaml`
- Modify: `translations/messages.pt.yaml`

**Interfaces:**
- Consumes: `App\Security\LoginCodeAuthenticator` (Task 2), `User::getLoginCodeHash()`/`setLoginCodeHash()`/
  `getLoginCodeExpiresAt()`/`setLoginCodeExpiresAt()`/`getLoginCodeAttempts()`/`setLoginCodeAttempts()`/
  `incrementLoginCodeAttempts()`/`clearLoginCode()` (Task 1).
- Produces: routes `app_login_request`, `app_login_send`, `app_login_verify`, `app_login_verify_submit`;
  session key `pending_login_user_id` (an `int` user id). Task 4's tests exercise these directly.

- [ ] **Step 1: Update `config/packages/security.yaml`**

Change:

```yaml
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
        - { path: ^/(en/send-magic-link|pt/link-magico), roles: PUBLIC_ACCESS }
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

to:

```yaml
        main:
            lazy: true
            provider: app_user_provider
            custom_authenticators:
                - App\Security\LoginCodeAuthenticator
            entry_point: App\Security\LoginEntryPoint
            logout:
                path: app_logout

    access_control:
        - { path: ^/(en/login(/verify)?|pt/entrar(/verificar)?)$, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
```

The trailing `$` anchor on the public rule is deliberate — without it the regex would also match e.g.
`/en/login-evil`, the same class of gap the localization work's final review flagged as pre-existing tech
debt elsewhere in this file. `access_control` matches against `Request::getPathInfo()`, which never
includes the query string, so anchoring at `$` is safe.

- [ ] **Step 2: Rename the rate limiters in `config/packages/framework.yaml`**

Change:

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

to:

```yaml
    rate_limiter:
        login_code_by_ip:
            policy: 'token_bucket'
            limit: '%env(int:RATE_LIMITER_MAX_CAPACITY)%'
            rate: { interval: '%app.rate_limiter_interval%', amount: '%env(int:RATE_LIMITER_MAX_CAPACITY)%' }
        login_code_by_email:
            policy: 'token_bucket'
            limit: '%env(int:RATE_LIMITER_MAX_CAPACITY)%'
            rate: { interval: '%app.rate_limiter_interval%', amount: '%env(int:RATE_LIMITER_MAX_CAPACITY)%' }
```

Also update the comment two lines above (in the same file) that reads
`# This is what keeps generated absolute URLs (magic links!) from being Host-header spoofed.`
to `# This is what keeps generated absolute URLs from being Host-header spoofed.` (the parenthetical no
longer applies — there are no more magic links to protect).

- [ ] **Step 3: Bind the login code TTL in `config/services.yaml`**

Add this line to the existing `_defaults.bind` block, alongside `string $emailFrom`/`int $paginationIpp`/etc.:

```yaml
            int $loginCodeTtl: '%env(int:LOGIN_TOLERANCE_TIME)%'
```

- [ ] **Step 4: Delete the now-dead security handler classes**

```bash
git rm src/Security/LoginSuccessHandler.php src/Security/LoginFailureHandler.php src/Security/MagicLinkEntryPoint.php
```

- [ ] **Step 5: Create `src/Security/LoginEntryPoint.php`**

```php
<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login_request'));
    }
}
```

- [ ] **Step 6: Delete the old `MagicLinkController`**

```bash
git rm src/Controller/MagicLinkController.php
```

- [ ] **Step 7: Replace `src/Controller/LoginController.php` entirely**

```php
<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginController extends AbstractController
{
    private const CODE_LENGTH = 6;
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailerInterface $mailer,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        #[Autowire(service: 'limiter.login_code_by_ip')]
        private readonly RateLimiterFactory $loginCodeByIpLimiterFactory,
        #[Autowire(service: 'limiter.login_code_by_email')]
        private readonly RateLimiterFactory $loginCodeByEmailLimiterFactory,
        private readonly string $emailFrom,
        private readonly int $loginCodeTtl,
    ) {
    }

    #[Route(path: ['en' => '/en/login', 'pt' => '/pt/entrar'], name: 'app_login_request', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function requestLogin(): Response
    {
        return $this->render('Default/login-request.html.twig');
    }

    #[Route(path: ['en' => '/en/login', 'pt' => '/pt/entrar'], name: 'app_login_send', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function sendCode(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('login', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        if (!$this->loginCodeByIpLimiterFactory->create($request->getClientIp())->consume(1)->isAccepted()) {
            return new Response('<h1>429 Too many requests</h1>', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $email = (string) $request->request->get('email');

        if (!$this->loginCodeByEmailLimiterFactory->create(sha1($email))->consume(1)->isAccepted()) {
            return new Response('<h1>429 Too many requests</h1>', Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->addFlash('danger', $this->translator->trans('flash.invalid_email'));

            return $this->redirectToRoute('app_login_request');
        }

        $user = $this->userRepository->findOneByEmail($email);
        if (!$user) {
            $user = new User($email);
            $this->entityManager->persist($user);
        }
        $user->setLocale($request->getLocale());

        $code = str_pad((string) random_int(0, 999999), self::CODE_LENGTH, '0', STR_PAD_LEFT);
        $user->setLoginCodeHash(password_hash($code, PASSWORD_DEFAULT));
        $user->setLoginCodeExpiresAt(new \DateTimeImmutable(sprintf('+%d seconds', $this->loginCodeTtl)));
        $user->setLoginCodeAttempts(0);
        $this->entityManager->flush();

        $request->getSession()->set('pending_login_user_id', $user->getId());

        $mail = (new Email())
            ->from($this->emailFrom)
            ->to($email)
            ->subject($this->translator->trans('mail.login_code_subject'))
            ->html($this->renderView('Default/Mail/login-code-html.html.twig', ['code' => $code]))
            ->text($this->renderView('Default/Mail/login-code-text.txt.twig', ['code' => $code]));

        $this->mailer->send($mail);

        $this->addFlash('success', $this->translator->trans('flash.code_sent'));

        return $this->redirectToRoute('app_login_verify');
    }

    #[Route(path: ['en' => '/en/login/verify', 'pt' => '/pt/entrar/verificar'], name: 'app_login_verify', methods: ['GET'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function showVerifyForm(Request $request): Response
    {
        $user = $this->pendingUser($request);
        if (!$user) {
            $this->addFlash('danger', $this->translator->trans('flash.no_pending_login'));

            return $this->redirectToRoute('app_login_request');
        }

        return $this->render('Default/login-verify.html.twig', ['email' => $user->getEmail()]);
    }

    #[Route(path: ['en' => '/en/login/verify', 'pt' => '/pt/entrar/verificar'], name: 'app_login_verify_submit', methods: ['POST'])]
    #[IsGranted('PUBLIC_ACCESS')]
    public function verifyCode(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('login-verify', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $user = $this->pendingUser($request);
        if (!$user) {
            $this->addFlash('danger', $this->translator->trans('flash.no_pending_login'));

            return $this->redirectToRoute('app_login_request');
        }

        $expiresAt = $user->getLoginCodeExpiresAt();
        if ($user->getLoginCodeHash() === null || $expiresAt === null || $expiresAt < new \DateTimeImmutable()) {
            $user->clearLoginCode();
            $this->entityManager->flush();
            $request->getSession()->remove('pending_login_user_id');
            $this->addFlash('danger', $this->translator->trans('flash.code_expired'));

            return $this->redirectToRoute('app_login_request');
        }

        if ($user->getLoginCodeAttempts() >= self::MAX_ATTEMPTS) {
            $user->clearLoginCode();
            $this->entityManager->flush();
            $request->getSession()->remove('pending_login_user_id');
            $this->addFlash('danger', $this->translator->trans('flash.too_many_attempts'));

            return $this->redirectToRoute('app_login_request');
        }

        $code = (string) $request->request->get('code');

        if (!password_verify($code, $user->getLoginCodeHash())) {
            $user->incrementLoginCodeAttempts();
            $this->entityManager->flush();
            $this->addFlash('danger', $this->translator->trans('flash.invalid_code'));

            return $this->redirectToRoute('app_login_verify');
        }

        $user->clearLoginCode();
        $this->entityManager->flush();
        $request->getSession()->remove('pending_login_user_id');

        $this->security->login($user);

        return $this->redirectToRoute('app_profile', ['_locale' => $user->getLocale()]);
    }

    private function pendingUser(Request $request): ?User
    {
        $userId = $request->getSession()->get('pending_login_user_id');
        if (!is_int($userId)) {
            return null;
        }

        return $this->userRepository->find($userId);
    }
}
```

- [ ] **Step 8: Delete the old magic-link page template and create the two new ones**

```bash
git rm templates/Default/magic-link.html.twig
```

Create `templates/Default/login-request.html.twig`:

```twig
{% extends "Default/base.html.twig" %}

{% block title %}Home{% endblock %}

{% block content %}
    <section class="py-5 text-center container">
        <div class="row py-lg-5">
            <div class="col-lg-6 col-md-8 mx-auto">
                <h1 class="fw-light">{{ 'login.title'|trans }}</h1>
                <p class="lead text-body-secondary">{{ 'login.intro'|trans }}</p>
                <div class="py-5 text-start">
                    <form class="needs-validation" method="POST" action="{{ path('app_login_send') }}">
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="hidden" name="_token" value="{{ csrf_token('login') }}"/>
                                <input type="email" class="form-control" name="email" id="email" placeholder="you@example.com" required="required"/>
                                <div class="invalid-feedback">
                                    {{ 'login.email_invalid_feedback'|trans }}
                                </div>
                            </div>
                        </div>
                        <button class="w-100 btn btn-primary btn-lg mt-4" type="submit">{{ 'login.send'|trans }}</button>
                    </form>
                </div>
            </div>
        </div>
    </section>
{% endblock %}
```

Create `templates/Default/login-verify.html.twig`:

```twig
{% extends "Default/base.html.twig" %}

{% block title %}Home{% endblock %}

{% block content %}
    <section class="py-5 text-center container">
        <div class="row py-lg-5">
            <div class="col-lg-6 col-md-8 mx-auto">
                <h1 class="fw-light">{{ 'login.verify_title'|trans }}</h1>
                <p class="lead text-body-secondary">{{ 'login.verify_intro'|trans({'%email%': email}) }}</p>
                <div class="py-5 text-start">
                    <form class="needs-validation" method="POST" action="{{ path('app_login_verify_submit') }}">
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="hidden" name="_token" value="{{ csrf_token('login-verify') }}"/>
                                <input type="text" class="form-control text-center fs-3" style="letter-spacing: 0.5em;" name="code" id="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="000000" required="required"/>
                                <div class="invalid-feedback">
                                    {{ 'login.code_invalid_feedback'|trans }}
                                </div>
                            </div>
                        </div>
                        <button class="w-100 btn btn-primary btn-lg mt-4" type="submit">{{ 'login.verify'|trans }}</button>
                    </form>
                    <p class="text-center mt-3">
                        <a href="{{ path('app_login_request') }}">{{ 'login.start_over'|trans }}</a>
                    </p>
                </div>
            </div>
        </div>
    </section>
{% endblock %}
```

- [ ] **Step 9: Delete the old mail templates and create the two new ones**

```bash
git rm templates/Default/Mail/magic-link-html.html.twig templates/Default/Mail/magic-link-text.txt.twig
```

Create `templates/Default/Mail/login-code-text.txt.twig`:

```twig
{{ 'mail.login_code_body'|trans }}

{{ code }}
```

Create `templates/Default/Mail/login-code-html.html.twig` (identical boilerplate/inline-CSS wrapper to the
deleted `magic-link-html.html.twig` — this is a self-contained, table-based HTML email, unrelated to the
app's own Bootstrap 5 layout — with the button block replaced by a prominent code display):

```twig
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
    <style type="text/css">
        body,table,td{font-family:Helvetica,Arial,sans-serif !important}.ExternalClass{width:100%}.ExternalClass,.ExternalClass p,.ExternalClass span,.ExternalClass font,.ExternalClass td,.ExternalClass div{line-height:150%}a{text-decoration:none}*{color:inherit}a[x-apple-data-detectors],u+#body a,#MessageViewBody a{color:inherit;text-decoration:none;font-size:inherit;font-family:inherit;font-weight:inherit;line-height:inherit}img{-ms-interpolation-mode:bicubic}table:not([class^=s-]){font-family:Helvetica,Arial,sans-serif;mso-table-lspace:0pt;mso-table-rspace:0pt;border-spacing:0px;border-collapse:collapse}table:not([class^=s-]) td{border-spacing:0px;border-collapse:collapse}@media screen and (max-width: 600px){.w-full,.w-full>tbody>tr>td{width:100% !important}.w-24,.w-24>tbody>tr>td{width:96px !important}.w-40,.w-40>tbody>tr>td{width:160px !important}.p-lg-10:not(table),.p-lg-10:not(.btn)>tbody>tr>td,.p-lg-10.btn td a{padding:0 !important}.p-3:not(table),.p-3:not(.btn)>tbody>tr>td,.p-3.btn td a{padding:12px !important}.p-6:not(table),.p-6:not(.btn)>tbody>tr>td,.p-6.btn td a{padding:24px !important}*[class*=s-lg-]>tbody>tr>td{font-size:0 !important;line-height:0 !important;height:0 !important}.s-4>tbody>tr>td{font-size:16px !important;line-height:16px !important;height:16px !important}.s-6>tbody>tr>td{font-size:24px !important;line-height:24px !important;height:24px !important}.s-10>tbody>tr>td{font-size:40px !important;line-height:40px !important;height:40px !important}}
    </style>
</head>
<body class="bg-light" style="outline: 0; width: 100%; min-width: 100%; height: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; font-family: Helvetica, Arial, sans-serif; line-height: 24px; font-weight: normal; font-size: 16px; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; color: #000000; margin: 0; padding: 0; border-width: 0;" bgcolor="#f7fafc">
<table class="bg-light body" valign="top" role="presentation" border="0" cellpadding="0" cellspacing="0" style="outline: 0; width: 100%; min-width: 100%; height: 100%; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; font-family: Helvetica, Arial, sans-serif; line-height: 24px; font-weight: normal; font-size: 16px; -moz-box-sizing: border-box; -webkit-box-sizing: border-box; box-sizing: border-box; color: #000000; margin: 0; padding: 0; border-width: 0;" bgcolor="#f7fafc">
    <tbody>
    <tr>
        <td valign="top" style="line-height: 24px; font-size: 16px; margin: 0;" align="left" bgcolor="#f7fafc">
            <table class="container" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;">
                <tbody>
                <tr>
                    <td align="center" style="line-height: 24px; font-size: 16px; margin: 0; padding: 0 16px;">
                        <!--[if (gte mso 9)|(IE)]>
                        <table align="center" role="presentation">
                            <tbody>
                            <tr>
                                <td width="600">
                        <![endif]-->
                        <table align="center" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 600px; margin: 0 auto;">
                            <tbody>
                            <tr>
                                <td style="line-height: 24px; font-size: 16px; margin: 0;" align="left">
                                    <table class="s-10 w-full" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
                                        <tbody>
                                        <tr>
                                            <td style="line-height: 40px; font-size: 40px; width: 100%; height: 40px; margin: 0;" align="left" width="100%" height="40">
                                                &#160;
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="ax-center" role="presentation" align="center" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                        <tbody>
                                        <tr>
                                            <td style="line-height: 24px; font-size: 16px; margin: 0;" align="left">
                                                <!-- logo image here -->
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="s-10 w-full" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
                                        <tbody>
                                        <tr>
                                            <td style="line-height: 40px; font-size: 40px; width: 100%; height: 40px; margin: 0;" align="left" width="100%" height="40">
                                                &#160;
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="card p-6 p-lg-10 space-y-4" role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important; width: 100%; overflow: hidden; border: 1px solid #e2e8f0;" bgcolor="#ffffff">
                                        <tbody>
                                        <tr>
                                            <td style="line-height: 24px; font-size: 16px; width: 100%; margin: 0; padding: 40px;" align="left" bgcolor="#ffffff">
                                                <h1 class="h3 fw-700" style="padding-top: 0; padding-bottom: 0; font-weight: 700 !important; vertical-align: baseline; font-size: 28px; line-height: 33.6px; margin: 0;" align="left">
                                                    Pixel Tracks
                                                </h1>
                                                <table class="s-4 w-full" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
                                                    <tbody>
                                                    <tr>
                                                        <td style="line-height: 16px; font-size: 16px; width: 100%; height: 16px; margin: 0;" align="left" width="100%" height="16">
                                                            &#160;
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                <p class="" style="line-height: 24px; font-size: 16px; width: 100%; margin: 0;" align="left">
                                                    {{ 'mail.login_code_body'|trans }}
                                                </p>
                                                <table class="s-4 w-full" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
                                                    <tbody>
                                                    <tr>
                                                        <td style="line-height: 16px; font-size: 16px; width: 100%; height: 16px; margin: 0;" align="left" width="100%" height="16">
                                                            &#160;
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                                <table class="p-3" role="presentation" border="0" cellpadding="0" cellspacing="0" style="border-radius: 6px; border-collapse: separate !important; width: 100%; background-color: #f7fafc; border: 1px solid #e2e8f0;">
                                                    <tbody>
                                                    <tr>
                                                        <td style="line-height: 24px; font-size: 36px; font-weight: 700; letter-spacing: 8px; border-radius: 6px; margin: 0; padding: 16px;" align="center">
                                                            {{ code }}
                                                        </td>
                                                    </tr>
                                                    </tbody>
                                                </table>
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="s-10 w-full" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
                                        <tbody>
                                        <tr>
                                            <td style="line-height: 40px; font-size: 40px; width: 100%; height: 40px; margin: 0;" align="left" width="100%" height="40">
                                                &#160;
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="ax-center" role="presentation" align="center" border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto;">
                                        <tbody>
                                        <tr>
                                            <td style="line-height: 24px; font-size: 16px; margin: 0;" align="left">
                                                <!-- footer image here -->
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <table class="s-6 w-full" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
                                        <tbody>
                                        <tr>
                                            <td style="line-height: 24px; font-size: 24px; width: 100%; height: 24px; margin: 0;" align="left" width="100%" height="24">
                                                &#160;
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                    <div class="text-muted text-center" style="color: #718096;" align="center">
                                        {{ 'mail.login_code_footer_prefix'|trans }} Pixel Tracks<br>
                                    </div>
                                    <table class="s-6 w-full" role="presentation" border="0" cellpadding="0" cellspacing="0" style="width: 100%;" width="100%">
                                        <tbody>
                                        <tr>
                                            <td style="line-height: 24px; font-size: 24px; width: 100%; height: 24px; margin: 0;" align="left" width="100%" height="24">
                                                &#160;
                                            </td>
                                        </tr>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                            </tbody>
                        </table>
                        <!--[if (gte mso 9)|(IE)]>
                        </td>
                        </tr>
                        </tbody>
                        </table>
                        <![endif]-->
                    </td>
                </tr>
                </tbody>
            </table>
        </td>
    </tr>
    </tbody>
</table>
</body>
</html>
```

- [ ] **Step 10: Update `translations/messages.en.yaml`**

Remove the `magic_link:` block entirely and replace it with:

```yaml
login:
    title: Log in
    intro: Enter your email and we'll send you a login code
    email_invalid_feedback: Please enter a valid email address.
    send: Send code
    verify_title: Enter your code
    verify_intro: 'We sent a 6-digit code to %email%'
    code_invalid_feedback: Please enter the 6-digit code.
    verify: Verify
    start_over: Use a different email
```

In the `mail:` block, replace all four `magic_link_*` keys:

```yaml
mail:
    login_code_subject: Your login code
    login_code_body: 'Here is your login code:'
    login_code_footer_prefix: 'Sent with <3 from'
```

In the `flash:` block, remove `invalid_or_expired_link` and `please_verify_mailbox`, and add these new
keys as siblings under the same existing block (`invalid_email` stays exactly as-is):

```yaml
    code_sent: We've sent you a login code. Check your email.
    invalid_code: Incorrect code. Please try again.
    code_expired: Your code has expired. Please request a new one.
    too_many_attempts: Too many incorrect attempts. Please request a new code.
    no_pending_login: Please request a new code to log in.
```

- [ ] **Step 11: Update `translations/messages.pt.yaml`** the same way

Replace `magic_link:` with:

```yaml
login:
    title: Iniciar sessão
    intro: Indique o seu email e enviamos-lhe um código de acesso
    email_invalid_feedback: Por favor indique um endereço de email válido.
    send: Enviar código
    verify_title: Indique o seu código
    verify_intro: 'Enviámos um código de 6 dígitos para %email%'
    code_invalid_feedback: Por favor indique o código de 6 dígitos.
    verify: Verificar
    start_over: Usar um email diferente
```

Replace the `mail:` block's four keys:

```yaml
mail:
    login_code_subject: O seu código de acesso
    login_code_body: 'Aqui está o seu código de acesso:'
    login_code_footer_prefix: 'Enviado com <3 por'
```

In `flash:`, remove `invalid_or_expired_link` and `please_verify_mailbox`, add:

```yaml
    code_sent: Enviámos-lhe um código de acesso. Verifique o seu email.
    invalid_code: Código incorreto. Por favor tente novamente.
    code_expired: O seu código expirou. Por favor peça um novo.
    too_many_attempts: Demasiadas tentativas incorretas. Por favor peça um novo código.
    no_pending_login: Por favor peça um novo código para iniciar sessão.
```

- [ ] **Step 12: Verify configuration and templates are syntactically valid**

```bash
docker compose exec -u www-data app bin/console lint:yaml translations/ config/packages/security.yaml config/packages/framework.yaml config/services.yaml
docker compose exec -u www-data app bin/console lint:twig templates/
docker compose exec -u www-data app bin/console lint:container
docker compose exec -u www-data app bin/console cache:clear
docker compose exec -u www-data app bin/console debug:router | grep -E 'app_login'
```

Expected: all lint commands `[OK]`; `cache:clear` succeeds (this is what would surface a
`custom_authenticators`/DI misconfiguration, e.g. if `LoginCodeAuthenticator` weren't autowireable);
`debug:router` shows `app_login_request.en`/`.pt`, `app_login_send.en`/`.pt`,
`app_login_verify.en`/`.pt`, `app_login_verify_submit.en`/`.pt` — 8 entries, all `/en/login...`/`/pt/entrar...`.

- [ ] **Step 13: Manually verify the full flow end to end**

Run the "Verification helper" recipe from the top of this plan. Expected: lands on `/en/profile/` with the
profile page rendered (not the login form again). Then repeat once with a wrong code (any 6-digit string
that isn't the real one) and confirm the response redirects back to `/en/login/verify` with the
`flash.invalid_code` message, not a 500 or an unrelated redirect.

- [ ] **Step 14: Commit**

```bash
git add config/packages/security.yaml config/packages/framework.yaml config/services.yaml src/Security/LoginEntryPoint.php src/Controller/LoginController.php templates/Default/login-request.html.twig templates/Default/login-verify.html.twig templates/Default/Mail/login-code-html.html.twig templates/Default/Mail/login-code-text.txt.twig translations/
git commit -m "feat(auth): replace magic-link login with a numeric login code"
```

---

### Task 4: Update the test suite for the new flow

The three affected test files currently drive `LoginLinkHandlerInterface` directly to obtain a link, or hit
now-deleted `/en/send-magic-link` routes — none of them can pass until this task lands. `make tests` is
expected to be red between Task 3 and this task; that's an accepted, temporary state within this plan
(the two are reviewed as adjacent commits on the same branch, not shipped independently).

**Files:**
- Delete: `tests/Functional/MagicLinkControllerTest.php`
- Create: `tests/Functional/LoginControllerTest.php`
- Modify (large rewrite): `tests/Functional/AuthenticationFlowTest.php`
- Modify: `tests/Functional/AccessControlTest.php`

**Interfaces:** None new — this task only consumes routes/behavior from Task 3.

- [ ] **Step 1: Delete the old magic-link controller test and create `tests/Functional/LoginControllerTest.php`**

```bash
git rm tests/Functional/MagicLinkControllerTest.php
```

```php
<?php

namespace App\Tests\Functional;

use App\Repository\UserRepository;

class LoginControllerTest extends WebTestCase
{
    public function testRequestFormRendersForAnUnauthenticatedVisitor(): void
    {
        $this->client->request('GET', '/en/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="email"]');
    }

    public function testValidEmailSendsACodeAndRedirectsToTheVerifyForm(): void
    {
        $this->client->request('POST', '/en/login', [
            'email' => 'someone@example.com',
            '_token' => $this->requestCsrfToken(),
        ]);

        self::assertResponseRedirects('/en/login/verify');

        self::assertEmailCount(1);
        $email = self::getMailerMessage(0);
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'someone@example.com');
        self::assertEmailSubjectContains($email, 'login code');

        $userRepository = self::getContainer()->get(UserRepository::class);
        self::assertNotNull($userRepository->findOneByEmail('someone@example.com'));

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'sent');
        self::assertSelectorExists('input[name="code"]');
    }

    public function testInvalidEmailRedirectsWithADangerFlashAndSendsNoEmail(): void
    {
        $this->client->request('POST', '/en/login', [
            'email' => 'not-an-email',
            '_token' => $this->requestCsrfToken(),
        ]);

        self::assertResponseRedirects('/en/login');
        self::assertEmailCount(0);

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid email');
    }

    public function testMissingCsrfTokenIsTreatedAsUnauthenticatedAndRedirectedToTheLoginForm(): void
    {
        // The controller throws AccessDeniedException on an invalid CSRF token. For an
        // unauthenticated client, Symfony's security layer hands that to the firewall's
        // entry point (LoginEntryPoint) rather than rendering a bare 403 - which just
        // redirects back to this same form, so from the outside this looks identical to a
        // normal visit.
        $this->client->request('POST', '/en/login', ['email' => 'someone@example.com']);

        self::assertResponseRedirects('/en/login');
        self::assertEmailCount(0);
    }

    public function testExceedingTheIpRateLimitReturns429(): void
    {
        // A fresh token is fetched per iteration rather than reused: Symfony's test-mode
        // ServiceResetListener clears stateful services (including the mailer message
        // logger this test asserts against) after every single request, so state can't be
        // accumulated across a request loop here regardless - but the session (and so the
        // CSRF token bound to it) persists via the cookie jar independently of that.
        $token = $this->requestCsrfToken();

        // RATE_LIMITER_MAX_CAPACITY defaults to 5 in .env.dist/.env.test.
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('POST', '/en/login', [
                'email' => 'someone@example.com',
                '_token' => $token,
            ]);
            self::assertResponseRedirects('/en/login/verify');
        }

        $this->client->request('POST', '/en/login', [
            'email' => 'someone@example.com',
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(429);
        // The limiter rejects the request before any mail is sent.
        self::assertEmailCount(0);
    }

    public function testVerifyFormRedirectsToTheRequestFormWhenThereIsNoPendingLogin(): void
    {
        $this->client->request('GET', '/en/login/verify');

        self::assertResponseRedirects('/en/login');
    }

    /**
     * Generating a CSRF token requires a session, which only exists within an actual HTTP
     * request - fetching it from the container directly (outside of $this->client->request())
     * fails with "There is currently no session available." So instead this visits the real
     * form and reads the token the same way a browser would.
     */
    private function requestCsrfToken(): string
    {
        $crawler = $this->client->request('GET', '/en/login');

        return (string) $crawler->filter('input[name="_token"]')->attr('value');
    }
}
```

- [ ] **Step 2: Run it to confirm it passes**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit tests/Functional/LoginControllerTest.php
```

Expected: 6 tests, all green.

- [ ] **Step 3: Replace `tests/Functional/AuthenticationFlowTest.php` entirely**

```php
<?php

namespace App\Tests\Functional;

use App\Repository\UserRepository;

class AuthenticationFlowTest extends WebTestCase
{
    public function testEnteringTheCorrectCodeAuthenticatesAndRedirectsToTheProfile(): void
    {
        $code = $this->requestCodeFor('code-fresh@example.com');

        $this->client->request('POST', '/en/login/verify', [
            'code' => $code,
            '_token' => $this->verifyCsrfToken(),
        ]);

        self::assertResponseRedirects('/en/profile/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testAWrongCodeFailsAndRedisplaysTheVerifyFormWithADangerFlash(): void
    {
        $code = $this->requestCodeFor('code-wrong@example.com');

        $this->client->request('POST', '/en/login/verify', [
            'code' => $this->wrongCodeFor($code),
            '_token' => $this->verifyCsrfToken(),
        ]);

        self::assertResponseRedirects('/en/login/verify');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Incorrect code');
    }

    public function testFiveWrongAttemptsInvalidatesTheCodeAndRequiresANewRequest(): void
    {
        $code = $this->requestCodeFor('code-exhausted@example.com');
        $wrongCode = $this->wrongCodeFor($code);

        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('POST', '/en/login/verify', [
                'code' => $wrongCode,
                '_token' => $this->verifyCsrfToken(),
            ]);
            self::assertResponseRedirects('/en/login/verify');
        }

        // The 5th wrong attempt above already exhausted the cap - this submission (even
        // with the real code) must now be treated as "no valid code left".
        $this->client->request('POST', '/en/login/verify', [
            'code' => $code,
            '_token' => $this->verifyCsrfToken(),
        ]);

        self::assertResponseRedirects('/en/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Too many incorrect attempts');
    }

    public function testAnExpiredCodeIsRejected(): void
    {
        $code = $this->requestCodeFor('code-expired@example.com');

        $userRepository = self::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneByEmail('code-expired@example.com');
        self::assertNotNull($user);
        $user->setLoginCodeExpiresAt(new \DateTimeImmutable('-1 second'));
        $this->entityManager->flush();

        $this->client->request('POST', '/en/login/verify', [
            'code' => $code,
            '_token' => $this->verifyCsrfToken(),
        ]);

        self::assertResponseRedirects('/en/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'expired');
    }

    public function testLogoutEndsTheSessionAndProtectedRoutesRedirectAgain(): void
    {
        $user = $this->persistUser('logout@example.com');
        $this->client->loginUser($user);

        $this->client->request('GET', '/en/profile/');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects();

        $this->client->request('GET', '/en/profile/');
        self::assertResponseRedirects('/en/login');
    }

    /**
     * Requests a real login code the same way a browser would (submits the email form),
     * then reads the 6-digit code out of the captured test email - there's no link to
     * extract any more, so this mirrors exactly how a real user reads their code.
     */
    private function requestCodeFor(string $email): string
    {
        $crawler = $this->client->request('GET', '/en/login');
        $token = (string) $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/en/login', [
            'email' => $email,
            '_token' => $token,
        ]);

        $mailerMessage = self::getMailerMessage(0);
        self::assertNotNull($mailerMessage);

        $body = $mailerMessage->getTextBody();
        self::assertIsString($body);
        self::assertMatchesRegularExpression('/\b\d{6}\b/', $body);
        preg_match('/\b(\d{6})\b/', $body, $matches);

        return $matches[1];
    }

    /**
     * Guaranteed different from $correctCode (never relies on a hardcoded guess like
     * '000000', which has a real if tiny chance of colliding with the actual random code
     * and making the test flaky).
     */
    private function wrongCodeFor(string $correctCode): string
    {
        $wrongCodeAsInt = ((int) $correctCode + 1) % 1000000;

        return str_pad((string) $wrongCodeAsInt, 6, '0', STR_PAD_LEFT);
    }

    private function verifyCsrfToken(): string
    {
        $crawler = $this->client->request('GET', '/en/login/verify');

        return (string) $crawler->filter('input[name="_token"]')->attr('value');
    }
}
```

- [ ] **Step 4: Run it to confirm it passes**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit tests/Functional/AuthenticationFlowTest.php
```

Expected: 5 tests, all green.

- [ ] **Step 5: Update `tests/Functional/AccessControlTest.php`**

Change:

```php
    public function testAProtectedRouteRedirectsAnUnauthenticatedVisitorToTheMagicLinkForm(): void
    {
        $this->client->request('GET', '/en/profile/');

        self::assertResponseRedirects('/en/send-magic-link');
    }

    public function testTheMagicLinkRouteIsReachableWithoutAuthentication(): void
    {
        $this->client->request('GET', '/en/send-magic-link');

        self::assertResponseIsSuccessful();
    }

    public function testSecurityHeadersAreSetOnEveryResponse(): void
    {
        $this->client->request('GET', '/en/send-magic-link');
```

to:

```php
    public function testAProtectedRouteRedirectsAnUnauthenticatedVisitorToTheLoginForm(): void
    {
        $this->client->request('GET', '/en/profile/');

        self::assertResponseRedirects('/en/login');
    }

    public function testTheLoginRouteIsReachableWithoutAuthentication(): void
    {
        $this->client->request('GET', '/en/login');

        self::assertResponseIsSuccessful();
    }

    public function testSecurityHeadersAreSetOnEveryResponse(): void
    {
        $this->client->request('GET', '/en/login');
```

- [ ] **Step 6: Run the full suite**

```bash
docker compose exec -u www-data app vendor/bin/simple-phpunit
```

Expected: every test passes — this is the point where `make tests` returns to fully green.

- [ ] **Step 7: Run PHPStan and PHP_CodeSniffer too, since this task touches a lot of surface area**

```bash
docker compose exec -u www-data app vendor/bin/phpstan analyse
docker compose exec -u www-data app vendor/bin/phpcs
```

Expected: both `[OK]`/no violations. If either tool flags something (e.g. an unused `use` import left over
from the deleted `MagicLinkController`/old test), fix it before committing.

- [ ] **Step 8: Commit**

```bash
git add tests/Functional/LoginControllerTest.php tests/Functional/AuthenticationFlowTest.php tests/Functional/AccessControlTest.php
git commit -m "test: rewrite auth suite for the numeric login code flow"
```

---

### Task 5: Documentation

**Files:**
- Modify: `CLAUDE.md`
- Modify: `README.md`

**Interfaces:** None — documentation only.

- [ ] **Step 1: Update `CLAUDE.md`'s Routing section**

Change the `HomeController`/`MagicLinkController` bullets and the intro sentence:

```markdown
- `HomeController` — `/` (unprefixed; for an already-authenticated visitor, redirects to the localized profile page, picking a locale from `Accept-Language`; an unauthenticated visitor never reaches this controller at all — `access_control` intercepts first and `LoginEntryPoint` redirects straight to the login page) and `/en/profile/` / `/pt/perfil/` (+ `/{page}` variants, paginated track list)
- `LoginController` — `/en/login` / `/pt/entrar` (GET: email form, POST: rate-limited code request + email send) and `/en/login/verify` / `/pt/entrar/verificar` (GET: code form, POST: verify + log in)
- `LogoutController` — `/logout` (unprefixed), same pattern (Symfony's logout listener intercepts it)
```

(Remove the old `LoginController — /login/check` bullet entirely — that route no longer exists.)

- [ ] **Step 2: Update `CLAUDE.md`'s Auth section**

Replace the whole paragraph:

```markdown
**Auth**: no passwords — Symfony Security's `login_link` firewall (`config/packages/security.yaml`), keyed on the `User` entity's `email` property. `MagicLinkController::sendMagicLink` rate-limits by IP and by email (via the `magic_link_by_ip`/`magic_link_by_email` limiters configured in `config/packages/framework.yaml`), looks up or creates a `User`, persists the requester's current locale onto it (`User::locale` — the transactional email below is actually localized by the current request's locale, since `/send-magic-link` is itself locale-prefixed; the persisted value is what lets the deliberately-unlocalized `/track/upload`/`/track/delete` routes still show flash messages in the user's own language later), and emails a link built by `LoginLinkHandlerInterface`. Visiting the link hits `app_login_check`, which the firewall's authenticator intercepts before the controller body ever runs. `App\Security\LoginSuccessHandler`/`LoginFailureHandler` redirect post-auth; `App\Security\MagicLinkEntryPoint` redirects unauthenticated access attempts to the localized magic-link page. `access_control` in `security.yaml` grants `PUBLIC_ACCESS` to both locale variants of the magic-link path and to `/login*`, and requires full authentication for everything else; the two public controllers also carry a `#[IsGranted('PUBLIC_ACCESS')]` attribute as defense-in-depth — note that attribute alone would NOT be sufficient on its own, since `access_control`'s `kernel.request`-time check runs before controller attributes are ever evaluated and remains the actually-enforcing mechanism.
```

with:

```markdown
**Auth**: no passwords — a two-step numeric login code, keyed on the `User` entity's `email` property. `LoginController::sendCode` rate-limits by IP and by email (via the `login_code_by_ip`/`login_code_by_email` limiters configured in `config/packages/framework.yaml`), looks up or creates a `User`, persists the requester's current locale onto it (`User::locale` — used for the `/track/upload`/`/track/delete` routes' flash messages later, since those stay deliberately unlocalized), generates a 6-digit code via `random_int()`, hashes it with `password_hash()` into `User::loginCodeHash` (with `loginCodeExpiresAt`/`loginCodeAttempts`), stores the pending user's id in the session (`pending_login_user_id`), and emails the code. `LoginController::verifyCode` checks the submitted code against the hash (`password_verify()`), enforces a 5-minute expiry and a 5-attempt cap (past either, the code is invalidated and a new one must be requested), and on success calls `Security::login($user)` before redirecting to the profile. `Security::login()` requires the firewall to have exactly one authenticator registered, which is what `App\Security\LoginCodeAuthenticator` exists for — it never handles a request directly (its `supports()` always returns `false`); Symfony's `authenticateUser()` path calls only its inherited `createToken()` (from `AbstractAuthenticator`) and its `onAuthenticationSuccess()`. `App\Security\LoginEntryPoint` redirects unauthenticated access attempts to the localized login page. `access_control` in `security.yaml` grants `PUBLIC_ACCESS` to both locale variants of the login and verify paths (end-anchored, to avoid matching unintended longer paths) and requires full authentication for everything else; `#[IsGranted('PUBLIC_ACCESS')]` on the controller actions is defense-in-depth only — `access_control`'s `kernel.request`-time check is what actually enforces this, since it runs before controller attributes are evaluated.
```

- [ ] **Step 3: Update `CLAUDE.md`'s Data layer or Cross-cutting mention if needed**

Search `CLAUDE.md` for any other `magic` mentions:

```bash
grep -n "magic" CLAUDE.md
```

If any remain outside the two sections already fixed above, update them to describe the login-code flow
using the same terms established in Step 2 (`LoginController`, login code, `LoginEntryPoint`).

- [ ] **Step 4: Update `README.md`**

```bash
grep -n "magic" README.md
```

Update each hit:
- "Authentication is handled via email "magic links"." → "Authentication is handled via a numeric login code sent by email."
- "`APP_SECRET` — HMAC key for magic-link signatures; generate your own" → "`APP_SECRET` — Symfony's general application secret (CSRF tokens, signed values); generate your own" (it's no longer specifically tied to link-signing now that `login_link` is gone).
- "This is what stops magic-link URLs from being Host-header spoofed" → "This is what stops generated absolute URLs from being Host-header spoofed" (matches the same wording fix already applied to `framework.yaml`'s comment in Task 3).
- "`LOGIN_TOLERANCE_TIME` (magic-link lifetime, in seconds)" → "`LOGIN_TOLERANCE_TIME` (login code lifetime, in seconds)"
- "Mailpit UI (for catching magic-link emails in dev):" → "Mailpit UI (for catching login-code emails in dev):"

- [ ] **Step 5: Verify no stale references remain anywhere**

```bash
grep -rln "magic.link\|magic_link\|MagicLink\|link.magico\|link-magico" --include="*.php" --include="*.twig" --include="*.yaml" --include="*.yml" --include="*.md" . 2>/dev/null | grep -v vendor | grep -v docs/superpowers
```

Expected: no output. If anything remains, it's a leftover this plan missed — investigate and fix it before
committing.

- [ ] **Step 6: Commit**

```bash
git add CLAUDE.md README.md
git commit -m "docs: describe the numeric login code flow"
```

## Self-review notes

- **Spec coverage:** the authenticator design (verified against Symfony source), the entity columns, the
  full controller flow (request/send/verify with expiry+attempt-cap+session), routing/`access_control`
  (with the anchoring fix), templates, mail templates, translations, rate-limiter renames, and doc updates
  are all covered by a task. The spec's out-of-scope items (2FA, remember-me, cross-device entry, non-email
  delivery) are never touched by any task.
- **Placeholder scan:** no TBD/TODO; every translation key has both an English and Portuguese value decided
  in this plan; every code block is complete, runnable content.
- **Type/name consistency:** `User`'s eight new/changed method names (Task 1) are used identically in
  `LoginController` (Task 3) and the tests (Task 4) — cross-checked while writing. Route names
  (`app_login_request`/`app_login_send`/`app_login_verify`/`app_login_verify_submit`) are identical across
  Task 3's controller, `security.yaml`'s `access_control`, and Task 4's tests. Translation keys used in
  Task 3's templates/controller match exactly what Task 3's own translation-file steps define — no key is
  referenced before it's defined or spelled differently in two places.
- **Test-flakiness check:** `AuthenticationFlowTest`'s wrong-code tests deliberately derive the wrong code
  from the real one (`wrongCodeFor()`) rather than hardcoding a guess like `'000000'`, which would have a
  1-in-1,000,000 chance of colliding with the real code and making the test flaky.
- **Known transient state:** `make tests` is red between Task 3's commit and Task 4's commit (Task 3 deletes
  routes/classes Task 4's still-unmodified test files reference) — this is called out explicitly in Task 4's
  intro rather than left implicit, so whoever executes this plan doesn't mistake it for a regression.
