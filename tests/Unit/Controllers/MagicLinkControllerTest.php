<?php

namespace Unit\Controllers;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\MagicLinkController;
use PixelTrack\DataTransfers\DataTransferObjects\MailMessageTransfer;
use PixelTrack\DataTransfers\DataTransferObjects\MailRecipientTransfer;
use PixelTrack\DataTransfers\DataTransferObjects\UserTransfer;
use PixelTrack\Mail\MailProviderInterface;
use PixelTrack\RateLimiter\RateLimiter;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig\Environment;
use Twig\TemplateWrapper;

class MagicLinkControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BypassFinals::enable();
    }

    public function testRequestMagicLink(): void
    {
        $mailProviderMock = $this->createMock(MailProviderInterface::class);
        $configMock = $this->createMock(Config::class);
        $twigMock = $this->createMock(Twig::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $rateLimiterMock = $this->createMock(RateLimiter::class);

        $sessionMock->expects(self::once())
            ->method('set')
            ->with('_csrf', 'test-csrf-token');

        $utilityMock->expects(self::once())
            ->method('generateCsrfToken')
            ->willReturn('test-csrf-token');

        /** @phpstan-ignore-next-line */
        $templateWrapperMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperMock->expects(self::once())
            ->method('render')
            ->with([
                'flashes' => [],
                'csrf' => 'test-csrf-token',
            ]);

        $environmentMock = $this->createMock(Environment::class);
        $environmentMock->expects(self::once())
            ->method('load')
            ->with('Default/magic-link.twig')
            ->willReturn($templateWrapperMock);

        $twigMock->expects(self::once())
            ->method('getTwig')
            ->willReturn($environmentMock);

        $mailProviderMock->expects(self::never())
            ->method('send');

        $magicLinkController = new MagicLinkController(
            $configMock,
            $twigMock,
            $userRepositoryMock,
            $rateLimiterMock,
            $utilityMock,
            $mailProviderMock,
        );

        $this->assertEquals(
            new Response('', 200),
            $magicLinkController->requestMagicLink($sessionMock)
        );
    }

    public function testRequestMagicLinkWithInvalidCsrfToken(): void
    {
        $mailProviderMock = $this->createMock(MailProviderInterface::class);
        $configMock = $this->createMock(Config::class);
        $twigMock = $this->createMock(Twig::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $rateLimiterMock = $this->createMock(RateLimiter::class);

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->request->set('_csrf', 'csrf-token');

        $rateLimiterMock->expects(self::once())
            ->method('check')
            ->with('10.0.0.1')
            ->willReturn(true);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('csrf-form-token');

        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Invalid token');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $mailProviderMock->expects(self::never())
            ->method('send');

        $magicLinkController = new MagicLinkController(
            $configMock,
            $twigMock,
            $userRepositoryMock,
            $rateLimiterMock,
            $utilityMock,
            $mailProviderMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $magicLinkController->sendMagicLink($request, $sessionMock)
        );
    }

    public function testRequestMagicLinkWithNoCsrfToken(): void
    {
        $mailProviderMock = $this->createMock(MailProviderInterface::class);
        $configMock = $this->createMock(Config::class);
        $twigMock = $this->createMock(Twig::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $rateLimiterMock = $this->createMock(RateLimiter::class);

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '10.0.0.1');

        $rateLimiterMock->expects(self::once())
            ->method('check')
            ->with('10.0.0.1')
            ->willReturn(true);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('csrf-form-token');

        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Invalid token');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $mailProviderMock->expects(self::never())
            ->method('send');

        $magicLinkController = new MagicLinkController(
            $configMock,
            $twigMock,
            $userRepositoryMock,
            $rateLimiterMock,
            $utilityMock,
            $mailProviderMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $magicLinkController->sendMagicLink($request, $sessionMock)
        );
    }

    public function testRequestMagicLinkWithExpiredCsrfToken(): void
    {
        $mailProviderMock = $this->createMock(MailProviderInterface::class);
        $configMock = $this->createMock(Config::class);
        $twigMock = $this->createMock(Twig::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $rateLimiterMock = $this->createMock(RateLimiter::class);

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->request->set('_csrf', 'csrf-token');

        $rateLimiterMock->expects(self::once())
            ->method('check')
            ->with('10.0.0.1')
            ->willReturn(true);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn(null);

        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Invalid token');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $mailProviderMock->expects(self::never())
            ->method('send');

        $magicLinkController = new MagicLinkController(
            $configMock,
            $twigMock,
            $userRepositoryMock,
            $rateLimiterMock,
            $utilityMock,
            $mailProviderMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $magicLinkController->sendMagicLink($request, $sessionMock)
        );
    }

    public function testRequestMagicLinkWithInvalidEmail(): void
    {
        $mailProviderMock = $this->createMock(MailProviderInterface::class);
        $configMock = $this->createMock(Config::class);
        $twigMock = $this->createMock(Twig::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $rateLimiterMock = $this->createMock(RateLimiter::class);

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->request->set('_csrf', 'csrf-token');
        $request->request->set('email', 'user-invalid-email');

        $rateLimiterMock->expects(self::once())
            ->method('check')
            ->with('10.0.0.1')
            ->willReturn(true);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('csrf-token');

        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('danger', 'Invalid email');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        $mailProviderMock->expects(self::never())
            ->method('send');

        $magicLinkController = new MagicLinkController(
            $configMock,
            $twigMock,
            $userRepositoryMock,
            $rateLimiterMock,
            $utilityMock,
            $mailProviderMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $magicLinkController->sendMagicLink($request, $sessionMock)
        );
    }

    public function testRequestMagicLinkIsSentAndCreateNewUser(): void
    {
        $mailProviderMock = $this->createMock(MailProviderInterface::class);
        $configMock = $this->createMock(Config::class);
        $twigMock = $this->createMock(Twig::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $rateLimiterMock = $this->createMock(RateLimiter::class);

        $_ENV['EMAIL_FROM'] = 'noreply@example.com';

        $configMock->expects(self::exactly(2))
            ->method('getBaseUrl')
            ->willReturn('http://example.com/');

        $userRepositoryMock->expects(self::once())
            ->method('findUserByEmail')
            ->with('user@example.com')
            ->willReturn(null);

        $userRepositoryMock->expects(self::once())
            ->method('createUserByEmail')
            ->with('user@example.com');

        $userRepositoryMock->expects(self::once())
            ->method('regenerateLoginKey')
            ->with('user@example.com')
            ->willReturn('user-login-key');

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->request->set('_csrf', 'csrf-token');
        $request->request->set('email', 'user@example.com');

        $rateLimiterMock->expects(self::once())
            ->method('check')
            ->with('10.0.0.1')
            ->willReturn(true);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('csrf-token');

        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('success', 'Please verify your mailbox');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        /** @phpstan-ignore-next-line */
        $templateWrapperHtmlMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperHtmlMock->expects(self::once())
            ->method('render')
            ->with([
                'link' => 'http://example.com/login/user-login-key',
            ])
            ->willReturn('html-link');

        /** @phpstan-ignore-next-line */
        $templateWrapperTextMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperTextMock->expects(self::once())
            ->method('render')
            ->with([
                'link' => 'http://example.com/login/user-login-key',
            ])
        ->willReturn('text-link');

        $environmentMock = $this->createMock(Environment::class);
        $environmentMock->expects(self::exactly(2))
            ->method('load')
            ->willReturnMap(
                [
                    ['Default/Mail/magic-link-html.twig', $templateWrapperHtmlMock],
                    ['Default/Mail/magic-link-text.twig', $templateWrapperTextMock],
                ]
            );

        $twigMock->expects(self::exactly(2))
            ->method('getTwig')
            ->willReturn($environmentMock);

        $mailProviderMock->expects(self::once())
            ->method('send')
            ->with(
                (new MailMessageTransfer())
                ->setFrom(
                    (new MailRecipientTransfer())
                    ->setEmail('noreply@example.com')
                    ->setName('Pixel Tracks')
                )
                ->setTo(
                    (new MailRecipientTransfer())
                        ->setEmail('user@example.com')
                        ->setName('user@example.com')
                )
                ->setSubject('Here is your magic link')
                ->setHtmlBody('html-link')
                ->setTextBody('text-link')
            )
            ->willReturn(true);

        $magicLinkController = new MagicLinkController(
            $configMock,
            $twigMock,
            $userRepositoryMock,
            $rateLimiterMock,
            $utilityMock,
            $mailProviderMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $magicLinkController->sendMagicLink($request, $sessionMock)
        );
    }

    public function testRequestMagicLinkIsSentWithExistingUser(): void
    {
        $mailProviderMock = $this->createMock(MailProviderInterface::class);
        $configMock = $this->createMock(Config::class);
        $twigMock = $this->createMock(Twig::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);
        $rateLimiterMock = $this->createMock(RateLimiter::class);

        $_ENV['EMAIL_FROM'] = 'noreply@example.com';

        $configMock->expects(self::exactly(2))
            ->method('getBaseUrl')
            ->willReturn('http://example.com/');

        $userRepositoryMock->expects(self::once())
            ->method('findUserByEmail')
            ->with('user@example.com')
            ->willReturn(new UserTransfer());

        $userRepositoryMock->expects(self::never())
            ->method('createUserByEmail');

        $userRepositoryMock->expects(self::once())
            ->method('regenerateLoginKey')
            ->with('user@example.com')
            ->willReturn('user-login-key');

        $request = new Request();
        $request->server->set('REMOTE_ADDR', '10.0.0.1');
        $request->request->set('_csrf', 'csrf-token');
        $request->request->set('email', 'user@example.com');

        $rateLimiterMock->expects(self::once())
            ->method('check')
            ->with('10.0.0.1')
            ->willReturn(true);

        $sessionMock->expects(self::once())
            ->method('get')
            ->with('_csrf')
            ->willReturn('csrf-token');

        $flashBagMock = $this->createMock(FlashBagInterface::class);
        $flashBagMock->expects(self::once())
            ->method('add')
            ->with('success', 'Please verify your mailbox');

        $sessionMock->expects(self::once())
            ->method('getFlashBag')
            ->willReturn($flashBagMock);

        /** @phpstan-ignore-next-line */
        $templateWrapperHtmlMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperHtmlMock->expects(self::once())
            ->method('render')
            ->with([
                'link' => 'http://example.com/login/user-login-key',
            ])
            ->willReturn('html-link');

        /** @phpstan-ignore-next-line */
        $templateWrapperTextMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperTextMock->expects(self::once())
            ->method('render')
            ->with([
                'link' => 'http://example.com/login/user-login-key',
            ])
            ->willReturn('text-link');

        $environmentMock = $this->createMock(Environment::class);
        $environmentMock->expects(self::exactly(2))
            ->method('load')
            ->willReturnMap(
                [
                    ['Default/Mail/magic-link-html.twig', $templateWrapperHtmlMock],
                    ['Default/Mail/magic-link-text.twig', $templateWrapperTextMock],
                ]
            );

        $twigMock->expects(self::exactly(2))
            ->method('getTwig')
            ->willReturn($environmentMock);

        $mailProviderMock->expects(self::once())
            ->method('send')
            ->with(
                (new MailMessageTransfer())
                    ->setFrom(
                        (new MailRecipientTransfer())
                            ->setEmail('noreply@example.com')
                            ->setName('Pixel Tracks')
                    )
                    ->setTo(
                        (new MailRecipientTransfer())
                            ->setEmail('user@example.com')
                            ->setName('user@example.com')
                    )
                    ->setSubject('Here is your magic link')
                    ->setHtmlBody('html-link')
                    ->setTextBody('text-link')
            )
            ->willReturn(true);

        $magicLinkController = new MagicLinkController(
            $configMock,
            $twigMock,
            $userRepositoryMock,
            $rateLimiterMock,
            $utilityMock,
            $mailProviderMock,
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $magicLinkController->sendMagicLink($request, $sessionMock)
        );
    }
}
