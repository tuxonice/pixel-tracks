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
