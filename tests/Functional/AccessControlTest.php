<?php

namespace App\Tests\Functional;

class AccessControlTest extends WebTestCase
{
    public function testAProtectedRouteRedirectsAnUnauthenticatedVisitorToTheMagicLinkForm(): void
    {
        $this->client->request('GET', '/profile/');

        self::assertResponseRedirects('/send-magic-link');
    }

    public function testTheMagicLinkRouteIsReachableWithoutAuthentication(): void
    {
        $this->client->request('GET', '/send-magic-link');

        self::assertResponseIsSuccessful();
    }

    public function testSecurityHeadersAreSetOnEveryResponse(): void
    {
        $this->client->request('GET', '/send-magic-link');

        $response = $this->client->getResponse();
        self::assertTrue($response->headers->has('Content-Security-Policy'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        self::assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
    }
}
