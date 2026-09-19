<?php

namespace App\Tests\Unit\Validator;

use App\Validator\XmlValidator;
use PHPUnit\Framework\TestCase;

class XmlValidatorTest extends TestCase
{
    private const SCHEMA_PATH = __DIR__ . '/../../../src/Schemas/gpx.xsd';

    /**
     * Mirrors how UploadController actually calls XmlValidator::isValid(): the second
     * argument is schema markup, not a schema file path (DOMDocument::schemaValidateSource()
     * expects the former).
     */
    private function schema(): string
    {
        return (string) file_get_contents(self::SCHEMA_PATH);
    }

    public function testValidGpxDocumentPassesSchemaValidation(): void
    {
        $validator = new XmlValidator();
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/gpx/valid-track.gpx');

        self::assertTrue($validator->isValid($xml, $this->schema()));
    }

    public function testDocumentMissingARequiredAttributeFailsSchemaValidation(): void
    {
        $validator = new XmlValidator();
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/gpx/missing-creator.gpx');

        self::assertFalse($validator->isValid($xml, $this->schema()));
    }

    public function testMalformedXmlFailsSchemaValidation(): void
    {
        $validator = new XmlValidator();
        $xml = (string) file_get_contents(__DIR__ . '/../../Fixtures/gpx/malformed.xml');

        self::assertFalse($validator->isValid($xml, $this->schema()));
    }
}
