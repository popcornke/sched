<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/mailer.php';

authRequire(
    true,
    ['ADMIN'],
    true
);

header(
    'Content-Type: application/json; charset=utf-8'
);

const ADMIN_PASSWORD_HISTORY_LIMIT = 3;
const ADMIN_EMAIL_OTP_EXPIRY_MINUTES = 10;
const ADMIN_EMAIL_OTP_MAX_ATTEMPTS = 5;
const ADMIN_EMAIL_OTP_MAX_SENDS_PER_HOUR = 5;
const ADMIN_EMAIL_OTP_RESEND_SECONDS = 60;

function aasRespond(
    int $status,
    array $payload
): never {
    http_response_code($status);

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

function aasInput(): array
{
    $contentType = strtolower(
        (string) (
            $_SERVER['CONTENT_TYPE']
            ?? ''
        )
    );

    if (
        str_contains(
            $contentType,
            'application/json'
        )
    ) {
        $raw = file_get_contents('php://input');

        if (
            !is_string($raw)
            || trim($raw) === ''
        ) {
            return [];
        }

        $decoded = json_decode(
            $raw,
            true
        );

        return is_array($decoded)
            ? $decoded
            : [];
    }

    return $_POST;
}

function aasStrongPassword(
    string $password
): bool {
    if (
        strlen($password) < 12
        || strlen($password) > 128
    ) {
        return false;
    }

    return preg_match('/[A-Z]/', $password) === 1
        && preg_match('/[a-z]/', $password) === 1
        && preg_match('/[0-9]/', $password) === 1;
}

function aasEmailOtpSecret(): string
{
    $secret = trim(
        (string) getenv('OTP_SECRET')
    );

    if (strlen($secret) < 32) {
        throw new RuntimeException(
            'OTP_SECRET is missing or too short.'
        );
    }

    return $secret;
}

function aasEmailOtpHash(
    int $userId,
    string $email,
    string $otp
): string {
    return hash_hmac(
        'sha256',
        $userId
            . '|BCP_ADMIN_EMAIL_CHANGE|'
            . strtolower($email)
            . '|'
            . $otp,
        aasEmailOtpSecret()
    );
}

function aasEmailHtml(
    string $username,
    string $otp
): string {
    $safeUser = htmlspecialchars(
        $username,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    $safeOtp = htmlspecialchars(
        $otp,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    return '<!doctype html>'
        . '<html><body style="font-family:Arial,sans-serif;background:#f5f7fb;padding:32px;color:#172033;">'
        . '<div style="max-width:560px;margin:auto;background:#fff;border:1px solid #e4e8f0;border-radius:16px;padding:32px;">'
        . '<div style="font-size:13px;font-weight:700;color:#1a3a8c;letter-spacing:.08em;">BCP ACCOUNT SECURITY</div>'
        . '<h2 style="margin:12px 0 8px;">Verify your new email address</h2>'
        . '<p>Hello ' . $safeUser . ', use this verification code to confirm the new email address for your administrator account.</p>'
        . '<div style="font-size:34px;font-weight:800;letter-spacing:.18em;color:#1a3a8c;margin:24px 0;">'
        . $safeOtp
        . '</div>'
        . '<p>This code expires in '
        . ADMIN_EMAIL_OTP_EXPIRY_MINUTES
        . ' minutes. If you did not request this change, do not share the code.</p>'
        . '</div></body></html>';
}

function aasAccount(
    PDO $db,
    int $userId,
    bool $forUpdate = false
): array {
    $sql =
        'SELECT
            user_id,
            username,
            email,
            email_verified_at,
            password_hash,
            password_changed_at,
            role,
            is_active,
            failed_attempts,
            locked_until,
            created_at
         FROM auth_users
         WHERE user_id = :user_id
           AND role = "ADMIN"
         LIMIT 1';

    if ($forUpdate) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $db->prepare($sql);

    $stmt->execute([
        'user_id' => $userId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (
        !$row
        || (int) $row['is_active'] !== 1
    ) {
        aasRespond(
            403,
            [
                'success' => false,
                'status' => 'ACCOUNT_UNAVAILABLE',
                'message' =>
                    'This administrator account is unavailable.'
            ]
        );
    }

    return $row;
}

function aasProfilePayload(
    PDO $db,
    array $account
): array {
    $state = authAdminPasswordState(
        $db,
        (int) $account['user_id']
    );

    return [
        'user_id' =>
            (int) $account['user_id'],
        'username' =>
            (string) $account['username'],
        'email' =>
            (string) ($account['email'] ?? ''),
        'email_verified' =>
            $account['email_verified_at'] !== null,
        'email_verified_at' =>
            $account['email_verified_at'],
        'role' =>
            (string) $account['role'],
        'is_active' =>
            (int) $account['is_active'] === 1,
        'created_at' =>
            $account['created_at'],
        'password' => [
            'changed_at' =>
                $state['password_changed_at'],
            'expires_at' =>
                $state['password_expires_at'],
            'expired' =>
                $state['expired'],
            'warning' =>
                $state['warning'],
            'days_remaining' =>
                $state['days_remaining'],
            'max_age_days' =>
                ADMIN_PASSWORD_MAX_AGE_DAYS,
            'warning_days' =>
                ADMIN_PASSWORD_WARNING_DAYS,
        ],
        'session_security' => [
            'idle_timeout_minutes' =>
                (int) (SESSION_IDLE_LIMIT / 60),
            'max_session_hours' =>
                (int) (SESSION_MAX_LIFETIME / 3600),
        ],
    ];
}

$db = authDb();
$db->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);

$userId = (int) (
    $_SESSION['auth_user_id']
    ?? 0
);

$method = strtoupper(
    (string) (
        $_SERVER['REQUEST_METHOD']
        ?? 'GET'
    )
);

$action = trim(
    (string) (
        $_GET['action']
        ?? ''
    )
);

if ($method === 'GET') {
    if (
        $action !== ''
        && $action !== 'profile'
    ) {
        aasRespond(
            400,
            [
                'success' => false,
                'status' => 'INVALID_ACTION',
                'message' => 'Invalid account action.'
            ]
        );
    }

    $account = aasAccount(
        $db,
        $userId
    );

    aasRespond(
        200,
        [
            'success' => true,
            'status' => 'ACCOUNT_READY',
            'account' =>
                aasProfilePayload(
                    $db,
                    $account
                )
        ]
    );
}

if ($method !== 'POST') {
    header('Allow: GET, POST');

    aasRespond(
        405,
        [
            'success' => false,
            'status' => 'METHOD_NOT_ALLOWED',
            'message' => 'Method not allowed.'
        ]
    );
}

$input = aasInput();

if (
    !authCsrfValid(
        $input['csrf_token']
        ?? null
    )
) {
    aasRespond(
        403,
        [
            'success' => false,
            'status' => 'INVALID_CSRF',
            'message' =>
                'Your session expired. Refresh and try again.'
        ]
    );
}

$action = trim(
    (string) (
        $input['action']
        ?? $action
    )
);

try {
    if ($action === 'update_username') {
        $username = trim(
            (string) (
                $input['username']
                ?? ''
            )
        );

        $currentPassword =
            (string) (
                $input['current_password']
                ?? ''
            );

        if (
            strlen($username) < 3
            || strlen($username) > 80
            || preg_match(
                '/^[A-Za-z0-9._-]+$/D',
                $username
            ) !== 1
        ) {
            aasRespond(
                422,
                [
                    'success' => false,
                    'status' => 'INVALID_USERNAME',
                    'message' =>
                        'Username must be 3–80 characters and may contain letters, numbers, dots, underscores, and hyphens.'
                ]
            );
        }

        $account = aasAccount(
            $db,
            $userId
        );

        if (
            !password_verify(
                $currentPassword,
                (string) $account['password_hash']
            )
        ) {
            aasRespond(
                422,
                [
                    'success' => false,
                    'status' =>
                        'CURRENT_PASSWORD_INCORRECT',
                    'message' =>
                        'Your current password is incorrect.'
                ]
            );
        }

        $duplicate = $db->prepare(
            'SELECT user_id
             FROM auth_users
             WHERE username = :username
               AND user_id <> :user_id
             LIMIT 1'
        );

        $duplicate->execute([
            'username' => $username,
            'user_id' => $userId
        ]);

        if ($duplicate->fetch()) {
            aasRespond(
                409,
                [
                    'success' => false,
                    'status' => 'USERNAME_IN_USE',
                    'message' =>
                        'That username is already in use.'
                ]
            );
        }

        $update = $db->prepare(
            'UPDATE auth_users
             SET username = :username
             WHERE user_id = :user_id
               AND role = "ADMIN"'
        );

        $update->execute([
            'username' => $username,
            'user_id' => $userId
        ]);

        $_SESSION['auth_username'] =
            $username;

        $_SESSION['auth_last_activity'] =
            time();

        aasRespond(
            200,
            [
                'success' => true,
                'status' => 'USERNAME_UPDATED',
                'message' =>
                    'Administrator username updated.',
                'username' => $username
            ]
        );
    }

    if ($action === 'request_email_change') {
        $newEmail = strtolower(
            trim(
                (string) (
                    $input['new_email']
                    ?? ''
                )
            )
        );

        $currentPassword =
            (string) (
                $input['current_password']
                ?? ''
            );

        if (
            !filter_var(
                $newEmail,
                FILTER_VALIDATE_EMAIL
            )
            || strlen($newEmail) > 254
        ) {
            aasRespond(
                422,
                [
                    'success' => false,
                    'status' => 'INVALID_EMAIL',
                    'message' =>
                        'Enter a valid email address.'
                ]
            );
        }

        $account = aasAccount(
            $db,
            $userId
        );

        if (
            !password_verify(
                $currentPassword,
                (string) $account['password_hash']
            )
        ) {
            aasRespond(
                422,
                [
                    'success' => false,
                    'status' =>
                        'CURRENT_PASSWORD_INCORRECT',
                    'message' =>
                        'Your current password is incorrect.'
                ]
            );
        }

        if (
            strtolower(
                (string) (
                    $account['email']
                    ?? ''
                )
            ) === $newEmail
        ) {
            aasRespond(
                422,
                [
                    'success' => false,
                    'status' => 'EMAIL_UNCHANGED',
                    'message' =>
                        'That is already your verified email address.'
                ]
            );
        }

        $duplicate = $db->prepare(
            'SELECT user_id
             FROM auth_users
             WHERE LOWER(email) = :email
               AND user_id <> :user_id
             LIMIT 1'
        );

        $duplicate->execute([
            'email' => $newEmail,
            'user_id' => $userId
        ]);

        if ($duplicate->fetch()) {
            aasRespond(
                409,
                [
                    'success' => false,
                    'status' => 'EMAIL_IN_USE',
                    'message' =>
                        'That email address is already in use.'
                ]
            );
        }

        $rate = $db->prepare(
            'SELECT
                COUNT(*) AS sends_last_hour,
                COALESCE(
                    GREATEST(
                        0,
                        :cooldown
                        - TIMESTAMPDIFF(
                            SECOND,
                            MAX(created_at),
                            NOW()
                        )
                    ),
                    0
                ) AS cooldown_remaining
             FROM auth_email_change_otps
             WHERE user_id = :user_id
               AND created_at >= DATE_SUB(
                    NOW(),
                    INTERVAL 1 HOUR
               )'
        );

        $rate->execute([
            'cooldown' =>
                ADMIN_EMAIL_OTP_RESEND_SECONDS,
            'user_id' => $userId
        ]);

        $rateRow =
            $rate->fetch(PDO::FETCH_ASSOC)
            ?: [];

        $sendCount =
            (int) (
                $rateRow['sends_last_hour']
                ?? 0
            );

        $cooldownRemaining =
            (int) (
                $rateRow['cooldown_remaining']
                ?? 0
            );

        if (
            $sendCount
            >= ADMIN_EMAIL_OTP_MAX_SENDS_PER_HOUR
        ) {
            aasRespond(
                429,
                [
                    'success' => false,
                    'status' => 'EMAIL_OTP_RATE_LIMIT',
                    'message' =>
                        'Too many verification requests. Try again later.'
                ]
            );
        }

        if ($cooldownRemaining > 0) {
            aasRespond(
                429,
                [
                    'success' => false,
                    'status' => 'EMAIL_OTP_COOLDOWN',
                    'message' =>
                        'Wait '
                        . $cooldownRemaining
                        . ' second(s) before requesting another code.',
                    'cooldown_remaining' =>
                        $cooldownRemaining
                ]
            );
        }

        $otp = str_pad(
            (string) random_int(
                0,
                999999
            ),
            6,
            '0',
            STR_PAD_LEFT
        );

        $otpHash = aasEmailOtpHash(
            $userId,
            $newEmail,
            $otp
        );

        $db->beginTransaction();

        try {
            $invalidate = $db->prepare(
                'UPDATE auth_email_change_otps
                 SET consumed_at = NOW()
                 WHERE user_id = :user_id
                   AND consumed_at IS NULL'
            );

            $invalidate->execute([
                'user_id' => $userId
            ]);

            $insert = $db->prepare(
                'INSERT INTO auth_email_change_otps
                    (
                        user_id,
                        new_email,
                        otp_hash,
                        expires_at,
                        attempt_count,
                        consumed_at
                    )
                 VALUES
                    (
                        :user_id,
                        :new_email,
                        :otp_hash,
                        DATE_ADD(
                            NOW(),
                            INTERVAL 10 MINUTE
                        ),
                        0,
                        NULL
                    )'
            );

            $insert->execute([
                'user_id' => $userId,
                'new_email' => $newEmail,
                'otp_hash' => $otpHash
            ]);

            $otpId =
                (int) $db->lastInsertId();

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        $mailSent = bcpMailerSend(
            $newEmail,
            'Verify your BCP administrator email',
            aasEmailHtml(
                (string) $account['username'],
                $otp
            ),
            'admin-email-change-'
                . $userId
                . '-'
                . $otpId,
            'account_email_change'
        );

        if (!$mailSent) {
            $consume = $db->prepare(
                'UPDATE auth_email_change_otps
                 SET consumed_at = NOW()
                 WHERE email_change_otp_id = :otp_id
                   AND user_id = :user_id'
            );

            $consume->execute([
                'otp_id' => $otpId,
                'user_id' => $userId
            ]);

            aasRespond(
                503,
                [
                    'success' => false,
                    'status' => 'EMAIL_SEND_FAILED',
                    'message' =>
                        'The verification email could not be sent. Your current login email was not changed.'
                ]
            );
        }

        $_SESSION[
            'admin_email_change_otp_id'
        ] = $otpId;

        $_SESSION[
            'admin_email_change_email'
        ] = $newEmail;

        $_SESSION[
            'admin_email_change_sent_at'
        ] = time();

        aasRespond(
            200,
            [
                'success' => true,
                'status' =>
                    'EMAIL_VERIFICATION_SENT',
                'message' =>
                    'A verification code was sent to the new email address.',
                'expires_minutes' =>
                    ADMIN_EMAIL_OTP_EXPIRY_MINUTES,
                'cooldown_seconds' =>
                    ADMIN_EMAIL_OTP_RESEND_SECONDS
            ]
        );
    }

    if ($action === 'confirm_email_change') {
        $otp = trim(
            (string) (
                $input['otp']
                ?? ''
            )
        );

        $otpId = (int) (
            $_SESSION[
                'admin_email_change_otp_id'
            ]
            ?? 0
        );

        if (
            preg_match(
                '/^\d{6}$/D',
                $otp
            ) !== 1
            || $otpId <= 0
        ) {
            aasRespond(
                422,
                [
                    'success' => false,
                    'status' => 'INVALID_EMAIL_OTP',
                    'message' =>
                        'Enter the complete 6-digit verification code.'
                ]
            );
        }

        $db->beginTransaction();

        try {
            $stmt = $db->prepare(
                'SELECT
                    email_change_otp_id,
                    new_email,
                    otp_hash,
                    attempt_count,
                    consumed_at,
                    (expires_at > NOW())
                        AS is_not_expired
                 FROM auth_email_change_otps
                 WHERE email_change_otp_id = :otp_id
                   AND user_id = :user_id
                 LIMIT 1
                 FOR UPDATE'
            );

            $stmt->execute([
                'otp_id' => $otpId,
                'user_id' => $userId
            ]);

            $row =
                $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$row
                || $row['consumed_at'] !== null
            ) {
                $db->rollBack();

                aasRespond(
                    422,
                    [
                        'success' => false,
                        'status' =>
                            'EMAIL_OTP_INVALIDATED',
                        'message' =>
                            'This verification code is no longer valid.'
                    ]
                );
            }

            if (
                (int) $row['is_not_expired']
                !== 1
            ) {
                $consume = $db->prepare(
                    'UPDATE auth_email_change_otps
                     SET consumed_at = NOW()
                     WHERE email_change_otp_id = :otp_id'
                );

                $consume->execute([
                    'otp_id' => $otpId
                ]);

                $db->commit();

                aasRespond(
                    422,
                    [
                        'success' => false,
                        'status' => 'EMAIL_OTP_EXPIRED',
                        'message' =>
                            'This verification code has expired. Request a new code.'
                    ]
                );
            }

            $attempts =
                (int) $row['attempt_count'];

            if (
                $attempts
                >= ADMIN_EMAIL_OTP_MAX_ATTEMPTS
            ) {
                $db->rollBack();

                aasRespond(
                    429,
                    [
                        'success' => false,
                        'status' =>
                            'EMAIL_OTP_ATTEMPTS_EXCEEDED',
                        'message' =>
                            'Too many incorrect attempts. Request a new code.'
                    ]
                );
            }

            $newEmail =
                strtolower(
                    (string) $row['new_email']
                );

            $valid = hash_equals(
                (string) $row['otp_hash'],
                aasEmailOtpHash(
                    $userId,
                    $newEmail,
                    $otp
                )
            );

            if (!$valid) {
                $newAttempts =
                    $attempts + 1;

                $consumeNow =
                    $newAttempts
                    >= ADMIN_EMAIL_OTP_MAX_ATTEMPTS;

                $update = $db->prepare(
                    'UPDATE auth_email_change_otps
                     SET
                        attempt_count = :attempts,
                        consumed_at = CASE
                            WHEN :consume_now = 1
                            THEN NOW()
                            ELSE consumed_at
                        END
                     WHERE email_change_otp_id = :otp_id'
                );

                $update->execute([
                    'attempts' =>
                        $newAttempts,
                    'consume_now' =>
                        $consumeNow ? 1 : 0,
                    'otp_id' => $otpId
                ]);

                $db->commit();

                aasRespond(
                    422,
                    [
                        'success' => false,
                        'status' =>
                            'EMAIL_OTP_INCORRECT',
                        'message' =>
                            $consumeNow
                                ? 'Too many incorrect attempts. Request a new code.'
                                : 'Incorrect verification code.',
                        'attempts_remaining' =>
                            max(
                                0,
                                ADMIN_EMAIL_OTP_MAX_ATTEMPTS
                                - $newAttempts
                            )
                    ]
                );
            }

            $duplicate = $db->prepare(
                'SELECT user_id
                 FROM auth_users
                 WHERE LOWER(email) = :email
                   AND user_id <> :user_id
                 LIMIT 1
                 FOR UPDATE'
            );

            $duplicate->execute([
                'email' => $newEmail,
                'user_id' => $userId
            ]);

            if ($duplicate->fetch()) {
                $db->rollBack();

                aasRespond(
                    409,
                    [
                        'success' => false,
                        'status' => 'EMAIL_IN_USE',
                        'message' =>
                            'That email address is now in use by another account.'
                    ]
                );
            }

            $updateUser = $db->prepare(
                'UPDATE auth_users
                 SET
                    email = :email,
                    email_verified_at = NOW()
                 WHERE user_id = :user_id
                   AND role = "ADMIN"'
            );

            $updateUser->execute([
                'email' => $newEmail,
                'user_id' => $userId
            ]);

            $consume = $db->prepare(
                'UPDATE auth_email_change_otps
                 SET consumed_at = NOW()
                 WHERE email_change_otp_id = :otp_id'
            );

            $consume->execute([
                'otp_id' => $otpId
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        unset(
            $_SESSION[
                'admin_email_change_otp_id'
            ],
            $_SESSION[
                'admin_email_change_email'
            ],
            $_SESSION[
                'admin_email_change_sent_at'
            ]
        );

        aasRespond(
            200,
            [
                'success' => true,
                'status' => 'EMAIL_UPDATED',
                'message' =>
                    'Your new email address is verified and saved.',
                'email' => $newEmail
            ]
        );
    }

    if ($action === 'change_password') {
        $currentPassword =
            (string) (
                $input['current_password']
                ?? ''
            );

        $newPassword =
            (string) (
                $input['new_password']
                ?? ''
            );

        $confirmPassword =
            (string) (
                $input['confirm_password']
                ?? ''
            );

        if (
            $newPassword
            !== $confirmPassword
        ) {
            aasRespond(
                422,
                [
                    'success' => false,
                    'status' =>
                        'PASSWORD_CONFIRMATION_MISMATCH',
                    'message' =>
                        'The new password and confirmation do not match.'
                ]
            );
        }

        if (
            !aasStrongPassword(
                $newPassword
            )
        ) {
            aasRespond(
                422,
                [
                    'success' => false,
                    'status' => 'WEAK_PASSWORD',
                    'message' =>
                        'Use 12–128 characters with at least one uppercase letter, lowercase letter, and number.'
                ]
            );
        }

        $db->beginTransaction();

        try {
            $account = aasAccount(
                $db,
                $userId,
                true
            );

            $currentHash =
                (string) $account['password_hash'];

            if (
                !password_verify(
                    $currentPassword,
                    $currentHash
                )
            ) {
                $db->rollBack();

                aasRespond(
                    422,
                    [
                        'success' => false,
                        'status' =>
                            'CURRENT_PASSWORD_INCORRECT',
                        'message' =>
                            'Your current password is incorrect.'
                    ]
                );
            }

            if (
                strcasecmp(
                    $newPassword,
                    (string) $account['username']
                ) === 0
            ) {
                $db->rollBack();

                aasRespond(
                    422,
                    [
                        'success' => false,
                        'status' =>
                            'PASSWORD_MATCHES_USERNAME',
                        'message' =>
                            'Your password cannot be the same as your username.'
                    ]
                );
            }

            if (
                password_verify(
                    $newPassword,
                    $currentHash
                )
            ) {
                $db->rollBack();

                aasRespond(
                    422,
                    [
                        'success' => false,
                        'status' => 'PASSWORD_REUSED',
                        'message' =>
                            'Your new password must be different from your current password.'
                    ]
                );
            }

            $history = $db->prepare(
                'SELECT password_hash
                 FROM auth_password_history
                 WHERE user_id = :user_id
                 ORDER BY
                    changed_at DESC,
                    history_id DESC
                 LIMIT 3'
            );

            $history->execute([
                'user_id' => $userId
            ]);

            foreach (
                $history->fetchAll(
                    PDO::FETCH_COLUMN
                )
                as $oldHash
            ) {
                if (
                    is_string($oldHash)
                    && password_verify(
                        $newPassword,
                        $oldHash
                    )
                ) {
                    $db->rollBack();

                    aasRespond(
                        422,
                        [
                            'success' => false,
                            'status' =>
                                'PASSWORD_REUSED',
                            'message' =>
                                'You cannot reuse any of your last three passwords.'
                        ]
                    );
                }
            }

            $newHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            if (
                !is_string($newHash)
                || $newHash === ''
            ) {
                throw new RuntimeException(
                    'Password hashing failed.'
                );
            }

            /*
             * Save the current hash into history before replacing it.
             */
            $saveHistory = $db->prepare(
                'INSERT INTO auth_password_history
                    (
                        user_id,
                        password_hash
                    )
                 VALUES
                    (
                        :user_id,
                        :password_hash
                    )'
            );

            $saveHistory->execute([
                'user_id' => $userId,
                'password_hash' =>
                    $currentHash
            ]);

            $update = $db->prepare(
                'UPDATE auth_users
                 SET
                    password_hash =
                        :password_hash,
                    password_changed_at =
                        NOW(),
                    failed_attempts = 0,
                    locked_until = NULL
                 WHERE user_id = :user_id
                   AND role = "ADMIN"'
            );

            $update->execute([
                'password_hash' =>
                    $newHash,
                'user_id' => $userId
            ]);

            /*
             * Keep only the three latest PRIOR password hashes.
             */
            $prune = $db->prepare(
                'DELETE FROM auth_password_history
                 WHERE user_id = :user_id
                   AND history_id NOT IN (
                        SELECT history_id
                        FROM (
                            SELECT history_id
                            FROM auth_password_history
                            WHERE user_id = :inner_user
                            ORDER BY
                                changed_at DESC,
                                history_id DESC
                            LIMIT 3
                        ) keep_rows
                   )'
            );

            $prune->execute([
                'user_id' => $userId,
                'inner_user' => $userId
            ]);

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }

        $_SESSION[
            'auth_password_expired'
        ] = 0;

        $_SESSION[
            'auth_password_warning'
        ] = 0;

        $_SESSION[
            'auth_password_days_remaining'
        ] = ADMIN_PASSWORD_MAX_AGE_DAYS;

        $_SESSION['auth_last_activity'] =
            time();

        $_SESSION['auth_regenerated_at'] =
            time();

        session_regenerate_id(true);

        aasRespond(
            200,
            [
                'success' => true,
                'status' => 'PASSWORD_UPDATED',
                'message' =>
                    'Password changed successfully. Your new 30-day security period has started.',
                'redirect' =>
                    authBasePath()
                    . '/app/dashboard/dashboard.php'
            ]
        );
    }

    aasRespond(
        400,
        [
            'success' => false,
            'status' => 'INVALID_ACTION',
            'message' => 'Invalid account action.'
        ]
    );
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }

    error_log(
        'BCP Admin Account Settings API error: '
        . $e->getMessage()
    );

    aasRespond(
        500,
        [
            'success' => false,
            'status' =>
                'ACCOUNT_SETTINGS_FAILED',
            'message' =>
                'Unable to update account settings right now.'
        ]
    );
}
