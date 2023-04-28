<?php

namespace Unit\Controllers;

use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\HomeController;
use PixelTrack\Repository\DatabaseRepository;
use PixelTrack\Service\Config;
use PixelTrack\Service\Twig;
use PixelTrack\Validator\XmlValidator;
use Symfony\Component\HttpFoundation\RedirectResponse;

class HomeControllerTest extends TestCase
{
    public function testRedirectToMagicLinkWhenUserNotFound()
    {
        $xmlValidatorMock = $this->createMock(XmlValidator::class);
        $configMock = $this->createMock(Config::class);
        $databaseRepositoryMock = $this->createMock(DatabaseRepository::class);
        $twigMock = $this->createMock(Twig::class);

        $databaseRepositoryMock->expects(self::once())
            ->method('userExists')
            ->with('test-user-key')
            ->willReturn(false);

        $homeController = new HomeController(
            $xmlValidatorMock,
            $configMock,
            $databaseRepositoryMock,
            $twigMock
        );

        $this->assertEquals(
            new RedirectResponse('/send-magic-link'),
            $homeController->index('test-user-key')
        );
    }
}
