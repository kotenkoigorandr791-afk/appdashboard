<?php
declare(strict_types=1);

use PhpOffice\PhpPresentation\IOFactory as PresentationIOFactory;
use PhpOffice\PhpPresentation\PhpPresentation;
use PhpOffice\PhpPresentation\Shape\Group;
use PhpOffice\PhpPresentation\Shape\RichText;
use PhpOffice\PhpPresentation\Shape\RichText\TextElementInterface;
use PhpOffice\PhpPresentation\Shape\Table;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;

function cloud_ai_truncate_extracted_text(string $text, int $maxChars): string
{
    if (strlen($text) > $maxChars) {
        return substr($text, 0, $maxChars) . "\n\n[... truncated ...]";
    }
    return $text;
}
function cloud_ai_markdown_escape_cell(string $text): string
{
    return str_replace(["\r", "\n", '|'], [' ', ' ', '\|'], trim($text));
}
function cloud_ai_markdown_table(array $rows): string
{
    if ($rows === []) {
        return '';
    }
    $width = 0;
    foreach ($rows as $row) {
        $width = max($width, count($row));
    }
    if ($width === 0) {
        return '';
    }
    $normalized = [];
    foreach ($rows as $row) {
        $line = [];
        for ($i = 0; $i < $width; $i++) {
            $line[] = cloud_ai_markdown_escape_cell((string)($row[$i] ?? ''));
        }
        $normalized[] = $line;
    }
    $header = array_shift($normalized);
    if (!array_filter($header, static fn(string $v): bool => $v !== '')) {
        $header = array_map(static fn(int $i): string => 'Column ' . ($i + 1), range(0, $width - 1));
    }
    $out = ['| ' . implode(' | ', $header) . ' |', '| ' . implode(' | ', array_fill(0, $width, '---')) . ' |'];
    foreach ($normalized as $row) {
        $out[] = '| ' . implode(' | ', $row) . ' |';
    }
    return implode("\n", $out);
}
function cloud_ai_unzip_entry(string $filePath, string $entry): ?string
{
    $cmd = 'unzip -p ' . escapeshellarg($filePath) . ' ' . escapeshellarg($entry) . ' 2>/dev/null';
    $out = shell_exec($cmd);
    return is_string($out) && $out !== '' ? $out : null;
}
function cloud_ai_unzip_list(string $filePath): array
{
    $cmd = 'unzip -Z1 ' . escapeshellarg($filePath) . ' 2>/dev/null';
    $out = shell_exec($cmd);
    if (!is_string($out) || trim($out) === '') {
        return [];
    }
    return array_values(array_filter(array_map('trim', explode("\n", $out)), static fn(string $s): bool => $s !== ''));
}
function cloud_ai_xml_text_decode(string $text): string
{
    return html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_XML1, 'UTF-8');
}

function cloud_ai_cell_scalar_value(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    if (is_bool($value)) {
        return $value ? 'TRUE' : 'FALSE';
    }
    if ($value instanceof \DateTimeInterface) {
        return $value->format('Y-m-d H:i:s');
    }
    if (is_float($value) || is_int($value)) {
        return (string)$value;
    }
    return trim((string)$value);
}

function cloud_ai_extract_pdf_text(string $filePath, int $maxChars = 120000): array
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return ['ok' => false, 'error' => 'File not found'];
    }
    $tmpOut = tempnam(sys_get_temp_dir(), 'pdf_txt_');
    if ($tmpOut === false) {
        return ['ok' => false, 'error' => 'Failed to create temp file'];
    }
    $cmd = 'pdftotext -enc UTF-8 ' . escapeshellarg($filePath) . ' ' . escapeshellarg($tmpOut) . ' 2>&1';
    exec($cmd, $output, $code);
    $text = is_file($tmpOut) ? (string)file_get_contents($tmpOut) : '';
    @unlink($tmpOut);
    if ($code !== 0 || trim($text) === '') {
        return ['ok' => false, 'error' => 'PDF text extraction failed', 'details' => implode("\n", $output)];
    }
    return ['ok' => true, 'content' => cloud_ai_truncate_extracted_text($text, $maxChars), 'mime' => 'application/pdf'];
}

function cloud_ai_collect_rich_text_lines(object $rich): array
{
    if (!method_exists($rich, 'getParagraphs')) {
        return [];
    }
    $lines = [];
    foreach ($rich->getParagraphs() as $paragraph) {
        $parts = [];
        foreach ($paragraph->getRichTextElements() as $textElement) {
            if ($textElement instanceof TextElementInterface) {
                $t = trim($textElement->getText());
                if ($t !== '') {
                    $parts[] = $t;
                }
            }
        }
        if ($parts !== []) {
            $lines[] = implode('', $parts);
        }
    }
    return $lines;
}

function cloud_ai_collect_shape_text_lines(mixed $shape): array
{
    if ($shape instanceof RichText) {
        return cloud_ai_collect_rich_text_lines($shape);
    }
    if ($shape instanceof Table) {
        $lines = [];
        foreach ($shape->getRows() as $row) {
            foreach ($row->getCells() as $cell) {
                foreach (cloud_ai_collect_rich_text_lines($cell) as $line) {
                    $lines[] = $line;
                }
            }
        }
        return $lines;
    }
    if ($shape instanceof Group) {
        $lines = [];
        foreach ($shape->getShapeCollection() as $child) {
            foreach (cloud_ai_collect_shape_text_lines($child) as $line) {
                $lines[] = $line;
            }
        }
        return $lines;
    }
    return [];
}

function cloud_ai_presentation_to_text(PhpPresentation $presentation): string
{
    $chunks = [];
    $slideIndex = 1;
    foreach ($presentation->getAllSlides() as $slide) {
        $slideText = [];
        foreach ($slide->getShapeCollection() as $shape) {
            foreach (cloud_ai_collect_shape_text_lines($shape) as $line) {
                $slideText[] = $line;
            }
        }
        if ($slideText !== []) {
            $chunks[] = '--- Slide ' . $slideIndex . " ---\n" . implode("\n", $slideText);
        }
        $slideIndex++;
    }
    return trim(implode("\n\n", $chunks));
}

function cloud_ai_presentation_reader_for_ext(string $ext): ?string
{
    return match ($ext) {
        'pptx' => 'PowerPoint2007',
        'ppt' => 'PowerPoint97',
        'odp' => 'ODPresentation',
        default => null,
    };
}

function cloud_ai_load_presentation_file(string $filePath): ?PhpPresentation
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $readerName = cloud_ai_presentation_reader_for_ext($ext);
    if ($readerName !== null) {
        try {
            $reader = PresentationIOFactory::createReader($readerName);
            if ($reader->canRead($filePath)) {
                return $reader->load($filePath);
            }
        } catch (Throwable $e) {
        }
    }
    try {
        return PresentationIOFactory::load($filePath);
    } catch (Throwable $e) {
        return null;
    }
}

function cloud_ai_extract_pptx_xml_text(string $filePath, int $maxChars = 120000): array
{
    if (!class_exists(ZipArchive::class)) {
        return ['ok' => false, 'error' => 'Zip extension is required'];
    }
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return ['ok' => false, 'error' => 'Invalid PPTX archive'];
    }
    $slideTexts = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        if (!is_string($entry) || !preg_match('#^ppt/slides/slide(\d+)\.xml$#i', $entry, $m)) {
            continue;
        }
        $xml = $zip->getFromName($entry);
        if ($xml === false || $xml === '') {
            continue;
        }
        $lines = cloud_ai_xml_collect_drawml_text($xml);
        if ($lines !== []) {
            $slideTexts[(int)$m[1]] = $lines;
        }
    }
    $zip->close();
    if ($slideTexts === []) {
        return ['ok' => false, 'error' => 'No text found in PPTX XML'];
    }
    ksort($slideTexts, SORT_NUMERIC);
    $chunks = [];
    foreach ($slideTexts as $num => $lines) {
        $chunks[] = '--- Slide ' . $num . " ---\n" . implode("\n", $lines);
    }
    $text = trim(implode("\n\n", $chunks));
    return ['ok' => true, 'content' => cloud_ai_truncate_extracted_text($text, $maxChars), 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
}

function cloud_ai_xml_collect_drawml_text(string $xml): array
{
    $dom = new DOMDocument();
    if (!@$dom->loadXML($xml)) {
        return [];
    }
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
    $lines = [];
    $current = [];
    foreach ($xpath->query('//a:t') as $node) {
        $t = trim($node->textContent ?? '');
        if ($t === '') {
            continue;
        }
        $parentName = $node->parentNode?->nodeName ?? '';
        if ($parentName === 'a:p') {
            if ($current !== []) {
                $lines[] = implode('', $current);
                $current = [];
            }
            $current[] = $t;
        } else {
            $current[] = $t;
        }
    }
    if ($current !== []) {
        $lines[] = implode('', $current);
    }
    return array_values(array_unique(array_filter($lines, static fn(string $s): bool => $s !== '')));
}

function cloud_ai_extract_presentation_text(string $filePath, int $maxChars = 120000): array
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return ['ok' => false, 'error' => 'File not found'];
    }
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $lastError = 'No text found in presentation';
    $presentation = cloud_ai_load_presentation_file($filePath);
    if ($presentation instanceof PhpPresentation) {
        $text = cloud_ai_presentation_to_text($presentation);
        if ($text !== '') {
            return ['ok' => true, 'content' => cloud_ai_truncate_extracted_text($text, $maxChars), 'mime' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
        }
        $lastError = 'No text found in presentation (PhpPresentation)';
    } else {
        $lastError = 'Presentation parse error: could not load file';
    }
    if ($ext === 'pptx') {
        $xmlResult = cloud_ai_extract_pptx_xml_text($filePath, $maxChars);
        if ($xmlResult['ok'] ?? false) {
            return $xmlResult;
        }
        $lastError = (string)($xmlResult['error'] ?? $lastError);
    }
    return ['ok' => false, 'error' => $lastError];
}

function cloud_ai_extract_spreadsheet_text(string $filePath, int $maxChars = 120000): array
{
    if (!is_file($filePath) || !is_readable($filePath)) {
        return ['ok' => false, 'error' => 'File not found'];
    }
    try {
        $spreadsheet = SpreadsheetIOFactory::load($filePath);
        $chunks = [];
        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = trim((string)$sheet->getTitle());
            $rows = [];
            $highestRow = min((int)$sheet->getHighestRow(), 5000);
            $highestColumn = $sheet->getHighestColumn();
            $highestColIndex = Coordinate::columnIndexFromString($highestColumn);
            $highestColIndex = min($highestColIndex, 120);
            for ($rowIndex = 1; $rowIndex <= $highestRow; $rowIndex++) {
                $cells = [];
                for ($colIndex = 1; $colIndex <= $highestColIndex; $colIndex++) {
                    $coord = Coordinate::stringFromColumnIndex($colIndex) . $rowIndex;
                    $cell = $sheet->getCell($coord);
                    $val = cloud_ai_cell_scalar_value($cell->getCalculatedValue());
                    $cells[] = $val;
                }
                while ($cells !== [] && end($cells) === '') {
                    array_pop($cells);
                }
                if (array_filter($cells, static fn(string $v): bool => $v !== '')) {
                    $rows[] = $cells;
                }
            }
            if ($rows !== []) {
                $label = $title !== '' ? $title : 'Sheet';
                $chunks[] = '## Sheet: ' . $label . "\n\n" . cloud_ai_markdown_table($rows);
            }
        }
        $text = trim(implode("\n\n", $chunks));
        if ($text === '') {
            return ['ok' => false, 'error' => 'No data found in spreadsheet'];
        }
        return ['ok' => true, 'content' => cloud_ai_truncate_extracted_text($text, $maxChars), 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    } catch (Throwable $e) {
        if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'xlsx') {
            $fallback = cloud_ai_extract_xlsx_xml_text($filePath, $maxChars);
            if ($fallback['ok'] ?? false) {
                return $fallback;
            }
        }
        return ['ok' => false, 'error' => 'Spreadsheet parse error: ' . $e->getMessage()];
    }
}
function cloud_ai_extract_xlsx_shared_strings(string $filePath): array
{
    $xml = cloud_ai_unzip_entry($filePath, 'xl/sharedStrings.xml');
    if ($xml === null) {
        return [];
    }
    $strings = [];
    if (!preg_match_all('#<si\b[^>]*>(.*?)</si>#is', $xml, $items)) {
        return [];
    }
    foreach ($items[1] as $si) {
        $parts = [];
        if (preg_match_all('#<t\b[^>]*>(.*?)</t>#is', $si, $texts)) {
            foreach ($texts[1] as $t) {
                $parts[] = cloud_ai_xml_text_decode($t);
            }
        }
        $strings[] = implode('', $parts);
    }
    return $strings;
}
function cloud_ai_extract_xlsx_xml_text(string $filePath, int $maxChars = 120000): array
{
    $entries = array_values(array_filter(cloud_ai_unzip_list($filePath), static fn(string $s): bool => preg_match('#^xl/worksheets/sheet\d+\.xml$#i', $s) === 1));
    if ($entries === []) {
        return ['ok' => false, 'error' => 'No worksheets found in XLSX'];
    }
    sort($entries, SORT_NATURAL);
    $shared = cloud_ai_extract_xlsx_shared_strings($filePath);
    $chunks = [];
    foreach ($entries as $index => $entry) {
        $xml = cloud_ai_unzip_entry($filePath, $entry);
        if ($xml === null) {
            continue;
        }
        $rows = [];
        if (!preg_match_all('#<row\b[^>]*>(.*?)</row>#is', $xml, $rowMatches)) {
            continue;
        }
        foreach ($rowMatches[1] as $rowXml) {
            $row = [];
            if (!preg_match_all('#<c\b([^>]*)>(.*?)</c>#is', $rowXml, $cellMatches, PREG_SET_ORDER)) {
                continue;
            }
            foreach ($cellMatches as $cellMatch) {
                $attrs = $cellMatch[1] ?? '';
                $cellXml = $cellMatch[2] ?? '';
                $type = preg_match('#\bt="([^"]+)"#i', $attrs, $tm) ? $tm[1] : '';
                $value = preg_match('#<v\b[^>]*>(.*?)</v>#is', $cellXml, $vm) ? cloud_ai_xml_text_decode($vm[1]) : '';
                if ($type === 's' && isset($shared[(int)$value])) {
                    $value = $shared[(int)$value];
                }
                $row[] = trim($value);
            }
            if (array_filter($row, static fn(string $v): bool => $v !== '')) {
                $rows[] = $row;
            }
            if (count($rows) >= 5000) {
                break;
            }
        }
        $table = cloud_ai_markdown_table($rows);
        if ($table !== '') {
            $chunks[] = '## Sheet: Sheet ' . ($index + 1) . "\n\n" . $table;
        }
    }
    $text = trim(implode("\n\n", $chunks));
    if ($text === '') {
        return ['ok' => false, 'error' => 'No data found in XLSX'];
    }
    return ['ok' => true, 'content' => cloud_ai_truncate_extracted_text($text, $maxChars), 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
}
function cloud_ai_extract_docx_text(string $filePath, int $maxChars = 120000): array
{
    $xml = cloud_ai_unzip_entry($filePath, 'word/document.xml');
    if (!is_string($xml) || $xml === '') {
        return ['ok' => false, 'error' => 'DOCX document.xml not found'];
    }
    $chunks = [];
    $xmlWithoutTables = preg_replace('#<w:tbl\b[^>]*>.*?</w:tbl>#is', '', $xml) ?? $xml;
    if (preg_match_all('#<w:p\b[^>]*>(.*?)</w:p>#is', $xmlWithoutTables, $paragraphs)) {
        foreach ($paragraphs[1] as $paragraph) {
            $parts = [];
            if (preg_match_all('#<w:t\b[^>]*>(.*?)</w:t>#is', $paragraph, $texts)) {
                foreach ($texts[1] as $text) {
                    $parts[] = cloud_ai_xml_text_decode($text);
                }
            }
            $line = trim(implode('', $parts));
            if ($line !== '') {
                $chunks[] = $line;
            }
        }
    }
    if (preg_match_all('#<w:tbl\b[^>]*>(.*?)</w:tbl>#is', $xml, $tables)) {
        foreach ($tables[1] as $tableXml) {
        $rows = [];
            if (!preg_match_all('#<w:tr\b[^>]*>(.*?)</w:tr>#is', $tableXml, $trMatches)) {
                continue;
            }
            foreach ($trMatches[1] as $tr) {
            $row = [];
                if (!preg_match_all('#<w:tc\b[^>]*>(.*?)</w:tc>#is', $tr, $tcMatches)) {
                    continue;
                }
                foreach ($tcMatches[1] as $tc) {
                    $parts = [];
                    if (preg_match_all('#<w:t\b[^>]*>(.*?)</w:t>#is', $tc, $texts)) {
                        foreach ($texts[1] as $text) {
                            $parts[] = cloud_ai_xml_text_decode($text);
                        }
                    }
                    $row[] = trim(implode(' ', $parts));
                }
                if (array_filter($row, static fn(string $v): bool => $v !== '')) {
                    $rows[] = $row;
                }
            }
        $table = cloud_ai_markdown_table($rows);
        if ($table !== '') {
            $chunks[] = $table;
        }
        }
    }
    $text = trim(implode("\n\n", $chunks));
    if ($text === '') {
        return ['ok' => false, 'error' => 'No text found in DOCX'];
    }
    return ['ok' => true, 'content' => cloud_ai_truncate_extracted_text($text, $maxChars), 'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
}

function cloud_ai_is_extractable_document_ext(string $ext): bool
{
    return in_array($ext, ['pdf', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'ods'], true);
}

function cloud_ai_extract_document_text(string $filePath, int $maxChars = 120000): array
{
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if ($ext === 'pdf') {
        return cloud_ai_extract_pdf_text($filePath, $maxChars);
    }
    if ($ext === 'docx') {
        return cloud_ai_extract_docx_text($filePath, $maxChars);
    }
    if (in_array($ext, ['ppt', 'pptx'], true)) {
        return cloud_ai_extract_presentation_text($filePath, $maxChars);
    }
    if (in_array($ext, ['xls', 'xlsx', 'ods'], true)) {
        return cloud_ai_extract_spreadsheet_text($filePath, $maxChars);
    }
    return ['ok' => false, 'error' => 'Unsupported document type: ' . $ext];
}
