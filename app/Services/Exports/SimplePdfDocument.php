<?php

namespace App\Services\Exports;

class SimplePdfDocument
{
    private array $pages = [];

    public function addPage(array $lines, string $footer): void
    {
        $this->pages[] = ['lines' => $lines, 'footer' => $footer];
    }

    public function output(): string
    {
        $objects = [];
        $pageObjectNumbers = [];
        $fontObjectNumber = 3;

        foreach ($this->pages as $index => $page) {
            $content = $this->contentStream($page['lines'], $page['footer'], $index + 1, count($this->pages));
            $contentObjectNumber = count($objects) + 4;
            $objects[$contentObjectNumber] = "<< /Length ".strlen($content)." >>\nstream\n".$content."\nendstream";
            $pageObjectNumber = count($objects) + 4;
            $objects[$pageObjectNumber] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 {$fontObjectNumber} 0 R >> >> /Contents {$contentObjectNumber} 0 R >>";
            $pageObjectNumbers[] = $pageObjectNumber;
        }

        $kids = implode(' ', array_map(fn (int $number): string => "{$number} 0 R", $pageObjectNumbers));
        $baseObjects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => "<< /Type /Pages /Kids [{$kids}] /Count ".count($pageObjectNumbers).' >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $objects = $baseObjects + $objects;
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $body) {
            $offsets[$number] = strlen($pdf);
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    }

    private function contentStream(array $lines, string $footer, int $page, int $totalPages): string
    {
        $stream = "BT\n/F1 8 Tf\n30 560 Td\n";

        foreach ($lines as $line) {
            $stream .= '('.$this->escape($line).") Tj\n0 -11 Td\n";
        }

        $stream .= "ET\nBT\n/F1 8 Tf\n30 22 Td\n(".$this->escape($footer.' | Page '.$page.' of '.$totalPages).") Tj\nET";

        return $stream;
    }

    private function escape(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;

        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $value);
    }
}
