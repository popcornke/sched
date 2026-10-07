<?php

declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

const LOGIN_OTP_EXPIRY_MINUTES = 10;
const LOGIN_OTP_MAX_ATTEMPTS = 5;
const LOGIN_OTP_RESEND_SECONDS = 60;
const LOGIN_OTP_MAX_SENDS_PER_HOUR = 5;

function loginOtpSecret(): string
{
    $secret = trim((string) getenv('OTP_SECRET'));

    if (strlen($secret) < 32) {
        throw new RuntimeException('OTP_SECRET is missing or too short.');
    }

    return $secret;
}

function loginOtpHash(int $userId, string $otp): string
{
    return hash_hmac(
        'sha256',
        $userId . '|BCP_LOGIN_2FA|' . $otp,
        loginOtpSecret()
    );
}

function loginOtpMaskEmail(string $email): string
{
    $parts = explode('@', $email, 2);

    if (count($parts) !== 2 || $parts[0] === '') {
        return 'registered email';
    }

    $local = (string) $parts[0];
    $domain = (string) $parts[1];
    $visibleLength = min(2, strlen($local));

    return substr($local, 0, $visibleLength)
        . str_repeat('*', max(4, strlen($local) - $visibleLength))
        . '@'
        . $domain;
}

function loginOtpClearPending(): void
{
    unset(
        $_SESSION['login_2fa_user_id'],
        $_SESSION['login_2fa_username'],
        $_SESSION['login_2fa_role'],
        $_SESSION['login_2fa_otp_id'],
        $_SESSION['login_2fa_email_masked'],
        $_SESSION['login_2fa_sent_at'],
        $_SESSION['login_2fa_started_at']
    );
}

function loginOtpPending(): bool
{
    return (int) ($_SESSION['login_2fa_user_id'] ?? 0) > 0
        && (int) ($_SESSION['login_2fa_otp_id'] ?? 0) > 0;
}

function loginOtpDestination(string $role): string
{
    return $role === 'TEACHER'
        ? 'app/teacher/teacher-dashboard.php'
        : 'app/dashboard/dashboard.php';
}

function loginOtpRateState(PDO $db, int $userId): array
{
    $stmt = $db->prepare(
        'SELECT
            COUNT(*) AS sends_last_hour,
            COALESCE(
                GREATEST(
                    0,
                    :cooldown - TIMESTAMPDIFF(
                        SECOND,
                        MAX(created_at),
                        NOW()
                    )
                ),
                0
            ) AS cooldown_remaining
         FROM auth_login_otps
         WHERE user_id = :user
           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );

    $stmt->execute([
        'cooldown' => LOGIN_OTP_RESEND_SECONDS,
        'user' => $userId,
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'send_count' => (int) ($row['sends_last_hour'] ?? 0),
        'cooldown_remaining' => (int) ($row['cooldown_remaining'] ?? 0),
    ];
}

function loginOtpIssue(PDO $db, array $user): array
{
    $userId = (int) ($user['user_id'] ?? 0);
    $username = (string) ($user['username'] ?? '');
    $role = (string) ($user['role'] ?? '');
    $email = trim((string) ($user['email'] ?? ''));
    $emailVerifiedAt = $user['email_verified_at'] ?? null;

    if (
        $userId <= 0
        || $username === ''
        || !in_array($role, ['ADMIN', 'SCHEDULER', 'TEACHER'], true)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || $emailVerifiedAt === null
    ) {
        return [
            'success' => false,
            'message' => 'This account does not have a verified email address.',
        ];
    }

    $rate = loginOtpRateState($db, $userId);

    if ($rate['send_count'] >= LOGIN_OTP_MAX_SENDS_PER_HOUR) {
        return [
            'success' => false,
            'message' => 'Too many verification requests. Please try again later.',
        ];
    }

    if ($rate['cooldown_remaining'] > 0) {
        return [
            'success' => false,
            'message' => 'Please wait ' . $rate['cooldown_remaining'] . ' second(s) before requesting another code.',
        ];
    }

    $otp = str_pad(
        (string) random_int(0, 999999),
        6,
        '0',
        STR_PAD_LEFT
    );

    $otpHash = loginOtpHash($userId, $otp);

    $db->beginTransaction();

    try {
        $invalidate = $db->prepare(
            'UPDATE auth_login_otps
             SET consumed_at = NOW()
             WHERE user_id = :user
               AND consumed_at IS NULL'
        );
        $invalidate->execute(['user' => $userId]);

        $insert = $db->prepare(
            'INSERT INTO auth_login_otps
                (user_id, otp_hash, expires_at, attempt_count, verified_at, consumed_at)
             VALUES
                (:user, :otp_hash, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NULL, NULL)'
        );
        $insert->execute([
            'user' => $userId,
            'otp_hash' => $otpHash,
        ]);

        $otpId = (int) $db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    $sent = bcpSendLoginOtp(
        $email,
        $username,
        $otp,
        LOGIN_OTP_EXPIRY_MINUTES,
        'bcp-login-' . $userId . '-' . $otpId
    );

    if (!$sent) {
        $delete = $db->prepare(
            'DELETE FROM auth_login_otps
             WHERE otp_id = :otp
               AND user_id = :user'
        );
        $delete->execute([
            'otp' => $otpId,
            'user' => $userId,
        ]);

        return [
            'success' => false,
            'message' => 'Unable to send the verification email. Please try again shortly.',
        ];
    }

    $_SESSION['login_2fa_user_id'] = $userId;
    $_SESSION['login_2fa_username'] = $username;
    $_SESSION['login_2fa_role'] = $role;
    $_SESSION['login_2fa_otp_id'] = $otpId;
    $_SESSION['login_2fa_email_masked'] = loginOtpMaskEmail($email);
    $_SESSION['login_2fa_sent_at'] = time();
    $_SESSION['login_2fa_started_at'] = (int) ($_SESSION['login_2fa_started_at'] ?? time());

    return [
        'success' => true,
        'message' => 'Verification code sent.',
        'otp_id' => $otpId,
    ];
}
