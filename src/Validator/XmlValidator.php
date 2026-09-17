<?php

namespace App\Validator;

class XmlValidator
{
    public function isValid(string $xmlDocument, string $schema): bool
    {
        libxml_use_internal_errors(true);

        $xml = new \DOMDocument();
        $xml->loadXML($xmlDocument);

        $isValid = $xml->schemaValidateSource($schema);
        libxml_clear_errors();

        return $isValid;
    }
}
