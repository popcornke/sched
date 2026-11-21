<?php
declare(strict_types=1);
require_once __DIR__ . '/shared.php';

/** Store server-generated backup security notifications for the current Super Admin only. */
function backupNotify(PDO $db, string $title, string $message, string $type = 'info'): void {
    $uid = backupUserId();
    if ($uid <= 0) return;
    try {
        require_once dirname(__DIR__) . '/shared/notifications.php';
        notificationCreate($db, $uid, $title, $message, $type,
            authBasePath() . '/app/super-admin/backup.php');
    } catch (Throwable $e) {
        // An unavailable notifications table must not bypass or break backup security.
        error_log('Super Admin backup notification: ' . $e->getMessage());
    }
}

function backupUserId(): int { return (int) ($_SESSION['auth_user_id'] ?? 0); }
function backupAccount(PDO $db): ?array {
    return saQueryOne($db, "SELECT user_id, username, email, email_verified_at, password_hash, is_active FROM auth_users WHERE user_id = :id AND role = 'SUPER_ADMIN'", ['id' => backupUserId()]);
}
function backupSecurityRow(PDO $db): ?array {
    return saQueryOne($db, 'SELECT user_id,password_hash,failed_attempts,locked_until FROM super_admin_backup_security WHERE user_id = :id', ['id' => backupUserId()]);
}
function backupStrongPassword(string $p): bool {
    return strlen($p) >= 12 && strlen($p) <= 128 && preg_match('/[A-Za-z]/', $p) && preg_match('/[0-9]/', $p);
}
function backupIsLocked(array $row): bool { return (int)($row['is_locked'] ?? 0) === 1; }
/** Atomic account-wide verification across browsers/computers. */
function backupPasswordVerify(PDO $db, string $password, string $purpose): bool {
    $id = backupUserId();
    $locked = false;
    $triggered = false;
    $accepted = false;
    $attempts = 0;
    $db->beginTransaction();
    try {
        // Lock identity first, then credential row; serialize concurrent attempts.
        $u = $db->prepare("SELECT locked_until, (locked_until > NOW()) AS is_locked FROM auth_users WHERE user_id=:id AND role='SUPER_ADMIN' FOR UPDATE");
        $u->execute(['id'=>$id]);
        $identity = $u->fetch(PDO::FETCH_ASSOC);
        $q = $db->prepare('SELECT password_hash,failed_attempts,locked_until,(locked_until > NOW()) AS is_locked,(locked_until <= NOW()) AS lock_expired FROM super_admin_backup_security WHERE user_id=:id FOR UPDATE');
        $q->execute(['id'=>$id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$identity || !$row) { $db->rollBack(); return false; }
        $locked = (int)($identity['is_locked'] ?? 0) === 1 || backupIsLocked($row);
        if (!$locked) {
            if (password_verify($password, (string)$row['password_hash'])) {
                $db->prepare('UPDATE super_admin_backup_security SET failed_attempts=0,locked_until=NULL WHERE user_id=:id')->execute(['id'=>$id]);
                $accepted = true;
            } else {
                // A completed previous cooldown starts a fresh three-attempt window.
                $previousCooldownFinished = !empty($row['locked_until'])
                    && (int)($row['lock_expired'] ?? 0) === 1;
                $attempts = ($previousCooldownFinished ? 0 : (int)$row['failed_attempts']) + 1;
                $triggered = $attempts >= 3;
                if ($triggered) {
                    // The login and every authenticated page also check auth_users.locked_until.
                    $db->prepare('UPDATE auth_users SET locked_until=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE user_id=:id')->execute(['id'=>$id]);
                    $db->prepare('UPDATE super_admin_backup_security SET failed_attempts=:tries,locked_until=DATE_ADD(NOW(),INTERVAL 15 MINUTE) WHERE user_id=:id')->execute(['tries'=>$attempts,'id'=>$id]);
                } else {
                    $db->prepare('UPDATE super_admin_backup_security SET failed_attempts=:tries,locked_until=NULL WHERE user_id=:id')->execute(['tries'=>$attempts,'id'=>$id]);
                }
            }
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    if ($accepted) return true;
    if ($locked) {
        backupNotify($db, 'Backup access blocked', 'A backup password verification was blocked while your Super Admin account is locked.', 'warning');
        auditLog($db, 'BACKUP_ACCESS_LOCKED', 'DATABASE_BACKUP', 'AUTH_USER', (string)$id, 'Access denied during account lockout.', ['purpose'=>$purpose]);
    } else {
        backupNotify($db, 'Incorrect backup password', 'An incorrect backup password was entered. Attempt ' . $attempts . ' of 3.', 'warning');
        auditLog($db, 'BACKUP_PASSWORD_FAILED', 'DATABASE_BACKUP', 'AUTH_USER', (string)$id, 'Incorrect backup password.', ['purpose'=>$purpose, 'attempts'=>$attempts]);
    }
    if ($triggered) {
        backupNotify($db, 'Super Admin security lockout', 'Three incorrect backup password attempts triggered a 15-minute account lockout and forced logout.', 'error');
        auditLog($db, 'BACKUP_SECURITY_LOCKOUT', 'DATABASE_BACKUP', 'AUTH_USER', (string)$id, 'Three incorrect backup passwords; 15-minute account lockout.');
        auditLog($db, 'FORCED_LOGOUT', 'AUTHENTICATION', 'AUTH_USER', (string)$id, 'Backup security lockout forced session termination.');
        try {
            $account = backupAccount($db);
            if ($account && !empty($account['email_verified_at']) && filter_var((string)$account['email'], FILTER_VALIDATE_EMAIL)) {
                require_once dirname(__DIR__) . '/shared/mailer.php';
                bcpMailerSend((string)$account['email'], 'BCP Super Admin Backup Security Lockout',
                    '<p>Three incorrect database backup password attempts were detected. Your Super Admin account has been locked for 15 minutes and your current session terminated.</p><p>If this was not you, contact your system administrator.</p>', null, 'password_reset');
            }
        } catch (Throwable $mailError) { error_log('Backup lockout notification failed: ' . $mailError->getMessage()); }
        $lockoutQuery = $db->prepare('SELECT UNIX_TIMESTAMP(locked_until) FROM auth_users WHERE user_id=:id');
        $lockoutQuery->execute(['id'=>$id]);
        $lockoutEpoch = (int) $lockoutQuery->fetchColumn();
        authClear();
        header('Location: ' . authBasePath() . '/app/super-admin/login.php?backup_locked=1&until=' . $lockoutEpoch, true, 303);
        exit;
    }
    return false;
}

function backupSetPassword(PDO $db, string $password, string $event): void {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $db->prepare('INSERT INTO super_admin_backup_security(user_id,password_hash,failed_attempts,locked_until,updated_at) VALUES(:id,:hash,0,NULL,NOW()) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),failed_attempts=0,locked_until=NULL,updated_at=NOW()')
       ->execute(['id'=>backupUserId(),'hash'=>$hash]);
    auditLog($db, $event, 'DATABASE_BACKUP', 'AUTH_USER', (string)backupUserId(), 'Super Admin backup security credential updated.');
    $eventLabels = ['BACKUP_PASSWORD_SET' => 'Backup password configured', 'BACKUP_PASSWORD_CHANGED' => 'Backup password changed', 'BACKUP_PASSWORD_RESET' => 'Backup password reset'];
    backupNotify($db, $eventLabels[$event] ?? 'Backup security updated', 'Your Super Admin backup security password was updated.', 'success');
}
function backupRedirect(string $kind, string $msg): never {
    saFlash($kind, $msg);
    header('Location: backup.php', true, 303);
    exit;
}
