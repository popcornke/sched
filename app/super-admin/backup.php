<?php

declare(strict_types=1);

require_once __DIR__ . '/shared.php';
saRequire();

$db = authDb();
$databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$tableCount = 0;
$dbSize = null;
try {
    $tableCount = saCount($db, 'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = "BASE TABLE"');
    $stmt = $db->query('SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = DATABASE()');
    $v = $stmt->fetchColumn();
    $dbSize = $v === null || $v === false ? null : (float) $v;
} catch (Throwable $e) {
    error_log('BCP backup info: ' . $e->getMessage());
}

saPageStart('Database Backup', 'backup');
?>
<header class="sa-page-head">
    <div><span class="sa-eyebrow">DATABASE CONTROL</span><h1>Database backup.</h1><p>Create a portable SQL export without relying on <span class="sa-code">mysqldump</span>, so the same workflow works on local XAMPP and Railway.</p></div>
</header>

<section class="sa-grid sa-grid--2">
    <article class="sa-card sa-card-pad">
        <div class="sa-section-title"><div><h2>Current database</h2><p>Backup source details.</p></div></div>
        <dl class="sa-kv">
            <dt>Database name</dt><dd><?= saEsc($databaseName) ?></dd>
            <dt>Base tables</dt><dd><?= number_format($tableCount) ?></dd>
            <dt>Estimated size</dt><dd><?= $dbSize === null ? 'Unavailable' : saEsc(number_format($dbSize, 2) . ' MB') ?></dd>
            <dt>Backup type</dt><dd>Full schema + table data (.sql)</dd>
            <dt>Transport</dt><dd>Direct browser download</dd>
        </dl>
    </article>

    <article class="sa-card sa-card-pad">
        <div class="sa-section-title"><div><h2>Create backup</h2><p>Exports the database state at the time the download starts.</p></div></div>
        <div class="sa-note">The SQL file contains application data including password hashes and other internal records. Treat it as a restricted administrative backup.</div>
        <form method="post" action="backup-download.php" style="margin-top:16px" onsubmit="return confirm('Download a full SQL backup of the current database?')">
            <input type="hidden" name="csrf_token" value="<?= saEsc(authCsrf()) ?>">
            <button class="sa-btn sa-btn--primary" type="submit"><i class="fa-solid fa-download"></i>Download Full SQL Backup</button>
        </form>
    </article>
</section>

<section class="sa-section sa-card sa-card-pad">
    <div class="sa-section-title"><div><h2>Restore note</h2><p>This version intentionally does not include one-click restore.</p></div></div>
    <p class="sa-note" style="margin:0">A backup is safe to generate from the web console, but restoring can overwrite live scheduling records. Keep restore as a controlled DBA/phpMyAdmin operation until we add a separate validated restore workflow with confirmation and rollback protection.</p>
</section>
<?php saPageEnd(); ?>
