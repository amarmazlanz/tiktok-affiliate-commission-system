<?php

namespace App\Services\Exports;

use XMLWriter;

class SpreadsheetXmlWriter
{
    private XMLWriter $xml;

    public function __construct(private readonly string $path)
    {
        $this->xml = new XMLWriter();
        $this->xml->openUri($this->path);
        $this->xml->startDocument('1.0', 'UTF-8');
        $this->xml->startElement('Workbook');
        $this->xml->writeAttribute('xmlns', 'urn:schemas-microsoft-com:office:spreadsheet');
        $this->xml->writeAttribute('xmlns:o', 'urn:schemas-microsoft-com:office:office');
        $this->xml->writeAttribute('xmlns:x', 'urn:schemas-microsoft-com:office:excel');
        $this->xml->writeAttribute('xmlns:ss', 'urn:schemas-microsoft-com:office:spreadsheet');
    }

    public function startSheet(string $name): void
    {
        $this->xml->startElement('Worksheet');
        $this->xml->writeAttribute('ss:Name', mb_substr($this->sanitizeSheetName($name), 0, 31));
        $this->xml->startElement('Table');
    }

    public function row(array $cells): void
    {
        $this->xml->startElement('Row');

        foreach ($cells as $cell) {
            $this->xml->startElement('Cell');
            $this->xml->startElement('Data');
            $this->xml->writeAttribute('ss:Type', is_numeric($cell) ? 'Number' : 'String');
            $this->xml->text((string) $cell);
            $this->xml->endElement();
            $this->xml->endElement();
        }

        $this->xml->endElement();
    }

    public function endSheet(): void
    {
        $this->xml->endElement();
        $this->xml->endElement();
    }

    public function close(): void
    {
        $this->xml->endElement();
        $this->xml->endDocument();
        $this->xml->flush();
    }

    private function sanitizeSheetName(string $name): string
    {
        return trim(preg_replace('/[\[\]\:\*\?\/\\\\]/', ' ', $name)) ?: 'Sheet';
    }
}
