<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\ResumeParseException;
use App\Services\ResumeParser;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class ResumeParserTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/resume-parser-'.uniqid();
        mkdir($this->tmpDir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmpDir.'/*') ?: []);
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function test_it_extracts_text_from_a_pdf(): void
    {
        $path = $this->tmpDir.'/cv.pdf';
        file_put_contents($path, $this->buildPdf('PHP Laravel PostgreSQL'));

        $text = (new ResumeParser)->parse($path, 'application/pdf', 'cv.pdf');

        $this->assertStringContainsString('Laravel', $text);
    }

    public function test_it_extracts_text_from_a_docx(): void
    {
        $path = $this->tmpDir.'/cv.docx';
        $this->buildDocx($path, 'Figma user research design systems');

        $text = (new ResumeParser)->parse(
            $path,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'cv.docx',
        );

        $this->assertStringContainsString('Figma', $text);
        $this->assertStringContainsString('design systems', $text);
    }

    public function test_it_throws_a_clear_error_for_an_empty_docx(): void
    {
        $path = $this->tmpDir.'/empty.docx';
        $this->buildDocx($path, '   ');

        $this->expectException(ResumeParseException::class);
        (new ResumeParser)->parse(
            $path,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'empty.docx',
        );
    }

    private function buildPdf(string $text): string
    {
        $stream = "BT /F1 24 Tf 72 700 Td ({$text}) Tj ET";
        $objects = [
            1 => '<</Type/Catalog/Pages 2 0 R>>',
            2 => '<</Type/Pages/Kids[3 0 R]/Count 1>>',
            3 => '<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>',
            4 => '<</Length '.strlen($stream).">>\nstream\n".$stream."\nendstream",
            5 => '<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = count($objects) + 1;
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        foreach ($objects as $num => $body) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$num]);
        }
        $pdf .= "trailer\n<</Size {$size}/Root 1 0 R>>\nstartxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function buildDocx(string $path, string $text): void
    {
        $document = <<<XML
        <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
          <w:body><w:p><w:r><w:t>{$text}</w:t></w:r></w:p></w:body>
        </w:document>
        XML;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('word/document.xml', $document);
        $zip->close();
    }
}
