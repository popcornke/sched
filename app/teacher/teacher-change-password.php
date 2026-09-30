<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
authRequire(false, ['TEACHER']);
authNoCache();

function tcpEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tcpStrongEnough(string $password): bool
{
    if (strlen($password) < 12 || strlen($password) > 128) {
        return false;
    }
    return preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

$error = '';
$success = '';
$userId = (int)($_SESSION['auth_user_id'] ?? 0);

try {
    $db = authDb();
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $stmt = $db->prepare(
        "SELECT
            au.user_id,
            au.username,
            au.password_hash,
            au.is_active,
            ta.teacher_id,
            ta.must_change_password,
            ta.password_changed_at,
            t.employee_no,
            t.teacher_name,
            t.status AS teacher_status
         FROM auth_users au
         JOIN teacher_accounts ta ON ta.user_id = au.user_id
         JOIN teachers t ON t.teacher_id = ta.teacher_id
         WHERE au.user_id = :user_id
           AND au.role = 'TEACHER'
         LIMIT 1"
    );
    $stmt->execute(['user_id' => $userId]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account || (int)$account['is_active'] !== 1 || strtoupper((string)$account['teacher_status']) !== 'ACTIVE') {
        http_response_code(403);
        exit('This teacher account is not available.');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');

        if (!authCsrfValid($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            $error = 'Your session expired. Refresh and try again.';
        } elseif (!password_verify($current, (string)$account['password_hash'])) {
            $error = 'Your current password is incorrect.';
        } elseif ($new !== $confirm) {
            $error = 'The new password and confirmation do not match.';
        } elseif (!tcpStrongEnough($new)) {
            $error = 'Use at least 12 characters with an uppercase letter, lowercase letter, and number.';
        } elseif (hash_equals('Teacher1234567', $new)) {
            $error = 'Choose a new password instead of the assigned default password.';
        } elseif (strcasecmp($new, (string)$account['username']) === 0 || strcasecmp($new, (string)$account['employee_no']) === 0) {
            $error = 'Your password cannot be the same as your employee number.';
        } elseif (password_verify($new, (string)$account['password_hash'])) {
            $error = 'Your new password must be different from your current password.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            if (!is_string($hash) || $hash === '') {
                throw new RuntimeException('Password hashing failed.');
            }

            $db->beginTransaction();
            try {
                $updateUser = $db->prepare(
                    "UPDATE auth_users
                     SET password_hash = :password_hash,
                         failed_attempts = 0,
                         locked_until = NULL
                     WHERE user_id = :user_id
                       AND role = 'TEACHER'"
                );
                $updateUser->execute([
                    'password_hash' => $hash,
                    'user_id' => $userId,
                ]);

                $updateTeacher = $db->prepare(
                    "UPDATE teacher_accounts
                     SET must_change_password = 0,
                         password_changed_at = NOW()
                     WHERE user_id = :user_id"
                );
                $updateTeacher->execute(['user_id' => $userId]);

                $db->commit();
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $e;
            }

            $_SESSION['teacher_must_change_password'] = 0;
            $_SESSION['auth_last_activity'] = time();
            session_regenerate_id(true);

            header('Location: teacher-dashboard.php?password_changed=1', true, 303);
            exit;
        }
    }
} catch (Throwable $e) {
    error_log('BCP Teacher password change error: ' . $e->getMessage());
    if ($error === '') {
        $error = 'Unable to update your password right now. Please try again.';
    }
}

$mustChange = isset($account) && (int)$account['must_change_password'] === 1;
$csrf = authCsrf();
$username = isset($account) ? (string)$account['employee_no'] : (string)($_SESSION['auth_username'] ?? 'Teacher');
$teacherName = isset($account) ? (string)$account['teacher_name'] : (string)($_SESSION['teacher_name'] ?? 'Teacher');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Change Password | BCP Teacher Portal</title>
    <link rel="icon" href="../assets/images/BCP_LOGO.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/teacher-password.css">
</head>
<body class="teacher-password-page">
<main class="teacher-password-shell">
    <section class="teacher-password-card" aria-labelledby="teacherPasswordTitle">
        <a class="teacher-password-brand" href="<?= $mustChange ? './teacher-change-password.php' : './teacher-dashboard.php' ?>">
            <img src="../assets/images/BCP_LOGO.png" alt="BCP Logo">
            <span><strong>BCP</strong><small>Teacher Portal</small></span>
        </a>

        <div class="teacher-password-icon"><i class="fa-solid fa-key" aria-hidden="true"></i></div>
        <p class="teacher-password-kicker"><?= $mustChange ? 'FIRST SIGN IN · REQUIRED' : 'ACCOUNT SECURITY' ?></p>
        <h1 id="teacherPasswordTitle"><?= $mustChange ? 'Create your own password.' : 'Change your password.' ?></h1>
        <p class="teacher-password-lead">
            <?= $mustChange
                ? 'Your account is still using the assigned default password. Change it before opening your teaching schedule.'
                : 'Update your Teacher Portal password. Your current password is required to confirm the change.' ?>
        </p>

        <div class="teacher-password-profile">
            <span class="teacher-password-avatar"><?= tcpEsc(strtoupper(substr($teacherName !== '' ? $teacherName : 'T', 0, 1))) ?></span>
            <div><strong><?= tcpEsc($teacherName) ?></strong><span><?= tcpEsc($username) ?></span></div>
        </div>

        <?php if ($error !== ''): ?>
            <div class="teacher-password-message is-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><span><?= tcpEsc($error) ?></span></div>
        <?php endif; ?>

        <form method="post" class="teacher-password-form" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= tcpEsc($csrf) ?>">

            <label>Current password
                <div class="teacher-password-input"><i class="fa-solid fa-lock"></i><input id="currentPassword" name="current_password" type="password" autocomplete="current-password" maxlength="128" required><button type="button" data-toggle="currentPassword" aria-label="Show current password"><i class="fa-regular fa-eye"></i></button></div>
            </label>

            <label>New password
                <div class="teacher-password-input"><i class="fa-solid fa-shield-halved"></i><input id="newPassword" name="new_password" type="password" autocomplete="new-password" maxlength="128" required><button type="button" data-toggle="newPassword" aria-label="Show new password"><i class="fa-regular fa-eye"></i></button></div>
            </label>

            <label>Confirm new password
                <div class="teacher-password-input"><i class="fa-solid fa-check"></i><input id="confirmPassword" name="confirm_password" type="password" autocomplete="new-password" maxlength="128" required><button type="button" data-toggle="confirmPassword" aria-label="Show password confirmation"><i class="fa-regular fa-eye"></i></button></div>
            </label>

            <div class="teacher-password-rules" aria-label="Password requirements">
                <strong>Password requirements</strong>
                <span id="ruleLength"><i class="fa-regular fa-circle"></i> 12–128 characters</span>
                <span id="ruleUpper"><i class="fa-regular fa-circle"></i> At least one uppercase letter</span>
                <span id="ruleLower"><i class="fa-regular fa-circle"></i> At least one lowercase letter</span>
                <span id="ruleNumber"><i class="fa-regular fa-circle"></i> At least one number</span>
                <span id="ruleMatch"><i class="fa-regular fa-circle"></i> Confirmation matches</span>
            </div>

            <button class="teacher-password-submit" type="submit"><i class="fa-solid fa-key"></i><span>Save new password</span></button>
        </form>

        <?php if (!$mustChange): ?>
            <a class="teacher-password-back" href="./teacher-dashboard.php"><i class="fa-solid fa-arrow-left"></i> Back to my schedule</a>
        <?php endif; ?>

        <form method="post" action="./teacher-logout.php" class="teacher-password-logout">
            <input type="hidden" name="csrf_token" value="<?= tcpEsc($csrf) ?>">
            <button type="submit">Sign out</button>
        </form>
    </section>
</main>
<script>
(() => {
    'use strict';
    document.querySelectorAll('[data-toggle]').forEach(button => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.dataset.toggle);
            if (!input) return;
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            const icon = button.querySelector('i');
            if (icon) icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            input.focus();
        });
    });

    const next = document.getElementById('newPassword');
    const confirm = document.getElementById('confirmPassword');
    const setRule = (id, ok) => {
        const row = document.getElementById(id);
        if (!row) return;
        row.classList.toggle('is-ok', ok);
        const icon = row.querySelector('i');
        if (icon) icon.className = ok ? 'fa-solid fa-circle-check' : 'fa-regular fa-circle';
    };
    const validate = () => {
        const value = next?.value || '';
        setRule('ruleLength', value.length >= 12 && value.length <= 128);
        setRule('ruleUpper', /[A-Z]/.test(value));
        setRule('ruleLower', /[a-z]/.test(value));
        setRule('ruleNumber', /[0-9]/.test(value));
        setRule('ruleMatch', value.length > 0 && value === (confirm?.value || ''));
    };
    next?.addEventListener('input', validate);
    confirm?.addEventListener('input', validate);
    document.getElementById('currentPassword')?.focus();
})();
</script>
</body>
</html>
