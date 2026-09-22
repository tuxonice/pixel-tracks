<?php

namespace App\Tests\Functional;

use App\Repository\UserRepository;

class MagicLinkControllerTest extends WebTestCase
{
    public function testRequestFormRendersForAnUnauthenticatedVisitor(): void
    {
        $this->client->request('GET', '/en/send-magic-link');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="email"]');
    }

    public function testValidEmailSendsALoginLinkAndRedirectsWithASuccessFlash(): void
    {
        $this->client->request('POST', '/en/send-magic-link', [
            'email' => 'someone@example.com',
            '_token' => $this->csrfToken(),
        ]);

        self::assertResponseRedirects('/en/send-magic-link');

        self::assertEmailCount(1);
        $email = self::getMailerMessage(0);
        self::assertNotNull($email);
        self::assertEmailAddressContains($email, 'To', 'someone@example.com');
        self::assertEmailSubjectContains($email, 'magic link');

        $userRepository = self::getContainer()->get(UserRepository::class);
        self::assertNotNull($userRepository->findOneByEmail('someone@example.com'));

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'verify your mailbox');
    }

    public function testInvalidEmailRedirectsWithADangerFlashAndSendsNoEmail(): void
    {
        $this->client->request('POST', '/en/send-magic-link', [
            'email' => 'not-an-email',
            '_token' => $this->csrfToken(),
        ]);

        self::assertResponseRedirects('/en/send-magic-link');
        self::assertEmailCount(0);

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid email');
    }

    public function testMissingCsrfTokenIsTreatedAsUnauthenticatedAndRedirectedToTheMagicLinkForm(): void
    {
        // The controller throws AccessDeniedException on an invalid CSRF token. For an
        // unauthenticated client, Symfony's security layer hands that to the firewall's
        // entry point (MagicLinkEntryPoint) rather than rendering a bare 403 - which just
        // redirects back to this same form, so from the outside this looks identical to a
        // normal visit.
        $this->client->request('POST', '/en/send-magic-link', ['email' => 'someone@example.com']);

        self::assertResponseRedirects('/en/send-magic-link');
        self::assertEmailCount(0);
    }

    public function testExceedingTheIpRateLimitReturns429(): void
    {
        // A fresh token is fetched per iteration rather than reused: Symfony's test-mode
        // ServiceResetListener clears stateful services (including the mailer message
        // logger this test asserts against) after every single request, so state can't be
        // accumulated across a request loop here regardless - but the session (and so the
        // CSRF token bound to it) persists via the cookie jar independently of that.
        $token = $this->csrfToken();

        // RATE_LIMITER_MAX_CAPACITY defaults to 5 in .env.dist/.env.test.
        for ($i = 0; $i < 5; ++$i) {
            $this->client->request('POST', '/en/send-magic-link', [
                'email' => 'someone@example.com',
                '_token' => $token,
            ]);
            self::assertResponseRedirects('/en/send-magic-link');
        }

        $this->client->request('POST', '/en/send-magic-link', [
            'email' => 'someone@example.com',
            '_token' => $token,
        ]);

        self::assertResponseStatusCodeSame(429);
        // The limiter rejects the request before any mail is sent.
        self::assertEmailCount(0);
    }

    /**
     * Generating a CSRF token requires a session, which only exists within an actual HTTP
     * request - fetching it from the container directly (outside of $this->client->request())
     * fails with "There is currently no session available." So instead this visits the real
     * form and reads the token the same way a browser would.
     */
    private function csrfToken(): string
    {
        $crawler = $this->client->request('GET', '/en/send-magic-link');

        return (string) $crawler->filter('input[name="_token"]')->attr('value');
    }
}
