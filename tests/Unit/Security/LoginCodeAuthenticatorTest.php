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
