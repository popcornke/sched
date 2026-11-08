<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/audit.php';

authStart();
authNoCache();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: frame-ancestors 'none'; form-action 'self'; base-uri 'none'");
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

if (authLoggedIn()) {
    if (($_SESSION['auth_role'] ?? '') === 'SUPER_ADMIN') {
        header('Location: dashboard.php', true, 303);
        exit;
    }

    // The Super Admin portal is intentionally isolated from the normal portal.
    authClear();
    authStart();
}

$error = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? null;

    if (!authCsrfValid($token)) {
        http_response_code(403);
        $error = 'Session expired. Refresh the page and try again.';
    } elseif ($username === '' || $password === '' || strlen($username) > 80 || strlen($password) > 1024) {
        $error = 'Invalid username or password.';
    } else {
        try {
            $db = authDb();
            $stmt = $db->prepare(
                "SELECT user_id, username, password_hash, role, is_active
                 FROM auth_users
                 WHERE username = :username AND role = 'SUPER_ADMIN'
                 LIMIT 1"
            );
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $hash = is_array($user)
                ? (string) ($user['password_hash'] ?? '')
                : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

            $passwordOk = password_verify($password, $hash);
            $active = is_array($user) && (int) ($user['is_active'] ?? 0) === 1;

            if (!$user || !$passwordOk || !$active) {
                $error = 'Invalid username or password.';
            } else {
                session_regenerate_id(true);
                $_SESSION = [];
                $_SESSION['auth_user_id'] = (int) $user['user_id'];
                $_SESSION['auth_username'] = (string) $user['username'];
                $_SESSION['auth_role'] = 'SUPER_ADMIN';
                $_SESSION['auth_login_time'] = time();
                $_SESSION['auth_last_activity'] = time();
                $_SESSION['auth_regenerated_at'] = time();
                authCsrf();

                auditLog(
                    $db,
                    'LOGIN_SUCCESS',
                    'SUPER_ADMIN_AUTH',
                    'AUTH_USER',
                    (string) $user['user_id'],
                    'Super Administrator signed in.'
                );

                header('Location: dashboard.php', true, 303);
                exit;
            }
        } catch (Throwable $e) {
            error_log('BCP super-admin login: ' . $e->getMessage());
            $error = str_contains($e->getMessage(), 'SUPER_ADMIN')
                ? 'Super Admin database migration is not installed yet.'
                : 'Login service is temporarily unavailable.';
        }
    }
}

$csrf = authCsrf();
function saloginEsc(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Super Admin Login | BCP Scheduling System</title>
    <link rel="icon" href="../assets/images/BCP_LOGO.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
    <link rel="stylesheet" href="../assets/css/super-admin.css">
    <style>
        .sa-login-lockup{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;margin-bottom:14px;border-radius:999px;background:#edf3ff;color:#1a3a8c;font-size:.7rem;font-weight:800;letter-spacing:.04em}
        .sa-password{position:relative}.sa-password input{padding-right:44px}.sa-toggle{position:absolute;right:13px;top:50%;transform:translateY(-50%);border:0;background:none;color:#64748b;cursor:pointer}
        .sa-normal-login{display:inline-flex;align-items:center;gap:6px;color:#3158ad;text-decoration:none;font-size:.75rem;font-weight:700;margin-top:14px}.sa-normal-login:hover{text-decoration:underline}
    </style>
</head>
<body class="bcp-login-page">
<main class="bcp-login">
    <section class="login-intro">
        <div class="brand">
            <div class="brand-icon">BCP</div>
            <div><strong>BESTLINK COLLEGE</strong><span>OF THE PHILIPPINES</span></div>
        </div>
        <div class="intro-content">
            <div class="intro-badge">SYSTEM CONTROL PORTAL</div>
            <h1>Super Administrator <span>Console.</span></h1>
            <p>Central control for scheduling operations, user accounts, audit visibility, reports, and database administration.</p>
        </div>
        <div class="intro-footer">BCP Class Scheduling System · Restricted Access</div>
    </section>

    <section class="login-form-area">
        <div class="login-card">
            <div class="sa-login-lockup"><i class="fa-solid fa-shield-halved"></i> SUPER ADMIN ONLY</div>
            <div class="login-card-header">
                <span class="welcome-label">CONTROL CENTER</span>
                <h2>Welcome back</h2>
                <p>Sign in using a Super Administrator account.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><i class="fa-solid fa-circle-exclamation"></i><?= saloginEsc($error) ?></div>
            <?php endif; ?>

            <form method="post" action="" autocomplete="off">
                <input type="hidden" name="csrf_token" value="<?= saloginEsc($csrf) ?>">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input id="username" name="username" type="text" autocomplete="off" maxlength="80" placeholder="Enter super admin username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="sa-password">
                        <input id="password" name="password" type="password" autocomplete="off" maxlength="1024" placeholder="Enter your password" required>
                        <button class="sa-toggle" type="button" id="togglePassword" aria-label="Show password"><i class="fa-regular fa-eye" id="toggleIcon"></i></button>
                    </div>
                </div>
                <button class="login-button" type="submit">Enter Super Admin Console <span aria-hidden="true">→</span></button>
            </form>

            <p class="login-support">This login accepts <strong>SUPER_ADMIN</strong> accounts only.</p>
            <a class="sa-normal-login" href="../../index.php"><i class="fa-solid fa-arrow-left"></i> Return to main system login</a>
        </div>
        <div class="login-bottom">&copy; <?= date('Y') ?> Bestlink College of the Philippines</div>
    </section>
</main>
<script>
    (() => {
        const button = document.getElementById('togglePassword');
        const input = document.getElementById('password');
        const icon = document.getElementById('toggleIcon');
        if (!button || !input || !icon) return;
        button.addEventListener('click', () => {
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            icon.className = hidden ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye';
            button.setAttribute('aria-label', hidden ? 'Hide password' : 'Show password');
        });
    })();
</script>
</body>
</html>
