<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use ZipArchive;

/**
 * Renders cover-letter text to downloadable PDF and DOCX binaries.
 */
class CoverLetterExport
{
    public function pdf(string $content): string
    {
        $body = nl2br(htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
        $html = '<html><head><meta charset="utf-8"></head>'
            .'<body style="font-family: DejaVu Serif, serif; font-size: 12pt; line-height: 1.5;">'
            .$body.'</body></html>';

        $dompdf = new Dompdf;
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    public function docx(string $content): string
    {
        $paragraphs = '';
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $escaped = htmlspecialchars($line, ENT_QUOTES | ENT_XML1, 'UTF-8');
            $paragraphs .= "<w:p><w:r><w:t xml:space=\"preserve\">{$escaped}</w:t></w:r></w:p>";
        }

        $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            ."<w:body>{$paragraphs}</w:body></w:document>";

        $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';

        $path = tempnam(sys_get_temp_dir(), 'cl').'.docx';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $contentTypes);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();

        $binary = (string) file_get_contents($path);
        @unlink($path);

        return $binary;
    }
}
