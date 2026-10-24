<?php
declare(strict_types=1);
// Local CLI installer for the idempotent 021 migration.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }
require_once dirname(__DIR__).'/app/config/database.php';
$path=dirname(__DIR__).'/database/migrations/021_academic_preview_requests.sql';
if (!is_file($path)) { fwrite(STDERR,"Migration 021 missing.\n"); exit(1); }
try {
    $pdo=getDatabase();
    $exists=$pdo->query("SHOW TABLES LIKE 'academic_preview_requests'")->fetchColumn();
    if ($exists) { echo "academic_preview_requests already exists. No changes made.\n"; exit(0); }
    // This migration contains exactly one CREATE TABLE statement and no destructive DDL.
    $sql=file_get_contents($path);
    if (!preg_match('/CREATE\s+TABLE\s+IF\s+NOT\s+EXISTS\s+academic_preview_requests\s*\(/i',$sql)) throw new RuntimeException('Unexpected migration content');
    $pdo->exec($sql);
    echo "Migration 021 installed successfully. No saved schedules were changed.\n";
} catch (Throwable $e) {
    fwrite(STDERR,"Migration failed: ".$e->getMessage()."\n");
    exit(1);
}
