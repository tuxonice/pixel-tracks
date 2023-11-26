<?php

namespace Unit\Controllers;

use DG\BypassFinals;
use PHPUnit\Framework\TestCase;
use PixelTrack\Controllers\NotFound;
use PixelTrack\Service\Twig;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\TemplateWrapper;

class NotFoundTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        BypassFinals::enable();
    }

    public function testRequestNotFound(): void
    {
        $twigMock = $this->createMock(Twig::class);

        /** @phpstan-ignore-next-line */
        $templateWrapperMock = $this->createMock(TemplateWrapper::class);
        $templateWrapperMock->expects(self::once())
            ->method('render')
            ->with([])
            ->willReturn('');

        $environmentMock = $this->createMock(Environment::class);
        $environmentMock->expects(self::once())
            ->method('load')
            ->with('Default/not-found.twig')
            ->willReturn($templateWrapperMock);

        $twigMock->expects(self::once())
            ->method('getTwig')
            ->willReturn($environmentMock);

        $notFound = new NotFound($twigMock);
        $expectedResponse = new Response(
            '',
            404,
        );

        $this->assertEquals(
            $expectedResponse,
            $notFound->index()
        );
    }
}
