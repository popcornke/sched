<?php

declare(strict_types=1);
require_once __DIR__ . '/backup-security-common.php';
require_once dirname(__DIR__) . '/shared/mailer.php';
saRequire();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('POST only');
}
if (!authCsrfValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid CSRF');
}
$db = authDb();
$user = backupAccount($db);
if (!$user || (int)$user['is_active'] !== 1) {
    http_response_code(403);
    exit('Not authorized');
}
$lockStmt = $db->prepare('SELECT GREATEST(0,TIMESTAMPDIFF(SECOND,NOW(),locked_until)) AS remaining, UNIX_TIMESTAMP(locked_until) AS deadline FROM auth_users WHERE user_id=:id');
$lockStmt->execute(['id' => backupUserId()]);
$lockInfo = $lockStmt->fetch(PDO::FETCH_ASSOC) ?: [];
if ((int)($lockInfo['remaining'] ?? 0) > 0) {
    authClear();
    header('Location: login.php?backup_locked=1&until=' . (int)($lockInfo['deadline'] ?? 0), true, 303);
    exit;
}
$action = (string)($_POST['action'] ?? '');
$pwd = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');
try {
    if ($action === 'set') {
        if (backupSecurityRow($db)) backupRedirect('error', 'Backup password is already configured. Use Change or Forgot Password.');
        if (!password_verify((string)($_POST['account_password'] ?? ''), (string)$user['password_hash'])) backupRedirect('error', 'Account password verification failed.');
        if (!backupStrongPassword($pwd) || $pwd !== $confirm) backupRedirect('error', 'Use 12–128 characters including letters and numbers; both entries must match.');
        backupSetPassword($db, $pwd, 'BACKUP_PASSWORD_SET');
        backupRedirect('success', 'Backup password configured successfully.');
    }
    if ($action === 'change') {
        if (!backupSecurityRow($db)) backupRedirect('error', 'Set a backup password first.');
        if (!backupPasswordVerify($db, (string)($_POST['current_password'] ?? ''), 'change')) backupRedirect('error', 'Incorrect password or temporarily locked.');
        if (!backupStrongPassword($pwd) || $pwd !== $confirm) backupRedirect('error', 'New password must be 12–128 characters with letters and numbers, and must match.');
        backupSetPassword($db, $pwd, 'BACKUP_PASSWORD_CHANGED');
        backupRedirect('success', 'Backup password changed.');
    }
    if ($action === 'reset_request') {
        if (!backupSecurityRow($db)) backupRedirect('error', 'No backup password exists yet.');
        if (!password_verify((string)($_POST['account_password'] ?? ''), (string)$user['password_hash'])) {
            auditLog($db, 'BACKUP_RESET_AUTH_FAILED', 'DATABASE_BACKUP', 'AUTH_USER', (string)backupUserId(), 'Backup reset account-password check failed.');
            backupNotify($db, 'Backup reset denied', 'A backup password reset request failed account password verification.', 'warning');
            backupRedirect('error', 'Account password verification failed.');
        }
        if (empty($user['email_verified_at']) || !filter_var((string)$user['email'], FILTER_VALIDATE_EMAIL)) backupRedirect('error', 'A verified Super Admin email is required.');
        $prev = saQueryOne($db, 'SELECT issued_at FROM super_admin_backup_reset_otps WHERE user_id=:id', ['id' => backupUserId()]);
        if ($prev && strtotime((string)$prev['issued_at']) > time() - 60) backupRedirect('error', 'Wait at least 60 seconds before requesting another code.');
        $code = (string)random_int(100000, 999999);
        $hash = hash_hmac('sha256', backupUserId() . '|backup-reset|' . $code, (string)$user['password_hash']);
        $db->prepare('INSERT INTO super_admin_backup_reset_otps(user_id,code_hash,expires_at,attempts,issued_at) VALUES(:id,:hash,DATE_ADD(NOW(),INTERVAL 5 MINUTE),0,NOW()) ON DUPLICATE KEY UPDATE code_hash=VALUES(code_hash),expires_at=VALUES(expires_at),attempts=0,issued_at=NOW()')->execute(['id' => backupUserId(), 'hash' => $hash]);
        // Send only to the verified email of the currently authenticated account.
        $html = '<p>BCP Scheduling System — Backup Security Password reset</p><p>Your verification code is <strong>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</strong>.</p><p>Expires in 5 minutes. Never share this code.</p>';
        if (!bcpMailerSend((string)$user['email'], 'BCP Backup Password Reset Code', $html, null, 'password_reset')) {
            $db->prepare('DELETE FROM super_admin_backup_reset_otps WHERE user_id=:id')->execute(['id' => backupUserId()]);
            backupRedirect('error', 'Unable to send reset code. Check email service configuration.');
        }
        auditLog($db, 'BACKUP_RESET_OTP_SENT', 'DATABASE_BACKUP', 'AUTH_USER', (string)backupUserId(), 'Backup password reset OTP issued to verified Super Admin email.');
        backupNotify($db, 'Backup password reset requested', 'A backup password reset verification code was requested for your account.', 'info');
        backupRedirect('success', 'A reset code was sent to your verified Super Admin email. It expires in 5 minutes.');
    }
    if ($action === 'reset_complete') {
        $otp = (string)($_POST['otp'] ?? '');
        $row = saQueryOne($db, 'SELECT * FROM super_admin_backup_reset_otps WHERE user_id=:id', ['id' => backupUserId()]);
        if (!$row || strtotime((string)$row['expires_at']) <= time() || (int)$row['attempts'] >= 3) backupRedirect('error', 'Reset code expired or locked. Request a new code.');
        if (!preg_match('/^\d{6}$/D', $otp) || !hash_equals((string)$row['code_hash'], hash_hmac('sha256', backupUserId() . '|backup-reset|' . $otp, (string)$user['password_hash']))) {
            $db->prepare('UPDATE super_admin_backup_reset_otps SET attempts=attempts+1 WHERE user_id=:id')->execute(['id' => backupUserId()]);
            auditLog($db, 'BACKUP_RESET_OTP_FAILED', 'DATABASE_BACKUP', 'AUTH_USER', (string)backupUserId(), 'Incorrect backup reset OTP.');
            backupNotify($db, 'Incorrect backup reset code', 'An invalid backup password reset verification code was submitted.', 'warning');
            backupRedirect('error', 'Invalid code. Maximum 3 attempts.');
        }
        if (!backupStrongPassword($pwd) || $pwd !== $confirm) backupRedirect('error', 'New password must be 12–128 characters with letters and numbers, and must match.');
        backupSetPassword($db, $pwd, 'BACKUP_PASSWORD_RESET');
        $db->prepare('DELETE FROM super_admin_backup_reset_otps WHERE user_id=:id')->execute(['id' => backupUserId()]);
        backupRedirect('success', 'Backup password reset successfully.');
    }
    backupRedirect('error', 'Invalid action.');
} catch (Throwable $e) {
    error_log('BCP backup security: ' . $e->getMessage());
    backupRedirect('error', 'Backup security operation failed. Verify migration 031 is installed.');
}
