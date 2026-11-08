<?php

declare(strict_types=1);

require_once __DIR__ . '/report-data.php';
saRequire();

date_default_timezone_set('Asia/Manila');
$db = authDb();
$catalog = saReportCatalog();
$type = (string) ($_GET['type'] ?? 'accounts');
$format = strtolower((string) ($_GET['format'] ?? 'csv'));
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));

if (!isset($catalog[$type]) || !in_array($format, ['csv', 'pdf'], true)) {
    http_response_code(400);
    exit('Invalid report request.');
}

try {
    $report = saReportData($db, $type, $from ?: null, $to ?: null, 5000);
    $rows = $report['rows'];
    $title = (string) $report['title'];
} catch (Throwable $e) {
    error_log('BCP report export: ' . $e->getMessage());
    http_response_code(500);
    exit('Unable to generate report.');
}

auditLog(
    $db,
    'REPORT_EXPORTED',
    'REPORTS',
    'REPORT',
    $type,
    'Exported ' . $title . ' as ' . strtoupper($format) . '.',
    ['format' => $format, 'from' => $from ?: null, 'to' => $to ?: null, 'rows' => count($rows)]
);

$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($type));
$stamp = date('Ymd-His');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="bcp-' . $slug . '-' . $stamp . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    if ($rows === []) {
        fputcsv($out, ['No data']);
    } else {
        $headers = array_keys($rows[0]);
        fputcsv($out, $headers);
        foreach ($rows as $row) {
            $line = [];
            foreach ($headers as $header) $line[] = $row[$header] ?? '';
            fputcsv($out, $line);
        }
    }
    fclose($out);
    exit;
}

function saPdfAscii(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($converted)) $value = $converted;
    }
    return preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;
}

function saPdfEscape(string $value): string
{
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], saPdfAscii($value));
}

function saPdfWrap(string $line, int $width = 104): array
{
    if ($line === '') return [''];
    $wrapped = wordwrap(saPdfAscii($line), $width, "\n", true);
    return explode("\n", $wrapped);
}

function saBuildSimplePdf(string $title, array $rows, string $filterText): string
{
    $lines = [];
    $lines[] = $title;
    $lines[] = 'Bestlink College of the Philippines - Super Admin Report';
    $lines[] = 'Generated: ' . date('Y-m-d H:i:s') . ' Asia/Manila';
    if ($filterText !== '') $lines[] = $filterText;
    $lines[] = str_repeat('-', 100);

    if ($rows === []) {
        $lines[] = 'No data available for this report.';
    } else {
        foreach ($rows as $row) {
            $parts = [];
            foreach ($row as $key => $value) {
                $parts[] = str_replace('_', ' ', (string) $key) . ': ' . (string) $value;
            }
            foreach (saPdfWrap(implode(' | ', $parts)) as $wrapped) $lines[] = $wrapped;
            $lines[] = '';
        }
    }

    $pages = array_chunk($lines, 49);
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';

    $kids = [];
    $objNo = 4;
    foreach ($pages as $pageIndex => $pageLines) {
        $pageObj = $objNo++;
        $contentObj = $objNo++;
        $kids[] = $pageObj . ' 0 R';

        $stream = "BT\n/F1 9 Tf\n40 800 Td\n";
        foreach ($pageLines as $i => $line) {
            if ($i > 0) $stream .= "0 -14 Td\n";
            $stream .= '(' . saPdfEscape((string) $line) . ") Tj\n";
        }
        $stream .= "ET\n";

        $objects[$pageObj] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObj . ' 0 R >>';
        $objects[$contentObj] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
    }
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

    ksort($objects);
    $pdf = "%PDF-1.4\n%BCP\n";
    $offsets = [0 => 0];
    $max = max(array_keys($objects));
    for ($i = 1; $i <= $max; $i++) {
        $offsets[$i] = strlen($pdf);
        $pdf .= $i . " 0 obj\n" . ($objects[$i] ?? '<<>>') . "\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . ($max + 1) . "\n";
    $pdf .= "0000000000 65535 f \n";
    for ($i = 1; $i <= $max; $i++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
    }
    $pdf .= "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    return $pdf;
}

$filterParts = [];
if ($from !== '') $filterParts[] = 'From: ' . $from;
if ($to !== '') $filterParts[] = 'To: ' . $to;
$pdf = saBuildSimplePdf($title, $rows, implode(' | ', $filterParts));
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="bcp-' . $slug . '-' . $stamp . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: no-store');
echo $pdf;
exit;
