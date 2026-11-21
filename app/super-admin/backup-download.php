<?php

declare(strict_types=1);

// Prevent PHP warning text or stack traces from corrupting SQL/JSON responses.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/shared.php';
saRequire();

/** Modal requests receive an explicit error so they don't mistake an HTML redirect for a SQL file. */
function backupDownloadError(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}


if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    backupDownloadError(405, 'POST only.');
}
if (!authCsrfValid($_POST['csrf_token'] ?? null)) {
    backupDownloadError(403, 'Your security session has expired. Refresh this page and try again.');
}

require_once __DIR__ . '/backup-security-common.php';
$db = authDb();
$backupMutex = 'bcp_full_sql_backup_global'; // One active export across all Super Admin accounts and computers.
$mutexStmt = $db->prepare('SELECT GET_LOCK(:name, 0)');
$mutexStmt->execute(['name' => $backupMutex]);
if ((int)$mutexStmt->fetchColumn() !== 1) {
    auditLog($db, 'BACKUP_DOWNLOAD_BUSY', 'DATABASE_BACKUP', 'AUTH_USER', (string)backupUserId(), 'Another backup export is already running.');
    backupNotify($db, 'SQL backup export busy', 'Your backup request could not start because another SQL export is running.', 'warning');
    backupDownloadError(409, 'Another SQL backup is already running. Please retry after it finishes.');
}
register_shutdown_function(static function () use ($db, $backupMutex): void {
    try {
        $r = $db->prepare('SELECT RELEASE_LOCK(:name)');
        $r->execute(['name' => $backupMutex]);
    } catch (Throwable $e) {
        error_log('Backup lock release failed: ' . $e->getMessage());
    }
});
try {
    if (!backupSecurityRow($db)) {
        backupDownloadError(403, 'Set your Backup Security Password before downloading.');
    }
    if (!backupPasswordVerify($db, (string)($_POST['backup_password'] ?? ''), 'download')) {
        $remaining = (int)max(0, 3 - (int)(backupSecurityRow($db)['failed_attempts'] ?? 0));
        backupDownloadError(403, 'Incorrect Backup Security Password. ' . $remaining . ' attempt(s) remaining before the 15-minute security lockout. This is different from your Super Admin login password.');
    }
} catch (Throwable $e) {
    error_log('Backup authorization: ' . $e->getMessage());
    backupDownloadError(503, 'Server-side backup verification error (HTTP 503). Check Apache/PHP error.log. Your password may be correct; do not keep retrying.');
}
set_time_limit(0);
try {
    $databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        throw new RuntimeException('No database is selected.');
    }
    // Verify export access before responding with an attachment.
    $tableStmt = $db->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
         ORDER BY TABLE_NAME"
    );
    $tables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $db->query('SHOW CREATE TABLE ' . '`' . str_replace('`', '``', (string)$table) . '`')->fetch(PDO::FETCH_NUM);
    }
} catch (Throwable $e) {
    error_log('BCP SQL export preparation error: ' . $e->getMessage());
    backupDownloadError(503, 'Database export preparation failed (HTTP 503). Check Apache/PHP error.log. Do not retry your password.');
}
$filename = 'bcp-backup-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $databaseName) . '-' . date('Ymd-His') . '.sql';

auditLog($db, 'DATABASE_BACKUP_EXPORTED', 'DATABASE_BACKUP', 'DATABASE', $databaseName, 'Downloaded a full SQL database backup.');
backupNotify($db, 'SQL backup download authorized', 'A full SQL backup export was authorized and its download response has started.', 'success');

header('Content-Type: application/sql; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function saSqlIdent(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

function saSqlValue(PDO $db, mixed $value): string
{
    if ($value === null) return 'NULL';
    return $db->quote((string) $value);
}

echo "-- BCP Scheduling System database backup\n";
echo '-- Database: ' . $databaseName . "\n";
echo '-- Generated: ' . date('Y-m-d H:i:s') . " Asia/Manila\n\n";
echo "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n\n";

foreach ($tables as $table) {
    $table = (string) $table;
    echo "\n-- --------------------------------------------------------\n";
    echo '-- Table: ' . $table . "\n-- --------------------------------------------------------\n";

    $create = $db->query('SHOW CREATE TABLE ' . saSqlIdent($table))->fetch(PDO::FETCH_NUM);
    if (!$create || !isset($create[1])) continue;

    echo 'DROP TABLE IF EXISTS ' . saSqlIdent($table) . ";\n";
    echo $create[1] . ";\n\n";

    // Do not export values for generated columns (for example exam_batches.active_key).
    // MySQL/MariaDB recalculates those from the CREATE TABLE definition on restore.
    $columnStmt = $db->prepare(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name
           AND UPPER(EXTRA) NOT LIKE '%GENERATED%'
         ORDER BY ORDINAL_POSITION"
    );
    $columnStmt->execute(['table_name' => $table]);
    $columns = $columnStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($columns === []) {
        echo "\n";
        continue;
    }

    $selectSql = implode(', ', array_map(static fn($col) => saSqlIdent((string) $col), $columns));
    $rows = $db->query('SELECT ' . $selectSql . ' FROM ' . saSqlIdent($table));
    $columnSql = implode(', ', array_map(static fn($col) => saSqlIdent((string) $col), $columns));
    while ($row = $rows->fetch(PDO::FETCH_ASSOC)) {
        $valueSql = implode(', ', array_map(static fn($value) => saSqlValue($db, $value), array_values($row)));
        echo 'INSERT INTO ' . saSqlIdent($table) . ' (' . $columnSql . ') VALUES (' . $valueSql . ");\n";
    }
    echo "\n";
    if (function_exists('flush')) @flush();
}

echo "SET FOREIGN_KEY_CHECKS=1;\n";
exit;
