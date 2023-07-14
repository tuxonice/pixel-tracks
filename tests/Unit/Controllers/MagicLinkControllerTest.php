<?php

namespace Unit\Controllers;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use PixelTrack\Cache\Cache;
use PixelTrack\Controllers\MagicLinkController;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Mail;
use PixelTrack\Service\Twig;
use PixelTrack\Service\Utility;
use Symfony\Component\HttpFoundation\Response;
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
        $mailMock = $this->createMock(Mail::class);
        $configMock = $this->createMock(Config::class);
        $twigMock = $this->createMock(Twig::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $cacheMock = $this->createMock(Cache::class);
        $utilityMock = $this->createMock(Utility::class);
        $sessionMock = $this->createMock(Session::class);

        $sessionMock->expects(self::once())
            ->method('set')
            ->with('_csrf', 'test-csrf-token');

        $utilityMock->expects(self::once())
            ->method('generateCsrfToken')
            ->willReturn('test-csrf-token');

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

        $magicLinkController = new MagicLinkController(
            $mailMock,
            $configMock,
            $twigMock,
            $userRepositoryMock,
            $cacheMock,
            $utilityMock
        );

        $this->assertEquals(
            new Response('', 200),
            $magicLinkController->requestMagicLink($sessionMock)
        );
    }
}
