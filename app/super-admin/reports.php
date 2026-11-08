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

try {
    $report = saReportData($db, $type, $from ?: null, $to ?: null, 20);
    $previewRows = $report['rows'];
    $previewError = '';
} catch (Throwable $e) {
    error_log('BCP report preview: ' . $e->getMessage());
    $report = ['title' => $catalog[$type]['label'], 'rows' => []];
    $previewRows = [];
    $previewError = 'Unable to load report preview.';
}

$headers = $previewRows ? array_keys($previewRows[0]) : [];
$params = http_build_query(['type' => $type, 'from' => $from, 'to' => $to]);

saPageStart('Reports', 'reports');
?>
<header class="sa-page-head">
    <div><span class="sa-eyebrow">REPORTING</span><h1>System reports.</h1><p>Create operational reports and export the current selection as CSV or PDF.</p></div>
    <div class="sa-actions"><a class="sa-btn sa-btn--success" href="report-export.php?<?= saEsc($params) ?>&format=csv"><i class="fa-solid fa-file-csv"></i>Export CSV</a><a class="sa-btn sa-btn--primary" href="report-export.php?<?= saEsc($params) ?>&format=pdf"><i class="fa-solid fa-file-pdf"></i>Export PDF</a></div>
</header>

<section class="sa-card sa-card-pad">
    <form method="get" class="sa-filter">
        <div class="sa-field"><label>Report type</label><select name="type"><?php foreach ($catalog as $key => $item): ?><option value="<?= saEsc($key) ?>" <?= $type === $key ? 'selected' : '' ?>><?= saEsc($item['label']) ?></option><?php endforeach; ?></select></div>
        <div class="sa-field"><label>From date</label><input type="date" name="from" value="<?= saEsc($from) ?>"></div>
        <div class="sa-field"><label>To date</label><input type="date" name="to" value="<?= saEsc($to) ?>"></div>
        <button class="sa-btn sa-btn--primary" type="submit"><i class="fa-solid fa-eye"></i>Preview</button>
    </form>
    <div class="sa-note" style="margin-top:14px"><strong><?= saEsc($catalog[$type]['label']) ?>:</strong> <?= saEsc($catalog[$type]['description']) ?> Date filters apply to reports that contain a creation/submission timestamp.</div>
</section>

<section class="sa-section sa-card">
    <div class="sa-card-pad"><div class="sa-section-title"><div><h2><?= saEsc((string) $report['title']) ?></h2><p>Preview is limited to 20 rows; exports include up to 5,000 rows.</p></div></div><?php if ($previewError): ?><div class="sa-alert sa-alert--error"><?= saEsc($previewError) ?></div><?php endif; ?></div>
    <div class="sa-table-wrap">
        <?php if ($previewRows === []): ?><div class="sa-empty">No rows available for this report and filter.</div><?php else: ?>
        <table class="sa-table"><thead><tr><?php foreach ($headers as $header): ?><th><?= saEsc(str_replace('_', ' ', (string) $header)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($previewRows as $row): ?><tr><?php foreach ($headers as $header): ?><td><?= saEsc((string) ($row[$header] ?? '')) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table>
        <?php endif; ?>
    </div>
</section>
<?php saPageEnd(); ?>
