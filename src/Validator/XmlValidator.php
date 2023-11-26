<?php

namespace PixelTrack\Validator;

use DOMDocument;
use LibXMLError;

class XmlValidator
{
    /**
     * @var array<mixed>
     */
    private array $errors = [];

    public function isValid(string $xmlDocument, string $schema): bool
    {
        $this->errors = [];
        libxml_use_internal_errors(true);

        $xml = new DOMDocument();
        $xml->loadXML($xmlDocument);

        $isValid = $xml->schemaValidateSource($schema);

        if (!$isValid) {
            $this->libxmlDisplayErrors();
        }

        return $isValid;
    }

    /**
     * @return array<mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function libxmlDisplayError(LibXMLError $error): string
    {
        $errorLine = '';
        switch ($error->level) {
            case LIBXML_ERR_WARNING:
                $errorLine .= "Warning $error->code | ";
                break;
            case LIBXML_ERR_ERROR:
                $errorLine .= "Error $error->code | ";
                break;
            case LIBXML_ERR_FATAL:
                $errorLine .= "Fatal Error $error->code | ";
                break;
        }

        $errorLine .= trim($error->message);
        $errorLine .= " | on line $error->line";

        return $errorLine;
    }

    private function libxmlDisplayErrors(): void
    {
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            $this->errors[] = $this->libxmlDisplayError($error);
        }
        libxml_clear_errors();
    }
}
