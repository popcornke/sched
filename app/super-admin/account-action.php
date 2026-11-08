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

$db = authDb();
$action = strtolower(trim((string) ($_POST['action'] ?? '')));
$currentUserId = (int) ($_SESSION['auth_user_id'] ?? 0);
$roles = ['SUPER_ADMIN', 'ADMIN', 'SCHEDULER', 'ACADEMIC_OFFICE', 'STAFF', 'TEACHER'];
$hasEmail = saColumnExists($db, 'auth_users', 'email');
$hasVerified = saColumnExists($db, 'auth_users', 'email_verified_at');
$hasPasswordChanged = saColumnExists($db, 'auth_users', 'password_changed_at');

try {
    if ($action === 'create') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = strtoupper(trim((string) ($_POST['role'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if (!preg_match('/^[a-zA-Z0-9_.-]{4,80}$/', $username)) {
            throw new RuntimeException('Username must be 4–80 characters and use letters, numbers, dot, underscore, or hyphen.');
        }
        if (!in_array($role, $roles, true)) {
            throw new RuntimeException('Invalid account role.');
        }
        if (strlen($password) < 12 || strlen($password) > 128) {
            throw new RuntimeException('Password must be 12–128 characters.');
        }
        if ($hasEmail && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid email address.');
        }

        $fields = ['username', 'password_hash', 'role', 'is_active', 'failed_attempts', 'locked_until'];
        $values = [':username', ':password', ':role', '1', '0', 'NULL'];
        $params = ['username' => $username, 'password' => password_hash($password, PASSWORD_DEFAULT), 'role' => $role];
        if ($hasEmail) {
            $fields[] = 'email';
            $values[] = ':email';
            $params['email'] = $email !== '' ? $email : null;
        }
        if ($hasVerified) {
            $fields[] = 'email_verified_at';
            $values[] = $email !== '' ? 'NOW()' : 'NULL';
        }
        if ($hasPasswordChanged) {
            $fields[] = 'password_changed_at';
            $values[] = 'NOW()';
        }

        $stmt = $db->prepare('INSERT INTO auth_users (' . implode(', ', $fields) . ') VALUES (' . implode(', ', $values) . ')');
        $stmt->execute($params);
        $newId = (int) $db->lastInsertId();
        auditLog($db, 'ACCOUNT_CREATED', 'ACCOUNT_MANAGEMENT', 'AUTH_USER', (string) $newId, 'Created account ' . $username . ' with role ' . $role . '.', ['role' => $role]);
        saFlash('success', 'Account created successfully.');
    } elseif ($action === 'update') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $username = trim((string) ($_POST['username'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $role = strtoupper(trim((string) ($_POST['role'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        $existing = saQueryOne($db, 'SELECT user_id, username, role FROM auth_users WHERE user_id = :id', ['id' => $userId]);
        if (!$existing) throw new RuntimeException('Account not found.');
        if (!preg_match('/^[a-zA-Z0-9_.-]{4,80}$/', $username)) throw new RuntimeException('Invalid username format.');
        if (!in_array($role, $roles, true)) throw new RuntimeException('Invalid role.');
        if ($userId === $currentUserId) $role = 'SUPER_ADMIN';
        if ($hasEmail && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Invalid email address.');
        if ($password !== '' && (strlen($password) < 12 || strlen($password) > 128)) throw new RuntimeException('New password must be 12–128 characters.');

        $sets = ['username = :username', 'role = :role'];
        $params = ['id' => $userId, 'username' => $username, 'role' => $role];
        if ($hasEmail) {
            $sets[] = 'email = :email';
            $params['email'] = $email !== '' ? $email : null;
            if ($hasVerified) {
                $sets[] = $email !== '' ? 'email_verified_at = NOW()' : 'email_verified_at = NULL';
            }
        }
        if ($password !== '') {
            $sets[] = 'password_hash = :password_hash';
            $sets[] = 'failed_attempts = 0';
            $sets[] = 'locked_until = NULL';
            $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            if ($hasPasswordChanged) $sets[] = 'password_changed_at = NOW()';
        }

        $stmt = $db->prepare('UPDATE auth_users SET ' . implode(', ', $sets) . ' WHERE user_id = :id');
        $stmt->execute($params);
        if ($userId === $currentUserId) $_SESSION['auth_username'] = $username;
        auditLog($db, 'ACCOUNT_UPDATED', 'ACCOUNT_MANAGEMENT', 'AUTH_USER', (string) $userId, 'Updated account ' . $username . '.', ['role' => $role, 'password_reset' => $password !== '']);
        saFlash('success', 'Account updated successfully.');
    } elseif ($action === 'toggle') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0 || $userId === $currentUserId) throw new RuntimeException('You cannot deactivate the account currently in use.');
        $account = saQueryOne($db, 'SELECT user_id, username, role, is_active FROM auth_users WHERE user_id = :id', ['id' => $userId]);
        if (!$account) throw new RuntimeException('Account not found.');
        $newState = (int) $account['is_active'] === 1 ? 0 : 1;
        if ($account['role'] === 'SUPER_ADMIN' && $newState === 0) {
            $activeSupers = saCount($db, "SELECT COUNT(*) FROM auth_users WHERE role = 'SUPER_ADMIN' AND is_active = 1");
            if ($activeSupers <= 1) throw new RuntimeException('The last active Super Administrator cannot be deactivated.');
        }
        $stmt = $db->prepare('UPDATE auth_users SET is_active = :state, failed_attempts = 0, locked_until = NULL WHERE user_id = :id');
        $stmt->execute(['state' => $newState, 'id' => $userId]);
        auditLog($db, $newState ? 'ACCOUNT_ACTIVATED' : 'ACCOUNT_DEACTIVATED', 'ACCOUNT_MANAGEMENT', 'AUTH_USER', (string) $userId, ($newState ? 'Activated ' : 'Deactivated ') . (string) $account['username'] . '.');
        saFlash('success', $newState ? 'Account activated.' : 'Account deactivated.');
    } elseif ($action === 'delete') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        if ($userId <= 0 || $userId === $currentUserId) throw new RuntimeException('You cannot delete the account currently in use.');
        $account = saQueryOne($db, 'SELECT user_id, username, role, is_active FROM auth_users WHERE user_id = :id', ['id' => $userId]);
        if (!$account) throw new RuntimeException('Account not found.');
        if ($account['role'] === 'SUPER_ADMIN') {
            $superCount = saCount($db, "SELECT COUNT(*) FROM auth_users WHERE role = 'SUPER_ADMIN'");
            if ($superCount <= 1) throw new RuntimeException('The last Super Administrator account cannot be deleted.');
        }
        auditLog($db, 'ACCOUNT_DELETE_REQUESTED', 'ACCOUNT_MANAGEMENT', 'AUTH_USER', (string) $userId, 'Permanent deletion requested for ' . (string) $account['username'] . '.');
        $stmt = $db->prepare('DELETE FROM auth_users WHERE user_id = :id');
        $stmt->execute(['id' => $userId]);
        auditLog($db, 'ACCOUNT_DELETED', 'ACCOUNT_MANAGEMENT', 'AUTH_USER', (string) $userId, 'Deleted unused account ' . (string) $account['username'] . '.', ['deleted_role' => $account['role']]);
        saFlash('success', 'Unused account deleted permanently.');
    } else {
        throw new RuntimeException('Unknown account action.');
    }
} catch (PDOException $e) {
    error_log('BCP account management DB error: ' . $e->getMessage());
    if ($e->getCode() === '23000') {
        saFlash('error', str_contains(strtolower($e->getMessage()), 'duplicate')
            ? 'Username or account value already exists.'
            : 'This account has linked system history and cannot be deleted. Deactivate it instead.');
    } else {
        saFlash('error', 'Database operation failed.');
    }
} catch (Throwable $e) {
    saFlash('error', $e->getMessage());
}

header('Location: accounts.php', true, 303);
exit;
