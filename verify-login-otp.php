<?php

declare(strict_types=1);

require_once __DIR__ . '/app/shared/auth.php';
require_once __DIR__ . '/app/shared/login-otp.php';

authStart();
authNoCache();

if (authLoggedIn()) {
    header('Location: ' . loginOtpDestination((string) ($_SESSION['auth_role'] ?? '')), true, 303);
    exit;
}

if (!loginOtpPending()) {
    header('Location: index.php', true, 303);
    exit;
}

$db = authDb();
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$error = '';
$success = '';
$maskedEmail = (string) ($_SESSION['login_2fa_email_masked'] ?? 'registered email');
$userId = (int) ($_SESSION['login_2fa_user_id'] ?? 0);
$otpId = (int) ($_SESSION['login_2fa_otp_id'] ?? 0);

function loginOtpEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function loginOtpCompleteSession(array $user): void
{
    session_regenerate_id(true);
    $_SESSION = [];

    $_SESSION['auth_user_id'] = (int) $user['user_id'];
    $_SESSION['auth_username'] = (string) $user['username'];
    $_SESSION['auth_role'] = (string) $user['role'];
    $_SESSION['auth_login_time'] = time();
    $_SESSION['auth_last_activity'] = time();
    $_SESSION['auth_regenerated_at'] = time();

    unset($_SESSION['csrf_token']);
    authCsrf();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string) ($_POST['action'] ?? 'verify');

    if (!authCsrfValid($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif ($action === 'cancel') {
        try {
            $stmt = $db->prepare(
                'UPDATE auth_login_otps
                 SET consumed_at = NOW()
                 WHERE otp_id = :otp
                   AND user_id = :user
                   AND consumed_at IS NULL'
            );
            $stmt->execute(['otp' => $otpId, 'user' => $userId]);
        } catch (Throwable $e) {
            error_log('BCP login OTP cancel error: ' . $e->getMessage());
        }

        loginOtpClearPending();
        session_regenerate_id(true);
        header('Location: index.php', true, 303);
        exit;
    } elseif ($action === 'resend') {
        try {
            $accountStmt = $db->prepare(
                'SELECT
                    user_id,
                    username,
                    email,
                    email_verified_at,
                    role,
                    is_active
                 FROM auth_users
                 WHERE user_id = :user
                   AND is_active = 1
                   AND email_verified_at IS NOT NULL
                 LIMIT 1'
            );
            $accountStmt->execute(['user' => $userId]);
            $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

            if (!$account) {
                loginOtpClearPending();
                $error = 'This login verification request is no longer valid.';
            } else {
                $result = loginOtpIssue($db, $account);

                if ($result['success'] ?? false) {
                    $otpId = (int) ($_SESSION['login_2fa_otp_id'] ?? 0);
                    $maskedEmail = (string) ($_SESSION['login_2fa_email_masked'] ?? 'registered email');
                    $success = 'A new verification code was sent to your registered email.';
                } else {
                    $error = (string) ($result['message'] ?? 'Unable to resend the code.');
                }
            }
        } catch (Throwable $e) {
            error_log('BCP login OTP resend error: ' . $e->getMessage());
            $error = 'Unable to resend the verification code right now.';
        }
    } else {
        $otp = trim((string) ($_POST['otp'] ?? ''));

        if (preg_match('/^\d{6}$/D', $otp) !== 1) {
            $error = 'Enter the complete 6-digit verification code.';
        } else {
            try {
                $db->beginTransaction();

                $stmt = $db->prepare(
                    'SELECT
                        otp_id,
                        otp_hash,
                        attempt_count,
                        verified_at,
                        consumed_at,
                        (expires_at > NOW()) AS is_not_expired
                     FROM auth_login_otps
                     WHERE otp_id = :otp
                       AND user_id = :user
                     LIMIT 1
                     FOR UPDATE'
                );
                $stmt->execute([
                    'otp' => $otpId,
                    'user' => $userId,
                ]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$row || $row['consumed_at'] !== null) {
                    $db->rollBack();
                    $error = 'This verification code is no longer valid. Request a new code.';
                } elseif ((int) $row['is_not_expired'] !== 1) {
                    $consume = $db->prepare(
                        'UPDATE auth_login_otps
                         SET consumed_at = NOW()
                         WHERE otp_id = :otp'
                    );
                    $consume->execute(['otp' => $otpId]);
                    $db->commit();
                    $error = 'This verification code has expired. Request a new code.';
                } elseif ((int) $row['attempt_count'] >= LOGIN_OTP_MAX_ATTEMPTS) {
                    $consume = $db->prepare(
                        'UPDATE auth_login_otps
                         SET consumed_at = NOW()
                         WHERE otp_id = :otp'
                    );
                    $consume->execute(['otp' => $otpId]);
                    $db->commit();
                    $error = 'Too many incorrect verification attempts. Request a new code.';
                } else {
                    $valid = hash_equals(
                        (string) $row['otp_hash'],
                        loginOtpHash($userId, $otp)
                    );

                    if (!$valid) {
                        $newAttempts = (int) $row['attempt_count'] + 1;
                        $consumeNow = $newAttempts >= LOGIN_OTP_MAX_ATTEMPTS;

                        $update = $db->prepare(
                            'UPDATE auth_login_otps
                             SET attempt_count = :attempts,
                                 consumed_at = CASE
                                     WHEN :consume_now = 1 THEN NOW()
                                     ELSE consumed_at
                                 END
                             WHERE otp_id = :otp'
                        );
                        $update->execute([
                            'attempts' => $newAttempts,
                            'consume_now' => $consumeNow ? 1 : 0,
                            'otp' => $otpId,
                        ]);
                        $db->commit();

                        $remaining = max(0, LOGIN_OTP_MAX_ATTEMPTS - $newAttempts);
                        $error = $remaining > 0
                            ? 'Incorrect verification code. ' . $remaining . ' attempt(s) remaining.'
                            : 'Too many incorrect verification attempts. Request a new code.';
                    } else {
                        $accountStmt = $db->prepare(
                            'SELECT
                                user_id,
                                username,
                                email,
                                email_verified_at,
                                role,
                                is_active
                             FROM auth_users
                             WHERE user_id = :user
                               AND is_active = 1
                               AND email_verified_at IS NOT NULL
                             LIMIT 1
                             FOR UPDATE'
                        );
                        $accountStmt->execute(['user' => $userId]);
                        $account = $accountStmt->fetch(PDO::FETCH_ASSOC);

                        if (!$account) {
                            $db->rollBack();
                            loginOtpClearPending();
                            $error = 'This account is no longer available for login.';
                        } else {
                            $verify = $db->prepare(
                                'UPDATE auth_login_otps
                                 SET verified_at = NOW(),
                                     consumed_at = NOW()
                                 WHERE otp_id = :otp
                                   AND consumed_at IS NULL'
                            );
                            $verify->execute(['otp' => $otpId]);
                            $db->commit();

                            $destination = loginOtpDestination((string) $account['role']);
                            loginOtpCompleteSession($account);

                            header('Location: ' . $destination, true, 303);
                            exit;
                        }
                    }
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('BCP login OTP verify error: ' . $e->getMessage());
                $error = 'Unable to verify the code right now. Please try again.';
            }
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
    <title>Login Verification | BCP Scheduling System</title>
    <link rel="icon" href="app/assets/images/BCP_LOGO.png" type="image/png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="app/assets/css/login.css">
    <link rel="stylesheet" href="app/assets/css/login-otp.css">
</head>
<body class="bcp-login-page">
<main class="bcp-login">
    <section class="login-intro">
        <div class="brand">
            <div class="brand-icon">BCP</div>
            <div>
                <strong>BESTLINK COLLEGE</strong>
                <span>OF THE PHILIPPINES</span>
            </div>
        </div>
        <div class="intro-content">
            <span class="intro-badge">ACCOUNT SECURITY</span>
            <h1>Verify your<br><span>sign-in.</span></h1>
            <p>Enter the one-time code sent to your registered email before accessing the scheduling system.</p>
        </div>
        <div class="intro-footer">BCP Class Scheduling System</div>
    </section>

    <section class="login-form-area">
        <div class="login-card login-otp-card">
            <div class="login-otp-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></div>

            <div class="login-card-header">
                <span class="welcome-label">TWO-FACTOR VERIFICATION</span>
                <h2>Enter verification code</h2>
                <p>We sent a 6-digit OTP to <strong><?= loginOtpEscape($maskedEmail) ?></strong>.</p>
            </div>

            <?php if ($error !== ''): ?>
                <div class="login-error" role="alert"><?= loginOtpEscape($error) ?></div>
            <?php endif; ?>

            <?php if ($success !== ''): ?>
                <div class="login-otp-success" role="status" aria-live="polite">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <?= loginOtpEscape($success) ?>
                </div>
            <?php endif; ?>

            <form method="post" autocomplete="off" id="verifyOtpForm">
                <input type="hidden" name="csrf_token" value="<?= loginOtpEscape($csrf) ?>">
                <input type="hidden" name="action" value="verify">

                <div class="form-group">
                    <label for="otp">6-digit verification code</label>
                    <input
                        class="login-otp-input"
                        type="text"
                        id="otp"
                        name="otp"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        minlength="6"
                        maxlength="6"
                        autocomplete="one-time-code"
                        placeholder="000000"
                        required
                        autofocus>
                </div>

                <button class="login-button" type="submit">
                    Verify and continue <span aria-hidden="true">→</span>
                </button>
            </form>

            <div class="login-otp-actions">
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= loginOtpEscape($csrf) ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button class="login-otp-secondary" type="submit">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        Back to login
                    </button>
                </form>

                <form method="post" id="resendForm">
                    <input type="hidden" name="csrf_token" value="<?= loginOtpEscape($csrf) ?>">
                    <input type="hidden" name="action" value="resend">
                    <button class="login-otp-secondary" type="submit" id="resendButton">
                        <i class="fa-regular fa-paper-plane" aria-hidden="true"></i>
                        <span id="resendText">Resend code</span>
                    </button>
                </form>
            </div>

            <p class="login-otp-note">
                The code expires after <?= LOGIN_OTP_EXPIRY_MINUTES ?> minutes. Never share your OTP with anyone.
            </p>
        </div>
    </section>
</main>

<script>
(() => {
    const input = document.getElementById('otp');
    if (input) {
        input.addEventListener('input', () => {
            input.value = input.value.replace(/\D/g, '').slice(0, 6);
        });
    }

    const resendButton = document.getElementById('resendButton');
    const resendText = document.getElementById('resendText');
    const sentAt = <?= (int) ($_SESSION['login_2fa_sent_at'] ?? 0) ?>;
    const cooldown = <?= LOGIN_OTP_RESEND_SECONDS ?>;

    if (resendButton && resendText && sentAt > 0) {
        const tick = () => {
            const elapsed = Math.floor(Date.now() / 1000) - sentAt;
            const remaining = Math.max(0, cooldown - elapsed);

            if (remaining > 0) {
                resendButton.disabled = true;
                resendText.textContent = `Resend in ${remaining}s`;
                setTimeout(tick, 1000);
            } else {
                resendButton.disabled = false;
                resendText.textContent = 'Resend code';
            }
        };
        tick();
    }
})();
</script>
</body>
</html>
