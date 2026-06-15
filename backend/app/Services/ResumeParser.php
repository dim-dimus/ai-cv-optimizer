<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ResumeParseException;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * Extracts plain text from an uploaded resume file (PDF or DOCX).
 */
class ResumeParser
{
    private const PDF_MIMES = ['application/pdf'];

    private const DOCX_MIMES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/zip',
    ];

    /**
     * @throws ResumeParseException when the file cannot be read as text
     */
    public function parse(string $absolutePath, string $mime, string $originalFilename): string
    {
        $extension = mb_strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

        $text = match (true) {
            $extension === 'pdf' || in_array($mime, self::PDF_MIMES, true) => $this->parsePdf($absolutePath),
            $extension === 'docx' || in_array($mime, self::DOCX_MIMES, true) => $this->parseDocx($absolutePath),
            default => throw new ResumeParseException('Unsupported file type. Upload a PDF or DOCX.'),
        };

        $text = trim($this->normalizeWhitespace($text));

        if ($text === '') {
            throw new ResumeParseException(
                'We could not read any text from this file. It may be scanned, empty, or password-protected. '.
                'Try another file or paste the text after uploading.'
            );
        }

        return $text;
    }

    private function parsePdf(string $path): string
    {
        try {
            return (new PdfParser)->parseFile($path)->getText();
        } catch (Throwable $e) {
            throw new ResumeParseException(
                'This PDF could not be read. It may be encrypted or corrupted.',
                previous: $e,
            );
        }
    }

    private function parseDocx(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new ResumeParseException('This DOCX file could not be opened.');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new ResumeParseException('This DOCX file is missing its document content.');
        }

        // Convert paragraph and line breaks to newlines, then strip remaining tags.
        $withBreaks = preg_replace('#</w:p>#', "\n", $xml) ?? $xml;
        $withBreaks = preg_replace('#<w:br\s*/?>#', "\n", $withBreaks) ?? $withBreaks;

        return html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function normalizeWhitespace(string $text): string
    {
        // Collapse runs of spaces/tabs and trim trailing space on each line,
        // but keep paragraph structure (newlines).
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */', "\n", $text) ?? $text;

        return preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
    }
}
