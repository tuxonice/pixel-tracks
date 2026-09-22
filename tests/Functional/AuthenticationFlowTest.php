<?php

namespace App\Tests\Functional;

use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class AuthenticationFlowTest extends WebTestCase
{
    public function testVisitingAValidLoginLinkAuthenticatesAndRedirectsToTheProfile(): void
    {
        $user = $this->persistUser('link-fresh@example.com');
        $loginLink = $this->loginLinkHandler()->createLoginLink($user);

        $this->client->request('GET', $loginLink->getUrl());

        self::assertResponseRedirects('/en/profile/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testATamperedLoginLinkFailsAuthenticationAndRedirectsBackWithADangerFlash(): void
    {
        $user = $this->persistUser('link-tampered@example.com');
        $loginLink = $this->loginLinkHandler()->createLoginLink($user);

        $this->client->request('GET', $loginLink->getUrl() . 'tampered');

        self::assertResponseRedirects('/en/send-magic-link');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'Invalid or expired magic link');
    }

    public function testALoginLinkCannotBeUsedTwice(): void
    {
        $user = $this->persistUser('link-reuse@example.com');
        $loginLink = $this->loginLinkHandler()->createLoginLink($user);

        $this->client->request('GET', $loginLink->getUrl());
        self::assertResponseRedirects('/en/profile/');

        $this->client->request('GET', $loginLink->getUrl());
        self::assertResponseRedirects('/en/send-magic-link');
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
        self::assertResponseRedirects('/en/send-magic-link');
    }

    /**
     * LoginLinkHandlerInterface resolves to a firewall-aware handler that needs an active
     * request to know which firewall it's generating a link for - fine inside a controller,
     * but there's no active request here, so the concrete per-firewall service is used
     * directly instead (as the LogicException from the firewall-aware one suggests).
     */
    private function loginLinkHandler(): LoginLinkHandlerInterface
    {
        return self::getContainer()->get('security.authenticator.login_link_handler.main');
    }
}
