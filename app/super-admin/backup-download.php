<?php

declare(strict_types=1);

require_once __DIR__ . '/shared.php';
saRequire();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit('POST only.');
}
if (!authCsrfValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF token.');
}

set_time_limit(0);
$db = authDb();
$databaseName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
$filename = 'bcp-backup-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $databaseName) . '-' . date('Ymd-His') . '.sql';

auditLog($db, 'DATABASE_BACKUP_EXPORTED', 'DATABASE_BACKUP', 'DATABASE', $databaseName, 'Downloaded a full SQL database backup.');

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

$tableStmt = $db->query(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'
     ORDER BY TABLE_NAME"
);
$tables = $tableStmt->fetchAll(PDO::FETCH_COLUMN);

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
