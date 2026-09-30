<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';

authStart();
authNoCache();

function teacherLoginEsc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function teacherLoginRedirectForExistingSession(): never
{
    $role = strtoupper((string)($_SESSION['auth_role'] ?? ''));

    if ($role === 'TEACHER') {
        try {
            $db = authDb();
            $stmt = $db->prepare(
                "SELECT ta.must_change_password
                   FROM teacher_accounts ta
                   JOIN auth_users au ON au.user_id = ta.user_id
                  WHERE ta.user_id = :user_id
                    AND au.role = 'TEACHER'
                  LIMIT 1"
            );
            $stmt->execute(['user_id' => (int)($_SESSION['auth_user_id'] ?? 0)]);
            $state = $stmt->fetch(PDO::FETCH_ASSOC);
            $mustChange = $state && (int)$state['must_change_password'] === 1;
            $_SESSION['teacher_must_change_password'] = $mustChange ? 1 : 0;
            header('Location: ' . ($mustChange ? 'teacher-change-password.php' : 'teacher-dashboard.php'), true, 303);
            exit;
        } catch (Throwable $e) {
            error_log('BCP Teacher password-state check failed: ' . $e->getMessage());
            header('Location: teacher-dashboard.php', true, 303);
            exit;
        }
    }

    // Never mix Admin/Scheduler and Teacher workspaces.
    header('Location: ../dashboard/dashboard.php', true, 303);
    exit;
}

if (authLoggedIn()) {
    teacherLoginRedirectForExistingSession();
}

$error = '';
$usernameValue = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $usernameValue = strtoupper(trim((string)($_POST['username'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $csrf = $_POST['csrf_token'] ?? null;

    if (!authCsrfValid($csrf)) {
        http_response_code(403);
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (
        $usernameValue === '' ||
        $password === '' ||
        strlen($usernameValue) > 80 ||
        strlen($password) > 1024
    ) {
        $error = 'Enter a valid employee number and password.';
    } else {
        try {
            $db = authDb();
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Teacher portal accepts TEACHER accounts only and verifies the
            // account-to-teacher mapping before creating a login session.
            $stmt = $db->prepare(
                "SELECT
                    au.user_id,
                    au.username,
                    au.password_hash,
                    au.role,
                    au.is_active,
                    au.failed_attempts,
                    (au.locked_until > NOW()) AS is_locked,
                    t.teacher_id,
                    t.employee_no,
                    t.teacher_name,
                    t.status AS teacher_status,
                    ta.must_change_password,
                    ta.password_changed_at
                 FROM auth_users au
                 INNER JOIN teacher_accounts ta ON ta.user_id = au.user_id
                 INNER JOIN teachers t ON t.teacher_id = ta.teacher_id
                 WHERE au.username = :username
                   AND au.role = 'TEACHER'
                 LIMIT 1"
            );
            $stmt->execute(['username' => $usernameValue]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $valid = $user
                && (int)$user['is_active'] === 1
                && strtoupper((string)$user['teacher_status']) === 'ACTIVE'
                && (int)$user['is_locked'] !== 1
                && password_verify($password, (string)$user['password_hash']);

            if (!$valid) {
                if ($user && (int)$user['is_locked'] !== 1) {
                    $failed = $db->prepare(
                        "UPDATE auth_users
                         SET
                            locked_until = CASE
                                WHEN failed_attempts + 1 >= 5
                                THEN DATE_ADD(NOW(), INTERVAL 15 MINUTE)
                                ELSE locked_until
                            END,
                            failed_attempts = CASE
                                WHEN failed_attempts + 1 >= 5
                                THEN 0
                                ELSE failed_attempts + 1
                            END
                         WHERE user_id = :id
                           AND role = 'TEACHER'"
                    );
                    $failed->execute(['id' => (int)$user['user_id']]);
                }

                // Generic response avoids exposing whether a teacher account exists.
                $error = 'Invalid employee number or password, or the account is temporarily locked.';
            } else {
                $reset = $db->prepare(
                    "UPDATE auth_users
                     SET failed_attempts = 0,
                         locked_until = NULL
                     WHERE user_id = :id
                       AND role = 'TEACHER'"
                );
                $reset->execute(['id' => (int)$user['user_id']]);

                session_regenerate_id(true);
                $_SESSION = [];
                $_SESSION['auth_user_id'] = (int)$user['user_id'];
                $_SESSION['auth_username'] = (string)$user['username'];
                $_SESSION['auth_role'] = 'TEACHER';
                $_SESSION['auth_login_time'] = time();
                $_SESSION['auth_last_activity'] = time();
                $_SESSION['auth_regenerated_at'] = time();

                // Convenience values only. Authorization still comes from
                // auth_user_id -> teacher_accounts -> teacher_id in the API.
                $_SESSION['teacher_id'] = (int)$user['teacher_id'];
                $_SESSION['teacher_name'] = (string)$user['teacher_name'];
                $_SESSION['teacher_must_change_password'] = (int)$user['must_change_password'] === 1 ? 1 : 0;

                authCsrf();

                header(
                    'Location: ' . ((int)$user['must_change_password'] === 1
                        ? 'teacher-change-password.php'
                        : 'teacher-dashboard.php'),
                    true,
                    303
                );
                exit;
            }
        } catch (Throwable $e) {
            error_log('BCP Teacher login error: ' . $e->getMessage());
            $error = 'Teacher login is temporarily unavailable. Please try again.';
        }
    }
}

$csrf = authCsrf();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Teacher Login | BCP Scheduling</title>
    <link rel="icon" href="../assets/images/BCP_LOGO.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/teacher-login.css">
</head>
<body class="teacher-login-page">
<main class="teacher-login-shell">
    <section class="teacher-login-brand" aria-label="BCP Teacher Portal introduction">
        <div class="teacher-login-brand__top">
            <img src="../assets/images/BCP_LOGO.png" alt="Bestlink College of the Philippines logo">
            <div>
                <strong>BESTLINK COLLEGE</strong>
                <span>OF THE PHILIPPINES</span>
            </div>
        </div>

        <div class="teacher-login-brand__content">
            <span class="teacher-login-kicker"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> FACULTY ACCESS</span>
            <h1>Your teaching schedule, <span>all in one view.</span></h1>
            <p>Access your saved class timetable, examination duties, substitute assignments, special classes, and availability from one read-only faculty workspace.</p>

            <div class="teacher-login-features" aria-label="Teacher portal features">
                <div><i class="fa-regular fa-calendar-check" aria-hidden="true"></i><span><strong>My Schedule</strong><small>Today and weekly timetable</small></span></div>
                <div><i class="fa-solid fa-file-circle-check" aria-hidden="true"></i><span><strong>Faculty Duties</strong><small>Exam and substitute assignments</small></span></div>
                <div><i class="fa-solid fa-print" aria-hidden="true"></i><span><strong>Print Ready</strong><small>Clean personal schedule report</small></span></div>
            </div>
        </div>

        <div class="teacher-login-brand__footer">BCP Class Scheduling System · Teacher Portal</div>
    </section>

    <section class="teacher-login-form-area">
        <div class="teacher-login-card">
            <div class="teacher-login-card__icon" aria-hidden="true"><i class="fa-solid fa-user-tie"></i></div>
            <span class="teacher-login-card__eyebrow">TEACHER PORTAL</span>
            <h2>Faculty sign in</h2>
<p class="teacher-login-card__subtitle">Use your teacher employee number and password. If you are a new teacher, use the temporary password provided by the administration and change it after your first sign in.</p>
            <?php if ($error !== ''): ?>
                <div class="teacher-login-error" role="alert">
                    <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                    <span><?= teacherLoginEsc($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="teacher-login-form" autocomplete="on">
                <input type="hidden" name="csrf_token" value="<?= teacherLoginEsc($csrf) ?>">

                <label class="teacher-login-field" for="teacherUsername">
                    <span>Employee number</span>
                    <div class="teacher-login-input">
                        <i class="fa-regular fa-id-badge" aria-hidden="true"></i>
                        <input
                            id="teacherUsername"
                            name="username"
                            type="text"
                            maxlength="80"
                            value="<?= teacherLoginEsc($usernameValue) ?>"
                            placeholder="e.g. DEMO-BSIT-01"
                            autocomplete="username"
                            autocapitalize="characters"
                            spellcheck="false"
                            required
                            autofocus>
                    </div>
                </label>

                <label class="teacher-login-field" for="teacherPassword">
                    <span>Password</span>
                    <div class="teacher-login-input">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input
                            id="teacherPassword"
                            name="password"
                            type="password"
                            maxlength="1024"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required>
                        <button type="button" id="teacherPasswordToggle" class="teacher-password-toggle" aria-label="Show password" title="Show password">
                            <i class="fa-regular fa-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </label>

                <button type="submit" class="teacher-login-submit">
                    <span>Sign in to Teacher Portal</span>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
            </form>

            <div class="teacher-login-help">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <p><strong>First-time account?</strong> Use the employee number assigned to your teacher record. Contact the scheduler if your account cannot be accessed.</p>
            </div>

            <a class="teacher-login-admin-link" href="../../index.php">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                Admin / Scheduler sign in
            </a>
        </div>

        <footer class="teacher-login-footer">Authorized faculty access only · BCP Scheduling System</footer>
    </section>
</main>
<script>
(() => {
    'use strict';

    const password = document.getElementById('teacherPassword');
    const toggle = document.getElementById('teacherPasswordToggle');

    toggle?.addEventListener('click', () => {
        const show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        toggle.setAttribute('title', show ? 'Hide password' : 'Show password');
        const icon = toggle.querySelector('i');
        if (icon) icon.className = show ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
        password.focus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.altKey && event.key.toLowerCase() === 'u') {
            event.preventDefault();
            document.getElementById('teacherUsername')?.focus();
        }
        if (event.altKey && event.key.toLowerCase() === 'p') {
            event.preventDefault();
            password?.focus();
        }
    });
})();
</script>
</body>
</html>
