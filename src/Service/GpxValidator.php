<?php

namespace App\Service;

use App\Exception\GpxValidationException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class GpxValidator
{
    private const MAX_FILE_SIZE = 10485760;
    private const ALLOWED_MIME_TYPES = ['application/gpx+xml', 'application/xml', 'text/xml'];
    private const SUPPORTED_NAMESPACES = [
        'http://www.topografix.com/GPX/1/0',
        'http://www.topografix.com/GPX/1/1',
    ];

    public function validate(UploadedFile $file): void
    {
        $this->validateFileSize($file);
        $this->validateMimeType($file);
        $this->validateXmlStructure($file);
        $this->validateGpxContent($file);
    }

    /** Reads the root element's namespace so callers can pick a matching XSD schema before validating. */
    public function detectNamespace(UploadedFile $file): ?string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->load($file->getPathname());
        libxml_clear_errors();

        return $this->matchSupportedNamespace($dom);
    }

    private function validateFileSize(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new GpxValidationException('gpx_validation.file_too_large');
        }
    }

    private function validateMimeType(UploadedFile $file): void
    {
        $mimeType = $file->getMimeType();
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES)) {
            throw new GpxValidationException('gpx_validation.invalid_file_type');
        }
    }

    private function validateXmlStructure(UploadedFile $file): void
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($file->getPathname());

        if ($xml === false) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            throw new GpxValidationException('gpx_validation.invalid_xml_structure', ['%details%' => $errors[0]->message]);
        }
    }

    private function validateGpxContent(UploadedFile $file): void
    {
        $dom = new \DOMDocument();
        $dom->load($file->getPathname());

        $namespace = $this->matchSupportedNamespace($dom);
        if ($namespace === null) {
            throw new GpxValidationException('gpx_validation.invalid_namespace');
        }

        if (!$this->hasValidTracks($dom, $namespace)) {
            throw new GpxValidationException('gpx_validation.no_track_data');
        }
    }

    private function matchSupportedNamespace(\DOMDocument $dom): ?string
    {
        $root = $dom->documentElement;
        if (!$root || !in_array($root->namespaceURI, self::SUPPORTED_NAMESPACES, true)) {
            return null;
        }

        return $root->namespaceURI;
    }

    private function hasValidTracks(\DOMDocument $dom, string $namespace): bool
    {
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('gpx', $namespace);

        $tracks = $xpath->query('//gpx:trk | //gpx:rte');
        if ($tracks->length === 0) {
            return false;
        }

        $points = $xpath->query('//gpx:trkpt | //gpx:rtept');

        return $points->length > 0;
    }
}
