<?php

namespace App\Tests\Unit\Service;

use App\Exception\GpxValidationException;
use App\Service\GpxValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GpxValidatorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../../Fixtures/gpx/';

    public function testValidGpxFilePassesAllChecks(): void
    {
        $file = new UploadedFile(self::FIXTURES . 'valid-track.gpx', 'valid-track.gpx', null, null, true);

        (new GpxValidator())->validate($file);

        $this->expectNotToPerformAssertions();
    }

    public function testFileExceedingTheSizeLimitIsRejected(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(10485760 + 1);

        $this->expectException(GpxValidationException::class);
        $this->expectExceptionMessage('File size exceeds maximum allowed size of 10MB');

        (new GpxValidator())->validate($file);
    }

    public function testFileWithDisallowedMimeTypeIsRejected(): void
    {
        $file = new UploadedFile(self::FIXTURES . 'not-xml.txt', 'not-xml.txt', null, null, true);

        $this->expectException(GpxValidationException::class);
        $this->expectExceptionMessage('Invalid file type. Only GPX files are allowed.');

        (new GpxValidator())->validate($file);
    }

    public function testFileWithoutTheGpxNamespaceIsRejected(): void
    {
        $file = new UploadedFile(self::FIXTURES . 'no-namespace.gpx', 'no-namespace.gpx', null, null, true);

        $this->expectException(GpxValidationException::class);
        $this->expectExceptionMessage('Invalid GPX namespace');

        (new GpxValidator())->validate($file);
    }

    public function testFileWithoutTrackPointsIsRejected(): void
    {
        $file = new UploadedFile(self::FIXTURES . 'no-track-points.gpx', 'no-track-points.gpx', null, null, true);

        $this->expectException(GpxValidationException::class);
        $this->expectExceptionMessage('No valid track data found in GPX file');

        (new GpxValidator())->validate($file);
    }

    public function testMalformedXmlIsRejected(): void
    {
        $file = new UploadedFile(self::FIXTURES . 'malformed.xml', 'malformed.xml', null, null, true);

        $this->expectException(GpxValidationException::class);
        $this->expectExceptionMessageMatches('/Invalid XML structure/');

        (new GpxValidator())->validate($file);
    }
}
