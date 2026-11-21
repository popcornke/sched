<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/audit.php';
require_once dirname(__DIR__) . '/shared/login-otp.php';

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

$error = isset($_GET['backup_locked']) ? 'Security lockout activated after three incorrect backup password attempts.' : '';
$lockoutUntil = 0;
// MySQL DATETIME values are in the database session's timezone, not necessarily
// PHP's default timezone. Use the DB clock for both the deadline and the timer.
try {
    $clockNow = (int) authDb()->query('SELECT UNIX_TIMESTAMP(NOW())')->fetchColumn();
} catch (Throwable $e) {
    error_log('Super Admin database clock unavailable: ' . $e->getMessage());
    $clockNow = time();
}
// Query parameter controls the countdown display only. All actual access decisions
// remain enforced by the database check in the login handler and auth middleware.
if (isset($_GET['backup_locked'])) {
    $candidate = filter_var($_GET['until'] ?? null, FILTER_VALIDATE_INT);
    if ($candidate !== false && $candidate !== null && $candidate > $clockNow && $candidate <= $clockNow + 900) {
        $lockoutUntil = $candidate;
    }
}

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
                "SELECT user_id, username, email, email_verified_at, password_hash, role, is_active, locked_until,
                        UNIX_TIMESTAMP(locked_until) AS lock_epoch,
                        GREATEST(0, TIMESTAMPDIFF(SECOND, NOW(), locked_until)) AS lock_remaining
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

            $backupLock = is_array($user) && (int)($user['lock_remaining'] ?? 0) > 0;
            if ($backupLock) {
                $error = 'Account temporarily locked. Sign in again after the security cooldown.';
                $lockoutUntil = (int)($user['lock_epoch'] ?? 0);
                auditAuthentication($db, 'LOGIN_BLOCKED_LOCKOUT', $user, 'Super Admin account locked after backup authorization failures.');
            } elseif (!$user || !$passwordOk || !$active) {
                if (is_array($user)) auditAuthentication($db, 'LOGIN_FAILED', $user, 'Super Admin password authentication failed or account inactive.');
                $error = 'Invalid username or password.';
            } else {
                // Password verified; do not establish an authenticated session until OTP succeeds.
                session_regenerate_id(true);
                $_SESSION = [];
                authCsrf();
                $user['email'] = $user['email'] ?? '';
                $user['email_verified_at'] = $user['email_verified_at'] ?? null;
                $result = loginOtpIssue($db, $user);
                if (!($result['success'] ?? false)) {
                    loginOtpClearPending();
                    $error = (string) ($result['message'] ?? 'Unable to send verification code.');
                    auditAuthentication($db, 'OTP_SEND_FAILED', $user, 'Super Admin login OTP could not be issued.');
                } else {
                    auditAuthentication($db, 'OTP_SENT', $user, 'Super Admin login OTP sent to configured security inbox.');
                    header('Location: ../../verify-login-otp.php', true, 303);
                    exit;
                }
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
        .sa-login-lockup {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 7px 11px;
            margin-bottom: 14px;
            border-radius: 999px;
            background: #edf3ff;
            color: #1a3a8c;
            font-size: .7rem;
            font-weight: 800;
            letter-spacing: .04em
        }

        .sa-password {
            position: relative
        }

        .sa-password input {
            padding-right: 44px
        }

        .sa-toggle {
            position: absolute;
            right: 13px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: none;
            color: #64748b;
            cursor: pointer
        }

        .sa-normal-login {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #3158ad;
            text-decoration: none;
            font-size: .75rem;
            font-weight: 700;
            margin-top: 14px
        }

        .sa-normal-login:hover {
            text-decoration: underline
        }

        .sa-lockout-countdown {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #fff4f2;
            color: #9f2323;
            border: 1px solid #f3c7c4;
            border-radius: 12px;
            padding: 12px 14px;
            margin: 12px 0 16px;
            font-family: 'Plus Jakarta Sans', sans-serif
        }

        .sa-lockout-countdown strong {
            font-weight: 800;
            letter-spacing: .04em;
            font-variant-numeric: tabular-nums;
            font-size: 1.15rem;
            white-space: nowrap
        }

        .sa-lockout-countdown span {
            font-size: .8rem;
            font-weight: 600;
            line-height: 1.5
        }

        .sa-lockout-countdown[hidden] {
            display: none
        }

        .sa-disabled-login {
            opacity: .6;
            cursor: not-allowed !important
        }

        .sa-login-clipboard-notice {
            margin: 8px 0 12px;
            color: #85510f;
            font-size: 12px;
            line-height: 1.55;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 500
        }

        .sa-login-clipboard-notice[hidden] {
            display: none
        }

        .sa-login-clipboard-notice i {
            margin-right: 5px
        }
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

                <div id="saLockoutPanel" class="sa-lockout-countdown" role="status" aria-live="off" <?= $lockoutUntil <= $clockNow ? 'hidden' : '' ?>
                    data-until="<?= (int)$lockoutUntil ?>" data-server-now="<?= (int)$clockNow ?>">
                    <span><i class="fa-solid fa-lock"></i> Account security cooldown<br><small>Login temporarily unavailable</small></span>
                    <strong id="saLockoutTimer" aria-label="Remaining lockout time">15:00</strong>
                </div>
                <form id="saLoginForm" method="post" action="" autocomplete="off">
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
                    <p id="saClipboardNotice" class="sa-login-clipboard-notice" role="status" aria-live="polite" hidden><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>Please type your login credentials. Copy, cut, paste, and drag-and-drop are disabled in these fields.</p>
                    <button class="login-button" id="saLoginSubmit" type="submit" <?= $lockoutUntil > $clockNow ? 'disabled' : '' ?>>Enter Super Admin Console <span aria-hidden="true">→</span></button>
                </form>

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
        (() => {
            // Match the regular Admin login's credential warning behavior.
            // Browser-only restriction; server-side authentication remains authoritative.
            const form = document.getElementById('saLoginForm');
            const notice = document.getElementById('saClipboardNotice');
            let hideNoticeTimer = null;
            const showClipboardNotice = () => {
                if (!notice) return;
                notice.hidden = false;
                if (hideNoticeTimer !== null) clearTimeout(hideNoticeTimer);
                hideNoticeTimer = setTimeout(() => {
                    notice.hidden = true;
                    hideNoticeTimer = null;
                }, 3500);
            };
            const blockClipboardAction = event => {
                event.preventDefault();
                showClipboardNotice();
            };
            for (const field of [document.getElementById('username'), document.getElementById('password')]) {
                if (!field) continue;
                for (const type of ['copy', 'cut', 'paste', 'drop', 'dragstart', 'contextmenu']) {
                    field.addEventListener(type, blockClipboardAction);
                }
                field.addEventListener('beforeinput', event => {
                    if (['insertFromPaste', 'insertFromPasteAsQuotation', 'insertFromDrop', 'deleteByCut'].includes(event.inputType)) {
                        blockClipboardAction(event);
                    }
                });
                field.addEventListener('keydown', event => {
                    const key = String(event.key).toLowerCase();
                    const modifier = event.ctrlKey || event.metaKey;
                    if ((modifier && ['c', 'v', 'x'].includes(key)) ||
                        (event.ctrlKey && key === 'insert') ||
                        (event.shiftKey && ['insert', 'delete'].includes(key))) {
                        blockClipboardAction(event);
                    }
                });
            }
            const panel = document.getElementById('saLockoutPanel');
            const timer = document.getElementById('saLockoutTimer');
            const submit = document.getElementById('saLoginSubmit');
            if (!panel || !timer || !submit) return;
            const until = Number(panel.dataset.until || 0) * 1000;
            const serverNow = Number(panel.dataset.serverNow || 0) * 1000;
            if (!until || !serverNow) return;
            // Use server-provided time as the baseline to avoid device clock skew.
            const started = performance.now();
            const update = () => {
                const remaining = Math.max(0, Math.ceil((until - (serverNow + performance.now() - started)) / 1000));
                const mm = Math.floor(remaining / 60);
                const ss = remaining % 60;
                timer.textContent = `${String(mm).padStart(2, '0')}:${String(ss).padStart(2, '0')}`;
                panel.hidden = remaining === 0;
                submit.disabled = remaining > 0;
                submit.classList.toggle('sa-disabled-login', remaining > 0);
                if (remaining === 0) clearInterval(interval);
            };
            const interval = setInterval(update, 1000);
            update();
            if (form) form.addEventListener('submit', event => {
                if (submit.disabled) event.preventDefault();
            });
        })();
    </script>
</body>

</html>