<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\HomeController;
use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Repository\TrackRepository;
use PixelTrack\Repository\UserRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use PixelTrack\Validator\XmlValidator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HomeControllerTest extends TestCase
{
    public function testRedirectToMagicLinkWhenUserNotFound()
    {
        $configMock = $this->createMock(Config::class);
        $userRepositoryMock = $this->createMock(UserRepository::class);
        $trackRepositoryMock = $this->createMock(TrackRepository::class);
        $twigMock = $this->createMock(Twig::class);

        $userRepositoryMock->expects(self::once())
            ->method('userExists')
            ->with('test-user-key')
            ->willReturn(false);

        $trackRepositoryMock->expects(self::never())
            ->method('getTracksFromUser');

        $homeController = new HomeController(
            $configMock,
            $userRepositoryMock,
            $trackRepositoryMock,
            $twigMock
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $homeController->index('test-user-key')
        );
    }
}
