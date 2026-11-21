<?php

declare(strict_types=1);

require_once __DIR__ . '/backup-security-common.php';
saRequire();
header('Cache-Control: private, no-store, max-age=0');

// Handle SQL export on this same, existing page path to avoid a missing download endpoint (HTTP 404).
// This branch is executed before any HTML output and still enforces CSRF, credentials, and export mutex.
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'download') {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
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

// backup-security-common.php is already loaded by backup.php.
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

}

$db = authDb();
try {
    $configured = backupSecurityRow($db) !== null;
} catch (Throwable $e) {
    $configured = false;
    $migrationMissing = true;
}
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
    <div><span class="sa-eyebrow">DATABASE CONTROL</span>
        <h1>Database backup.</h1>
        <p style="font-size:11px;opacity:.7;margin:2px 0 0">Backup module · path correction v2.1</p>
        <p>Create a portable SQL export without relying on <span class="sa-code">mysqldump</span>, so the same workflow works on local XAMPP and Railway.</p>
    </div>
</header>

<section class="sa-grid sa-grid--2">
    <article class="sa-card sa-card-pad">
        <div class="sa-section-title">
            <div>
                <h2>Current database</h2>
                <p>Backup source details.</p>
            </div>
        </div>
        <dl class="sa-kv">
            <dt>Database name</dt>
            <dd><?= saEsc($databaseName) ?></dd>
            <dt>Base tables</dt>
            <dd><?= number_format($tableCount) ?></dd>
            <dt>Estimated size</dt>
            <dd><?= $dbSize === null ? 'Unavailable' : saEsc(number_format($dbSize, 2) . ' MB') ?></dd>
            <dt>Backup type</dt>
            <dd>Full schema + table data (.sql)</dd>
            <dt>Transport</dt>
            <dd>Direct browser download</dd>
        </dl>
    </article>

    <article class="sa-card sa-card-pad sa-backup-ui">
        <div class="sa-section-title">
            <div>
                <h2>Create backup</h2>
                <p>Exports the database state at the time the download starts.</p>
            </div>
        </div>
        <div class="sa-note">The SQL file contains application data including password hashes and other internal records. Treat it as a restricted administrative backup.</div>
        <div class="sa-backup-actions">
            <?php if (!empty($migrationMissing)): ?>
                <div class="sa-alert sa-alert--error" style="margin-top:14px">Backup security is not installed. Run migration 031 before enabling downloads.</div>
            <?php elseif (!$configured): ?>
                <button class="sa-btn sa-btn--primary" type="button" data-backup-open="set">Set Backup Security Password</button>
            <?php else: ?>
                <button class="sa-btn sa-btn--primary" type="button" data-backup-open="download"><i class="fa-solid fa-download"></i> Download Full SQL Backup</button>
                <button class="sa-btn" type="button" data-backup-open="change">Change Password</button>
                <button class="sa-btn" type="button" data-backup-open="forgot">Forgot Password</button>
            <?php endif; ?>
        </div>
    </article>
</section>

<section class="sa-section sa-card sa-card-pad">
    <div class="sa-section-title">
        <div>
            <h2>Restore note</h2>
            <p>This version intentionally does not include one-click restore.</p>
        </div>
    </div>
    <p class="sa-note" style="margin:0">A backup is safe to generate from the web console, but restoring can overwrite live scheduling records. Keep restore as a controlled DBA/phpMyAdmin operation until we add a separate validated restore workflow with confirmation and rollback protection.</p>
</section>
<style>
    /* Scoped to Database Backup: match the Plus Jakarta Sans typography used in the Super Admin UI. */
    .sa-backup-ui .sa-backup-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
        margin-top: 16px
    }

    .sa-backup-ui .sa-backup-actions .sa-btn,
    .bkp-dialog .sa-btn {
        appearance: none;
        font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
        font-size: .76rem;
        font-weight: 700;
        line-height: 1.35;
        letter-spacing: 0;
        border-radius: 9px;
        padding: 10px 14px;
        min-height: 40px;
        white-space: normal;
        transition: background-color .15s, border-color .15s, box-shadow .15s
    }

    .sa-backup-ui .sa-backup-actions .sa-btn:not(.sa-btn--primary),
    .bkp-dialog .sa-btn:not(.sa-btn--primary) {
        background: #fff;
        border: 1px solid #dce4f1;
        color: #33415d
    }

    .sa-backup-ui .sa-backup-actions .sa-btn:not(.sa-btn--primary):hover,
    .bkp-dialog .sa-btn:not(.sa-btn--primary):hover {
        background: #f1f5fd;
        border-color: #bccbed
    }

    .sa-backup-ui .sa-backup-actions .sa-btn:focus-visible,
    .bkp-dialog .sa-btn:focus-visible {
        outline: 2px solid #1a3a8c;
        outline-offset: 2px
    }

    .sa-backup-ui .sa-backup-actions .sa-btn--primary,
    .bkp-dialog .sa-btn--primary {
        background: #1a3a8c;
        border: 1px solid #1a3a8c;
        color: #fff
    }

    .sa-backup-ui .sa-backup-actions .sa-btn--primary:hover,
    .bkp-dialog .sa-btn--primary:hover {
        background: #244fa8
    }

    @media(max-width:560px) {
        .sa-backup-ui .sa-backup-actions {
            align-items: stretch;
            flex-direction: column
        }

        .sa-backup-ui .sa-backup-actions .sa-btn {
            width: 100%
        }
    }

    .bkp-overlay[hidden] {
        display: none !important
    }

    .bkp-overlay {
        position: fixed;
        inset: 0;
        background: rgba(12, 28, 58, .64);
        display: grid;
        place-items: center;
        z-index: 9999;
        padding: 18px
    }

    .bkp-dialog {
        font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
        font-size: 13px;
        line-height: 1.5;
        width: min(100%, 480px);
        max-height: 92vh;
        overflow: auto;
        background: #fff;
        border-radius: 18px;
        padding: 26px;
        box-shadow: 0 24px 80px rgba(0, 0, 0, .25);
        color: #19325e
    }

    .bkp-dialog h2 {
        margin: 0 0 8px
    }

    .bkp-dialog p {
        color: #65748e;
        font-size: 14px
    }

    .bkp-dialog label {
        display: block;
        margin-top: 15px;
        font-size: 13px;
        font-weight: 700
    }

    .bkp-dialog input {
        font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
        font-size: 13px;
        font-weight: 500;
        color: #172033;
        display: block;
        width: 100%;
        margin-top: 6px;
        border: 1px solid #ccd8ec;
        border-radius: 9px;
        padding: 11px;
        box-sizing: border-box
    }

    .bkp-password-wrap {
        position: relative;
        margin-top: 6px;
    }

    .bkp-dialog .bkp-password-wrap input {
        margin-top: 0;
        padding-right: 48px;
    }

    .bkp-show-password {
        position: absolute;
        top: 50%;
        right: 5px;
        transform: translateY(-50%);
        background: transparent;
        border: 0;
        border-radius: 7px;
        color: #435675;
        width: 39px;
        height: 36px;
        cursor: pointer;
        display: grid;
        place-items: center;
    }

    .bkp-show-password:hover,
    .bkp-show-password:focus-visible {
        background: #eaf0fb;
        outline: 2px solid #b9c8e4;
    }

    .bkp-show-password i {
        pointer-events: none;
    }

    .bkp-reset-link {
        font: inherit;
        font-weight: 700;
        font-size: 12px;
        color: #1a3a8c;
        border: 0;
        background: transparent;
        padding: 12px 0 0;
        cursor: pointer;
        text-align: left
    }

    .bkp-reset-link:hover {
        text-decoration: underline
    }

    .bkp-reset-link[hidden] {
        display: none !important
    }

    .bkp-feedback {
        margin-top: 12px;
        font-size: 12px;
        font-weight: 650;
        color: #b42318;
        line-height: 1.5
    }

    .bkp-feedback[hidden] {
        display: none !important
    }

    .bkp-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        margin-top: 22px;
        flex-wrap: wrap
    }
</style>
<div class="bkp-overlay" id="backupOverlay" hidden>
    <section class="bkp-dialog" role="dialog" aria-modal="true" aria-labelledby="backupDialogTitle" tabindex="-1">
        <h2 id="backupDialogTitle">Backup security</h2>
        <p id="backupDialogHelp"></p>
        <form method="post" id="backupDialogForm" action="<?= saEsc(rtrim(authBasePath(), '/') . '/app/super-admin/backup-security-action.php') ?>">
            <input type="hidden" name="csrf_token" value="<?= saEsc(authCsrf()) ?>">
            <input type="hidden" name="action" id="backupAction">
            <div id="backupFormFields"></div>
            <p class="bkp-feedback" id="backupFeedback" role="alert" aria-live="polite" hidden></p>
            <button class="bkp-reset-link" type="button" id="backupResetCodeLink" hidden>Already received a reset code? Enter code</button>
            <div class="bkp-actions"><button class="sa-btn" type="button" id="backupCancel">Cancel</button><button class="sa-btn sa-btn--primary" type="submit" id="backupSubmit">Continue</button></div>
        </form>
    </section>
</div>
<script>
    (() => {
        'use strict';
        const overlay = document.getElementById('backupOverlay');
        const dialog = overlay.querySelector('.bkp-dialog');
        const form = document.getElementById('backupDialogForm');
        const fields = document.getElementById('backupFormFields');
        const action = document.getElementById('backupAction');
        const title = document.getElementById('backupDialogTitle');
        const help = document.getElementById('backupDialogHelp');
        const submit = document.getElementById('backupSubmit');
        const feedback = document.getElementById('backupFeedback');
        const resetCodeLink = document.getElementById('backupResetCodeLink');
        let previous = null;
        let busy = false;
        const definitions = {
            set: ['Set Backup Password', 'Confirm your Super Admin login password to set a separate backup password.', [
                ['account_password', 'Account login password'],
                ['new_password', 'New backup password (12+ characters)'],
                ['confirm_password', 'Confirm backup password']
            ]],
            download: ['Authorize SQL Download', 'Enter your backup password. Each download requires fresh verification.', [
                ['backup_password', 'Backup security password']
            ]],
            change: ['Change Backup Password', 'Enter current and new backup passwords.', [
                ['current_password', 'Current backup password'],
                ['new_password', 'New backup password'],
                ['confirm_password', 'Confirm new password']
            ]],
            forgot: ['Reset Backup Password', 'First verify your Super Admin account password. A reset code will be emailed to your verified email.', [
                ['account_password', 'Account login password']
            ]],
            reset: ['Complete Password Reset', 'Enter the one-time email code and your new backup password.', [
                ['otp', '6-digit email verification code'],
                ['new_password', 'New backup password'],
                ['confirm_password', 'Confirm new password']
            ]]
        };

        function showError(message) {
            feedback.textContent = message;
            feedback.hidden = false;
        }

        function close() {
            if (busy) return;
            overlay.hidden = true;
            fields.replaceChildren();
            feedback.hidden = true;
            previous?.focus();
        }

        function open(mode) {
            const def = definitions[mode];
            if (!def || busy) return;
            previous = document.activeElement;
            title.textContent = def[0];
            help.textContent = def[1];
            fields.replaceChildren();
            feedback.hidden = true;
            resetCodeLink.hidden = mode !== 'forgot';
            action.value = mode === 'forgot' ? 'reset_request' : mode === 'reset' ? 'reset_complete' : mode;
            form.action = mode === 'download' ? window.location.pathname : <?= json_encode(rtrim(authBasePath(), '/') . '/app/super-admin/backup-security-action.php', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
            submit.textContent = mode === 'download' ? 'Verify & Download' : mode === 'forgot' ? 'Send Reset Code' : mode === 'reset' ? 'Reset Password' : 'Save';
            for (const [name, labelText] of def[2]) {
                const label = document.createElement('label');
                label.textContent = labelText;
                const input = document.createElement('input');
                input.name = name;
                input.required = true;
                input.autocomplete = 'off';
                input.type = name === 'otp' ? 'text' : 'password';
                if (name === 'otp') {
                    input.inputMode = 'numeric';
                    input.pattern = '[0-9]{6}';
                    input.maxLength = 6;
                }
                if (input.type === 'password') {
                    const wrap = document.createElement('div');
                    wrap.className = 'bkp-password-wrap';
                    const toggle = document.createElement('button');
                    toggle.type = 'button';
                    toggle.className = 'bkp-show-password';
                    toggle.setAttribute('aria-label', 'Show ' + labelText);
                    toggle.setAttribute('aria-pressed', 'false');
                    toggle.title = 'Show password';
                    toggle.innerHTML = '<i class="fa-solid fa-eye" aria-hidden="true"></i>';
                    toggle.addEventListener('click', () => {
                        const revealed = input.type === 'password';
                        input.type = revealed ? 'text' : 'password';
                        toggle.setAttribute('aria-pressed', String(revealed));
                        toggle.setAttribute('aria-label', (revealed ? 'Hide ' : 'Show ') + labelText);
                        toggle.title = revealed ? 'Hide password' : 'Show password';
                        toggle.querySelector('i').className = revealed ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye';
                        input.focus();
                    });
                    wrap.append(input, toggle);
                    label.append(wrap);
                } else {
                    label.append(input);
                }
                fields.append(label);
            }
            overlay.hidden = false;
            dialog.focus();
            fields.querySelector('input')?.focus();
        }
        // Browser-side warning/restriction is supplemental. Backend password verification is authoritative.
        fields.addEventListener('copy', e => {
            if (e.target.closest('input')) {
                e.preventDefault();
                showError('Copy and paste are disabled in security fields. Please type your password.');
            }
        });
        fields.addEventListener('cut', e => {
            if (e.target.closest('input')) {
                e.preventDefault();
                showError('Copy and paste are disabled in security fields. Please type your password.');
            }
        });
        fields.addEventListener('paste', e => {
            if (e.target.closest('input')) {
                e.preventDefault();
                showError('Copy and paste are disabled in security fields. Please type your password.');
            }
        });
        fields.addEventListener('drop', e => {
            if (e.target.closest('input')) {
                e.preventDefault();
                showError('Drag and drop are disabled in security fields.');
            }
        });
        fields.addEventListener('dragover', e => {
            if (e.target.closest('input')) e.preventDefault();
        });
        fields.addEventListener('contextmenu', e => {
            if (e.target.closest('input')) {
                e.preventDefault();
                showError('Right-click is disabled in security fields.');
            }
        });

        function filenameFromDisposition(header) {
            const extended = header.match(/filename\*=UTF-8''([^;]+)/i);
            if (extended) {
                try {
                    return decodeURIComponent(extended[1].replace(/^"|"$/g, ''));
                } catch (e) {}
            }
            const normal = header.match(/filename="?([^";]+)"?/i);
            return normal ? normal[1].trim() : 'bcp-database-backup.sql';
        }
        form.addEventListener('submit', async e => {
            if (action.value !== 'download') return;
            e.preventDefault();
            if (busy || !form.reportValidity()) return;
            busy = true;
            submit.disabled = true;
            submit.textContent = 'Verifying & Preparing SQL…';
            feedback.hidden = true;
            try {
                // Same-origin route already serving this page. Do not follow an outdated endpoint link.
                const downloadUrl = window.location.pathname;
                const res = await fetch(downloadUrl, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin',
                    cache: 'no-store',
                    redirect: 'follow'
                });
                const mime = (res.headers.get('content-type') || '').toLowerCase();
                const disposition = res.headers.get('content-disposition') || '';
                if (!res.ok || !mime.includes('application/sql') || !/attachment/i.test(disposition)) {
                    // On account-wide lockout, follow the server's login redirect so the live countdown appears.
                    if (res.redirected && new URL(res.url, location.href).pathname.endsWith('/super-admin/login.php')) {
                        location.assign(res.url);
                        return;
                    }
                    if (mime.includes('application/json')) {
                        const issue = await res.json();
                        throw new Error(issue.message || 'Backup authorization failed.');
                    }
                    const responseText = await res.text();
                    if (res.redirected) {
                        throw new Error('Your session was redirected. Please sign in again and reopen Database Backup.');
                    }
                    if (responseText.length < 350 && !responseText.includes('<')) {
                        throw new Error(responseText.trim() || `Backup request failed (HTTP ${res.status}).`);
                    }
                    throw new Error(`Backup request failed (HTTP ${res.status}) at ${downloadUrl}. The server returned ${mime || 'unknown content'} instead of SQL. Check the file location and Apache logs. Do not retry your password yet.`);
                }
                const blob = await res.blob();
                if (!blob.size) throw new Error('The server returned an empty backup. Please retry.');
                const name = filenameFromDisposition(disposition);
                const blobUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = name;
                document.body.appendChild(a);
                a.click();
                a.remove();
                // The modal closes only after receiving a valid SQL response and initiating browser download.
                busy = false;
                close();
                setTimeout(() => URL.revokeObjectURL(blobUrl), 60000);
            } catch (err) {
                showError(err?.message || 'The download failed. Please try again.');
            } finally {
                busy = false;
                submit.disabled = false;
                submit.textContent = 'Verify & Download';
            }
        });
        document.querySelectorAll('[data-backup-open]').forEach(el => el.addEventListener('click', () => open(el.dataset.backupOpen)));
        document.getElementById('backupCancel').addEventListener('click', close);
        overlay.addEventListener('click', e => {
            if (e.target === overlay) close();
        });
        document.addEventListener('keydown', e => {
            if (overlay.hidden) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
            }
            if (e.key === 'Tab') {
                const tabs = [...dialog.querySelectorAll('input:not([type=hidden]),button:not([hidden]):not([disabled])')];
                if (e.shiftKey && document.activeElement === tabs[0]) {
                    e.preventDefault();
                    tabs.at(-1).focus();
                } else if (!e.shiftKey && document.activeElement === tabs.at(-1)) {
                    e.preventDefault();
                    tabs[0].focus();
                }
            }
        });
        resetCodeLink.addEventListener('click', () => open('reset'));
    })();
</script>
<?php saPageEnd(); ?>