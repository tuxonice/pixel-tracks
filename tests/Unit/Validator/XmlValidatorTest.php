<?php

namespace Unit\Validator;

use PHPUnit\Framework\TestCase;
use PixelTrack\Validator\XmlValidator;

class XmlValidatorTest extends TestCase
{
    public function testValidDocument(): void
    {
        $gpxFile = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/sample.gpx');
        $gpxSchema = file_get_contents(dirname(__DIR__, 3) . '/src/Schemas/gpx.xsd');
        $xmlValidator = new XmlValidator();

        self::assertTrue($xmlValidator->isValid($gpxFile, $gpxSchema));
    }

    public function testInvalidDocument(): void
    {
        $gpxFile = file_get_contents(dirname(__DIR__, 2) . '/Fixtures/invalid-sample.gpx');
        $gpxSchema = file_get_contents(dirname(__DIR__, 3) . '/src/Schemas/gpx.xsd');
        $xmlValidator = new XmlValidator();

        self::assertFalse($xmlValidator->isValid($gpxFile, $gpxSchema));
    }
}
