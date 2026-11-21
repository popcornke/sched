<?php

declare(strict_types=1);
require_once __DIR__ . '/report-data.php';
saRequire();
$db = authDb();
$catalog = saReportCatalog();
$type = (string) ($_GET['type'] ?? 'accounts');
if (!isset($catalog[$type])) $type = 'accounts';
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
// Invalid date or inverted range is rejected before querying/exporting.
$dateIsValid = static function (string $v): bool {
    if ($v === '') return true;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return $d !== false && $d->format('Y-m-d') === $v;
};
$filterError = (!$dateIsValid($from) || !$dateIsValid($to)) ? 'Enter a valid date.' : (($from !== '' && $to !== '' && $from > $to) ? 'From date must be on or before To date.' : '');
$previewError = $filterError;
$report = ['title' => $catalog[$type]['label'], 'rows' => []];
if ($filterError === '') {
    try {
        $report = saReportData($db, $type, $from ?: null, $to ?: null, 20);
    } catch (Throwable $e) {
        error_log('BCP report preview: ' . $e->getMessage());
        $previewError = 'Unable to load report preview. Check the server error log.';
    }
}
$previewRows = $report['rows'];
$headers = $previewRows ? array_keys($previewRows[0]) : [];
$params = http_build_query(['type' => $type, 'from' => $from, 'to' => $to]);
$renderPreview = static function () use ($report, $previewError, $previewRows, $headers, $catalog, $type): void { ?>
    <div class="sa-reports-result-head">
      <div><div class="sa-reports-kicker">REPORT PREVIEW</div><h2><?= saEsc((string)$report['title']) ?></h2><p><?= saEsc($catalog[$type]['description']) ?></p></div>
      <span class="sa-reports-count"><i class="fa-solid fa-list-check"></i> <?= count($previewRows) ?> preview rows</span>
    </div>
    <?php if ($previewError): ?><div class="sa-alert sa-alert--error" role="alert"><?= saEsc($previewError) ?></div><?php endif; ?>
    <div class="sa-reports-table-wrap">
    <?php if (!$previewRows): ?>
      <div class="sa-reports-empty"><i class="fa-regular fa-folder-open"></i><strong>No report entries to show</strong><span><?= $previewError ? 'Correct the filter or try again.' : 'Try another report type or adjust your date range.' ?></span></div>
    <?php else: ?>
      <table class="sa-table sa-reports-table"><thead><tr><?php foreach ($headers as $header): ?><th scope="col"><?= saEsc(str_replace('_', ' ', (string)$header)) ?></th><?php endforeach; ?></tr></thead><tbody>
      <?php foreach ($previewRows as $row): ?><tr><?php foreach ($headers as $header): ?>
        <td title="<?= saEsc((string)($row[$header] ?? '')) ?>"><?= saEsc((string)($row[$header] ?? '')) ?></td>
      <?php endforeach; ?></tr><?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    </div>
    <div class="sa-reports-foot"><span>Showing up to 20 records for preview. Exports include up to 5,000 records.</span><span>Read-only report</span></div>
<?php };
if (isset($_GET['partial']) && $_GET['partial'] === '1') {
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $renderPreview();
    exit;
}
$base = authBasePath();
saPageStart('Reports', 'reports');
?>
<link rel="stylesheet" href="<?= saEsc($base) ?>/app/assets/css/sa-reports-refresh.css?v=20261121b">
<header class="sa-page-head sa-reports-page-head">
 <div><span class="sa-eyebrow">REPORTING & INSIGHTS</span><h1>System reports.</h1><p>Explore system records, refine your selection, and export a report.</p></div>
 <div class="sa-reports-exports"><a id="saReportCsv" class="sa-btn sa-reports-export" href="report-export.php?<?= saEsc($params) ?>&amp;format=csv" <?= $previewError ? 'aria-disabled="true"' : '' ?>><i class="fa-solid fa-file-csv"></i> Export CSV</a><a id="saReportPdf" class="sa-btn sa-reports-export sa-reports-export-primary" href="report-export.php?<?= saEsc($params) ?>&amp;format=pdf" <?= $previewError ? 'aria-disabled="true"' : '' ?>><i class="fa-solid fa-file-pdf"></i> Export PDF</a></div>
</header>
<section class="sa-card sa-reports-filter-card">
 <div class="sa-reports-filter-heading"><div><strong><i class="fa-solid fa-sliders"></i> Report filters</strong><p>Preview updates automatically when you change a filter.</p></div><span class="sa-reports-auto"><i class="fa-solid fa-bolt"></i> Auto preview</span></div>
 <form id="saReportFilters" class="sa-reports-filter-grid" method="get" action="reports.php" novalidate>
   <div class="sa-field sa-reports-type"><label for="saReportType">Report type</label><div class="sa-reports-select"><select id="saReportType" name="type"><?php foreach ($catalog as $key => $item): ?><option value="<?= saEsc($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= saEsc($item['label']) ?></option><?php endforeach; ?></select><i class="fa-solid fa-chevron-down"></i></div></div>
   <div class="sa-field"><label for="saReportFrom">From date</label><input id="saReportFrom" type="date" name="from" value="<?= saEsc($from) ?>"></div>
   <div class="sa-field"><label for="saReportTo">To date</label><input id="saReportTo" type="date" name="to" value="<?= saEsc($to) ?>"></div>
 </form>
 <div class="sa-reports-filter-hint" id="saReportStatus" role="status" aria-live="polite">Select a report type or date range to refresh the preview.</div>
</section>
<section class="sa-card sa-reports-results" id="saReportResults" aria-live="polite"><?php $renderPreview(); ?></section>
<script src="<?= saEsc($base) ?>/app/assets/js/sa-reports-refresh.js?v=20261121b" defer></script>
<?php saPageEnd(); ?>
