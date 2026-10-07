<?php

declare(strict_types=1);

require_once __DIR__ . '/app/shared/auth.php';
require_once __DIR__ . '/app/shared/mailer.php';

authStart();
authNoCache();


/*
 * ============================================================
 * BCP SCHEDULING SYSTEM
 * ADMIN / SCHEDULER FORGOT PASSWORD
 * ============================================================
 *
 * FLOW:
 *
 * 1. Enter registered email
 * 2. Send 6-digit OTP through Resend
 * 3. Verify OTP
 * 4. Create new password
 * 5. Return to root index.php login
 *
 * IMPORTANT:
 *
 * - ADMIN / SCHEDULER only
 * - Teacher Portal is separate
 * - No current password is required
 * - OTP is never stored as plaintext
 * - OTP expires after 10 minutes
 * - Maximum 5 wrong OTP attempts
 * - Resend cooldown = 60 seconds
 * - Password reset clears login lockout
 * ============================================================
 */


/*
 * ============================================================
 * CONFIGURATION
 * ============================================================
 */

const FP_OTP_EXPIRY_MINUTES = 10;

const FP_OTP_MAX_ATTEMPTS = 5;

const FP_RESEND_SECONDS = 60;

const FP_MAX_SENDS_PER_HOUR = 5;


/*
 * ============================================================
 * IF ALREADY LOGGED IN
 * ============================================================
 */

if (authLoggedIn()) {

    header(
        'Location: app/dashboard/dashboard.php',
        true,
        303
    );

    exit;
}


/*
 * ============================================================
 * HELPERS
 * ============================================================
 */

function fpEscape(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}


/*
 * OTP secret must exist in Railway environment variables.
 *
 * Example:
 *
 * OTP_SECRET=<64-character-random-secret>
 */
function fpOtpSecret(): string
{
    $secret = trim(
        (string) getenv(
            'OTP_SECRET'
        )
    );


    if (strlen($secret) < 32) {

        throw new RuntimeException(
            'OTP_SECRET is missing or too short.'
        );
    }


    return $secret;
}


/*
 * We never save the plain OTP.
 *
 * Database stores only an HMAC hash.
 */
function fpOtpHash(
    int $userId,
    string $otp
): string {

    return hash_hmac(
        'sha256',

        $userId
            . '|BCP_ADMIN_PASSWORD_RESET|'
            . $otp,

        fpOtpSecret()
    );
}


/*
 * Password policy.
 */
function fpStrongPassword(
    string $password
): bool {

    if (
        strlen($password) < 12
        || strlen($password) > 128
    ) {
        return false;
    }


    return
        preg_match(
            '/[A-Z]/',
            $password
        ) === 1

        && preg_match(
            '/[a-z]/',
            $password
        ) === 1

        && preg_match(
            '/[0-9]/',
            $password
        ) === 1;
}


/*
 * Mask registered email.
 *
 * Example:
 *
 * mikedabu702@gmail.com
 *
 * becomes:
 *
 * mi*********@gmail.com
 */
function fpMaskEmail(
    string $email
): string {

    $parts = explode(
        '@',
        $email,
        2
    );


    if (count($parts) !== 2) {
        return 'registered email';
    }


    $local = (string) $parts[0];

    $domain = (string) $parts[1];


    if ($local === '') {
        return 'registered email';
    }


    $visibleLength = min(
        2,
        strlen($local)
    );


    $visible = substr(
        $local,
        0,
        $visibleLength
    );


    $hidden = str_repeat(
        '*',
        max(
            4,
            strlen($local)
                - $visibleLength
        )
    );


    return
        $visible
        . $hidden
        . '@'
        . $domain;
}


/*
 * Clear password-reset session state.
 */
function fpClearSession(): void
{
    unset(
        $_SESSION['fp_stage'],
        $_SESSION['fp_user_id'],
        $_SESSION['fp_otp_id'],
        $_SESSION['fp_email_masked'],
        $_SESSION['fp_sent_at'],
        $_SESSION['fp_verified']
    );
}


/*
 * Move user to OTP stage.
 */
function fpSetOtpSession(
    int $userId,
    int $otpId,
    string $maskedEmail
): void {

    $_SESSION['fp_stage'] =
        'otp';


    $_SESSION['fp_user_id'] =
        $userId;


    $_SESSION['fp_otp_id'] =
        $otpId;


    $_SESSION['fp_email_masked'] =
        $maskedEmail;


    $_SESSION['fp_sent_at'] =
        time();


    $_SESSION['fp_verified'] =
        false;
}


/*
 * ============================================================
 * INITIAL PAGE STATE
 * ============================================================
 */

$error = '';

$success = '';


$stage = (string) (
    $_SESSION['fp_stage']
    ?? 'email'
);


$allowedStages = [
    'email',
    'otp',
    'password',
    'done',
];


if (
    !in_array(
        $stage,
        $allowedStages,
        true
    )
) {

    fpClearSession();

    $stage = 'email';
}


$db = authDb();

$db->setAttribute(
    PDO::ATTR_ERRMODE,
    PDO::ERRMODE_EXCEPTION
);


/*
 * ============================================================
 * HANDLE POST
 * ============================================================
 */

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    === 'POST'
) {

    $action = (string) (
        $_POST['action']
        ?? ''
    );


    /*
     * ========================================================
     * CSRF VALIDATION
     * ========================================================
     */

    if (
        !authCsrfValid(
            $_POST['csrf_token']
                ?? null
        )
    ) {

        http_response_code(403);

        $error =
            'Your session expired. Refresh the page and try again.';


        /*
     * ========================================================
     * RESTART RECOVERY
     * ========================================================
     */
    } elseif (
        $action === 'restart'
    ) {

        fpClearSession();


        session_regenerate_id(
            true
        );


        header(
            'Location: forgot-password.php',
            true,
            303
        );

        exit;


        /*
     * ========================================================
     * SEND FIRST OTP
     * ========================================================
     */
    } elseif (
        $action === 'send_otp'
    ) {

        $email = strtolower(
            trim(
                (string) (
                    $_POST['email']
                    ?? ''
                )
            )
        );


        /*
         * Basic email syntax validation.
         */
        if (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $error =
                'Enter a valid email address.';

            $stage =
                'email';
        } else {

            try {

                /*
                 * Only registered, verified and active
                 * ADMIN / SCHEDULER accounts can recover
                 * through this page.
                 */
                $stmt = $db->prepare(
                    "SELECT
                        user_id,
                        username,
                        email,
                        role,
                        is_active

                     FROM auth_users

                     WHERE LOWER(email) =
                        LOWER(:email)

                       AND role IN (
                           'ADMIN',
                           'SCHEDULER'
                       )

                       AND is_active = 1

                       AND email_verified_at
                           IS NOT NULL

                     LIMIT 1"
                );


                $stmt->execute([
                    'email' => $email
                ]);


                $account = $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


                /*
                 * =================================================
                 * UNKNOWN EMAIL
                 * =================================================
                 *
                 * Do NOT reveal:
                 *
                 * "This email is not registered."
                 *
                 * Otherwise an attacker could enumerate
                 * administrator emails.
                 *
                 * We simulate the same OTP screen.
                 */
                if (!$account) {

                    fpSetOtpSession(
                        0,
                        0,
                        fpMaskEmail(
                            $email
                        )
                    );


                    $stage =
                        'otp';


                    $success =
                        'If this email is registered to an active '
                        . 'Admin or Scheduler account, a verification '
                        . 'code has been sent.';


                    /*
                 * =================================================
                 * REAL REGISTERED ACCOUNT
                 * =================================================
                 */
                } else {

                    $userId = (int) (
                        $account['user_id']
                    );


                    /*
                     * ---------------------------------------------
                     * RATE LIMIT
                     * ---------------------------------------------
                     *
                     * Maximum 5 OTP records per hour.
                     */
                    /*
                     * Keep all database time arithmetic inside MySQL.
                     *
                     * Do NOT parse created_at with PHP strtotime().
                     * Local XAMPP and Railway/MySQL may use different
                     * time zones, which can turn a 60-second cooldown
                     * into several hours.
                     */
                    $rate = $db->prepare(
                        "SELECT
                            COUNT(*) AS sends_last_hour,

                            COALESCE(
                                GREATEST(
                                    0,
                                    "
                                . FP_RESEND_SECONDS .
                                " - TIMESTAMPDIFF(
                                    SECOND,
                                    MAX(created_at),
                                    NOW()
                                )
                            ),
                            0
                        ) AS cooldown_remaining

                         FROM
                            auth_password_reset_otps

                         WHERE user_id = :user

                           AND created_at >=
                               DATE_SUB(
                                   NOW(),
                                   INTERVAL 1 HOUR
                               )"
                    );


                    $rate->execute([
                        'user' => $userId
                    ]);


                    $rateRow = $rate->fetch(
                        PDO::FETCH_ASSOC
                    );


                    $sendCount = (int) (
                        $rateRow['sends_last_hour']
                        ?? 0
                    );


                    $cooldownRemaining = max(
                        0,
                        min(
                            FP_RESEND_SECONDS,
                            (int) (
                                $rateRow['cooldown_remaining']
                                ?? 0
                            )
                        )
                    );


                    /*
                     * Hourly limit.
                     */
                    if (
                        $sendCount
                        >= FP_MAX_SENDS_PER_HOUR
                    ) {

                        $error =
                            'Too many verification requests. '
                            . 'Please try again later.';

                        $stage =
                            'email';


                        /*
                     * 60-second cooldown.
                     */
                    } elseif (
                        $cooldownRemaining > 0
                    ) {

                        $error =
                            'Please wait '
                            . $cooldownRemaining
                            . ' second(s) before requesting another code.';

                        $stage =
                            'email';


                        /*
                     * Generate and send OTP.
                     */
                    } else {

                        /*
                         * Secure 6-digit OTP.
                         */
                        $otp = str_pad(
                            (string) random_int(
                                0,
                                999999
                            ),

                            6,

                            '0',

                            STR_PAD_LEFT
                        );


                        /*
                         * Convert plain OTP into HMAC.
                         */
                        $otpHash =
                            fpOtpHash(
                                $userId,
                                $otp
                            );


                        /*
                         * Invalidate all old unused OTPs.
                         */
                        $invalidate = $db->prepare(
                            "UPDATE
                                auth_password_reset_otps

                             SET
                                consumed_at = NOW()

                             WHERE user_id = :user

                               AND consumed_at
                                   IS NULL"
                        );


                        $invalidate->execute([
                            'user' => $userId
                        ]);


                        /*
                         * Save HASH only.
                         */
                        $insert = $db->prepare(
                            "INSERT INTO
                                auth_password_reset_otps
                             (
                                user_id,
                                otp_hash,
                                expires_at,
                                attempt_count,
                                verified_at,
                                consumed_at
                             )

                             VALUES
                             (
                                :user,
                                :otp_hash,

                                DATE_ADD(
                                    NOW(),
                                    INTERVAL 10 MINUTE
                                ),

                                0,
                                NULL,
                                NULL
                             )"
                        );


                        $insert->execute([
                            'user' =>
                            $userId,

                            'otp_hash' =>
                            $otpHash
                        ]);


                        $otpId = (int) (
                            $db->lastInsertId()
                        );


                        /*
                         * Unique Resend idempotency key.
                         */
                        $idempotencyKey =
                            'bcp-reset-'
                            . $userId
                            . '-'
                            . $otpId;


                        /*
                         * -----------------------------------------
                         * SEND THROUGH CENTRAL MAILER
                         * -----------------------------------------
                         */

                        $sent =
                            bcpSendPasswordResetOtp(
                                (string) $account['email'],

                                (string) $account['username'],

                                $otp,

                                FP_OTP_EXPIRY_MINUTES,

                                $idempotencyKey
                            );


                        /*
                         * Email failed.
                         *
                         * OTP must become unusable.
                         */
                        if (!$sent) {

                            /*
                             * A failed transport is NOT a successful
                             * OTP request. Remove the record so it does
                             * not create a false cooldown or consume the
                             * hourly send quota.
                             */
                            $deleteOtp =
                                $db->prepare(
                                    "DELETE FROM
                                        auth_password_reset_otps

                                     WHERE otp_id = :otp"
                                );


                            $deleteOtp->execute([
                                'otp' => $otpId
                            ]);


                            unset(
                                $_SESSION['fp_sent_at']
                            );


                            $error =
                                'Unable to send the verification email. '
                                . 'Please try again shortly.';

                            $stage =
                                'email';


                            /*
                         * Email sent.
                         */
                        } else {

                            fpSetOtpSession(
                                $userId,

                                $otpId,

                                fpMaskEmail(
                                    (string) $account['email']
                                )
                            );


                            $stage =
                                'otp';


                            $success =
                                'A 6-digit verification code was sent '
                                . 'to your registered email.';
                        }
                    }
                }
            } catch (Throwable $e) {

                error_log(
                    'BCP Forgot Password send OTP error: '
                        . $e->getMessage()
                );


                $error =
                    'Password recovery is temporarily unavailable. '
                    . 'Please try again.';

                $stage =
                    'email';
            }
        }


        /*
     * ========================================================
     * RESEND OTP
     * ========================================================
     */
    } elseif (
        $action === 'resend_otp'
    ) {

        $userId = (int) (
            $_SESSION['fp_user_id']
            ?? 0
        );


        $sentAt = (int) (
            $_SESSION['fp_sent_at']
            ?? 0
        );


        $maskedEmail = (string) (
            $_SESSION['fp_email_masked']
            ?? 'registered email'
        );


        /*
         * ----------------------------------------------------
         * FAKE / UNKNOWN ACCOUNT
         * ----------------------------------------------------
         *
         * Simulate resend without actually sending anything.
         */
        if ($userId <= 0) {

            $elapsed =
                $sentAt > 0
                    ? max(0, time() - $sentAt)
                    : FP_RESEND_SECONDS;


            if (
                $elapsed
                < FP_RESEND_SECONDS
            ) {

                $remaining =
                    FP_RESEND_SECONDS
                    - $elapsed;


                $error =
                    'You can request another code in '
                    . max(
                        1,
                        $remaining
                    )
                    . ' second(s).';
            } else {

                $_SESSION['fp_sent_at'] =
                    time();


                $success =
                    'If the email is registered, '
                    . 'a new verification code has been sent.';
            }


            $stage =
                'otp';


            /*
         * ----------------------------------------------------
         * REAL ACCOUNT
         * ----------------------------------------------------
         */
        } else {

            try {

                /*
                 * Session cooldown.
                 */
                $elapsed =
                    $sentAt > 0
                        ? max(0, time() - $sentAt)
                        : FP_RESEND_SECONDS;


                if (
                    $elapsed
                    < FP_RESEND_SECONDS
                ) {

                    $remaining =
                        FP_RESEND_SECONDS
                        - $elapsed;


                    $error =
                        'You can request another code in '
                        . max(
                            1,
                            $remaining
                        )
                        . ' second(s).';

                    $stage =
                        'otp';
                } else {

                    /*
                     * Revalidate account.
                     */
                    $accountStmt = $db->prepare(
                        "SELECT
                            user_id,
                            username,
                            email

                         FROM auth_users

                         WHERE user_id = :user

                           AND role IN (
                               'ADMIN',
                               'SCHEDULER'
                           )

                           AND is_active = 1

                           AND email_verified_at
                               IS NOT NULL

                         LIMIT 1"
                    );


                    $accountStmt->execute([
                        'user' => $userId
                    ]);


                    $account =
                        $accountStmt->fetch(
                            PDO::FETCH_ASSOC
                        );


                    if (!$account) {

                        fpClearSession();


                        $error =
                            'This password recovery request '
                            . 'is no longer valid.';

                        $stage =
                            'email';
                    } else {

                        /*
                         * Hourly resend protection.
                         */
                        $rate = $db->prepare(
                            "SELECT
                                COUNT(*) AS sends_last_hour

                             FROM
                                auth_password_reset_otps

                             WHERE user_id = :user

                               AND created_at >=
                                   DATE_SUB(
                                       NOW(),
                                       INTERVAL 1 HOUR
                                   )"
                        );


                        $rate->execute([
                            'user' => $userId
                        ]);


                        $rateRow =
                            $rate->fetch(
                                PDO::FETCH_ASSOC
                            );


                        $sendCount = (int) (
                            $rateRow['sends_last_hour']
                            ?? 0
                        );


                        if (
                            $sendCount
                            >= FP_MAX_SENDS_PER_HOUR
                        ) {

                            $error =
                                'Too many verification requests. '
                                . 'Please try again later.';

                            $stage =
                                'otp';
                        } else {

                            /*
                             * New secure OTP.
                             */
                            $otp = str_pad(
                                (string) random_int(
                                    0,
                                    999999
                                ),

                                6,

                                '0',

                                STR_PAD_LEFT
                            );


                            $otpHash =
                                fpOtpHash(
                                    $userId,
                                    $otp
                                );


                            /*
                             * Invalidate previous OTP.
                             */
                            $invalidate =
                                $db->prepare(
                                    "UPDATE
                                        auth_password_reset_otps

                                     SET
                                        consumed_at = NOW()

                                     WHERE user_id = :user

                                       AND consumed_at
                                           IS NULL"
                                );


                            $invalidate->execute([
                                'user' => $userId
                            ]);


                            /*
                             * Create new OTP record.
                             */
                            $insert =
                                $db->prepare(
                                    "INSERT INTO
                                        auth_password_reset_otps
                                     (
                                        user_id,
                                        otp_hash,
                                        expires_at,
                                        attempt_count,
                                        verified_at,
                                        consumed_at
                                     )

                                     VALUES
                                     (
                                        :user,
                                        :otp_hash,

                                        DATE_ADD(
                                            NOW(),
                                            INTERVAL 10 MINUTE
                                        ),

                                        0,
                                        NULL,
                                        NULL
                                     )"
                                );


                            $insert->execute([
                                'user' =>
                                $userId,

                                'otp_hash' =>
                                $otpHash
                            ]);


                            $otpId = (int) (
                                $db->lastInsertId()
                            );


                            $idempotencyKey =
                                'bcp-reset-'
                                . $userId
                                . '-'
                                . $otpId;


                            /*
                             * Send new OTP.
                             */
                            $sent =
                                bcpSendPasswordResetOtp(
                                    (string) $account['email'],

                                    (string) $account['username'],

                                    $otp,

                                    FP_OTP_EXPIRY_MINUTES,

                                    $idempotencyKey
                                );


                            if (!$sent) {

                                /*
                                 * Do not count a transport failure as
                                 * a successful send. Delete the unsent
                                 * OTP record and remove the PHP-session
                                 * cooldown so the user may retry.
                                 */
                                $db->prepare(
                                    "DELETE FROM
                                        auth_password_reset_otps

                                     WHERE otp_id = :otp"
                                )->execute([
                                    'otp' => $otpId
                                ]);


                                unset(
                                    $_SESSION['fp_sent_at']
                                );


                                $_SESSION['fp_otp_id'] = 0;


                                $error =
                                    'Unable to resend the verification code. '
                                    . 'Please try again shortly.';

                                $stage =
                                    'otp';
                            } else {

                                fpSetOtpSession(
                                    $userId,

                                    $otpId,

                                    fpMaskEmail(
                                        (string) $account['email']
                                    )
                                );


                                $success =
                                    'A new verification code '
                                    . 'was sent to your registered email.';

                                $stage =
                                    'otp';
                            }
                        }
                    }
                }
            } catch (Throwable $e) {

                error_log(
                    'BCP Forgot Password resend error: '
                        . $e->getMessage()
                );


                $error =
                    'Unable to resend the verification code '
                    . 'right now.';

                $stage =
                    'otp';
            }
        }


        /*
     * ========================================================
     * VERIFY OTP
     * ========================================================
     */
    } elseif (
        $action === 'verify_otp'
    ) {

        $otp = trim(
            (string) (
                $_POST['otp']
                ?? ''
            )
        );


        $userId = (int) (
            $_SESSION['fp_user_id']
            ?? 0
        );


        $otpId = (int) (
            $_SESSION['fp_otp_id']
            ?? 0
        );


        /*
         * OTP format must be exactly six digits.
         */
        if (
            preg_match(
                '/^\d{6}$/D',
                $otp
            ) !== 1
        ) {

            $error =
                'Enter the complete 6-digit verification code.';

            $stage =
                'otp';


            /*
         * Fake account / invalid session.
         */
        } elseif (
            $userId <= 0
            || $otpId <= 0
        ) {

            $error =
                'Invalid or expired verification code.';

            $stage =
                'otp';
        } else {

            try {

                $db->beginTransaction();


                /*
                 * Lock OTP record while checking.
                 */
                $stmt = $db->prepare(
                    "SELECT
                        otp_id,
                        user_id,
                        otp_hash,
                        expires_at,
                        attempt_count,
                        verified_at,
                        consumed_at,

                        (
                            expires_at > NOW()
                        ) AS is_not_expired

                     FROM
                        auth_password_reset_otps

                     WHERE otp_id = :otp

                       AND user_id = :user

                     LIMIT 1

                     FOR UPDATE"
                );


                $stmt->execute([
                    'otp' =>
                    $otpId,

                    'user' =>
                    $userId
                ]);


                $row =
                    $stmt->fetch(
                        PDO::FETCH_ASSOC
                    );


                /*
                 * Missing / expired / consumed OTP.
                 */
                if (
                    !$row
                    || $row['consumed_at'] !== null
                    || (int) $row['is_not_expired'] !== 1
                ) {

                    $db->rollBack();


                    $error =
                        'This verification code has expired '
                        . 'or is no longer valid.';

                    $stage =
                        'otp';


                    /*
                 * Already exceeded max attempts.
                 */
                } elseif (
                    (int) $row['attempt_count']
                    >= FP_OTP_MAX_ATTEMPTS
                ) {

                    $consume =
                        $db->prepare(
                            "UPDATE
                                auth_password_reset_otps

                             SET
                                consumed_at = NOW()

                             WHERE otp_id = :otp"
                        );


                    $consume->execute([
                        'otp' => $otpId
                    ]);


                    $db->commit();


                    $error =
                        'Too many incorrect verification attempts. '
                        . 'Request a new OTP.';

                    $stage =
                        'otp';
                } else {

                    /*
                     * Hash submitted OTP using same secret.
                     */
                    $expectedHash =
                        fpOtpHash(
                            $userId,
                            $otp
                        );


                    $validOtp =
                        hash_equals(
                            (string) $row['otp_hash'],

                            $expectedHash
                        );


                    /*
                     * -----------------------------------------
                     * WRONG OTP
                     * -----------------------------------------
                     */

                    if (!$validOtp) {

                        $newAttemptCount =
                            (int) $row['attempt_count'] + 1;


                        $consumeNow =
                            $newAttemptCount
                            >= FP_OTP_MAX_ATTEMPTS;


                        $update =
                            $db->prepare(
                                "UPDATE
                                    auth_password_reset_otps

                                 SET
                                    attempt_count =
                                        :attempts,

                                    consumed_at =
                                        CASE
                                            WHEN :consume_now = 1
                                            THEN NOW()
                                            ELSE consumed_at
                                        END

                                 WHERE otp_id = :otp"
                            );


                        $update->execute([
                            'attempts' =>
                            $newAttemptCount,

                            'consume_now' =>
                            $consumeNow
                                ? 1
                                : 0,

                            'otp' =>
                            $otpId
                        ]);


                        $db->commit();


                        $remaining = max(
                            0,

                            FP_OTP_MAX_ATTEMPTS
                                - $newAttemptCount
                        );


                        if ($remaining <= 0) {

                            $error =
                                'Too many incorrect verification attempts. '
                                . 'Request a new OTP.';
                        } else {

                            $error =
                                'Incorrect verification code. '
                                . $remaining
                                . ' attempt(s) remaining.';
                        }


                        $stage =
                            'otp';


                        /*
                     * -----------------------------------------
                     * CORRECT OTP
                     * -----------------------------------------
                     */
                    } else {

                        $verify =
                            $db->prepare(
                                "UPDATE
                                    auth_password_reset_otps

                                 SET
                                    verified_at = NOW()

                                 WHERE otp_id = :otp

                                   AND consumed_at
                                       IS NULL"
                            );


                        $verify->execute([
                            'otp' => $otpId
                        ]);


                        $db->commit();


                        $_SESSION['fp_verified'] = true;


                        $_SESSION['fp_stage'] = 'password';


                        $stage =
                            'password';


                        /*
                         * Rotate session after successful
                         * identity verification.
                         */
                        session_regenerate_id(
                            true
                        );


                        $success =
                            'Email verification successful. '
                            . 'You may now create a new password.';
                    }
                }
            } catch (Throwable $e) {

                if ($db->inTransaction()) {
                    $db->rollBack();
                }


                error_log(
                    'BCP Forgot Password OTP verify error: '
                        . $e->getMessage()
                );


                $error =
                    'Unable to verify the code right now. '
                    . 'Please try again.';

                $stage =
                    'otp';
            }
        }


        /*
     * ========================================================
     * RESET PASSWORD
     * ========================================================
     */
    } elseif (
        $action === 'reset_password'
    ) {

        $userId = (int) (
            $_SESSION['fp_user_id']
            ?? 0
        );


        $otpId = (int) (
            $_SESSION['fp_otp_id']
            ?? 0
        );


        $verified =
            (
                $_SESSION['fp_verified']
                ?? false
            ) === true;


        $newPassword =
            (string) (
                $_POST['new_password']
                ?? ''
            );


        $confirmPassword =
            (string) (
                $_POST['confirm_password']
                ?? ''
            );


        /*
         * Recovery state missing.
         */
        if (
            $userId <= 0
            || $otpId <= 0
            || !$verified
        ) {

            fpClearSession();


            $error =
                'Your password recovery session is no longer valid. '
                . 'Please start again.';

            $stage =
                'email';


            /*
         * Password mismatch.
         */
        } elseif (
            $newPassword
            !== $confirmPassword
        ) {

            $error =
                'The new password and confirmation do not match.';

            $stage =
                'password';


            /*
         * Password policy failure.
         */
        } elseif (
            !fpStrongPassword(
                $newPassword
            )
        ) {

            $error =
                'Use 12–128 characters with at least one '
                . 'uppercase letter, lowercase letter, and number.';

            $stage =
                'password';
        } else {

            try {

                $db->beginTransaction();


                /*
                 * ---------------------------------------------
                 * RECHECK VERIFIED OTP
                 * ---------------------------------------------
                 *
                 * Never trust session alone.
                 */
                $otpCheck =
                    $db->prepare(
                        "SELECT
                            otp_id,
                            verified_at,
                            consumed_at,

                            (
                                expires_at > NOW()
                            ) AS is_not_expired

                         FROM
                            auth_password_reset_otps

                         WHERE otp_id = :otp

                           AND user_id = :user

                         LIMIT 1

                         FOR UPDATE"
                    );


                $otpCheck->execute([
                    'otp' =>
                    $otpId,

                    'user' =>
                    $userId
                ]);


                $otpRow =
                    $otpCheck->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (
                    !$otpRow
                    || $otpRow['verified_at'] === null
                    || $otpRow['consumed_at'] !== null
                    || (int) $otpRow['is_not_expired'] !== 1
                ) {

                    throw new RuntimeException(
                        'RESET_OTP_INVALID'
                    );
                }


                /*
                 * ---------------------------------------------
                 * RECHECK ACCOUNT
                 * ---------------------------------------------
                 */

                $accountCheck =
                    $db->prepare(
                        "SELECT
                            user_id,
                            username,
                            password_hash,
                            role,
                            is_active

                         FROM auth_users

                         WHERE user_id = :user

                           AND role IN (
                               'ADMIN',
                               'SCHEDULER'
                           )

                           AND is_active = 1

                         LIMIT 1

                         FOR UPDATE"
                    );


                $accountCheck->execute([
                    'user' => $userId
                ]);


                $accountRow =
                    $accountCheck->fetch(
                        PDO::FETCH_ASSOC
                    );


                if (!$accountRow) {

                    throw new RuntimeException(
                        'RESET_ACCOUNT_INVALID'
                    );
                }


                /*
                 * New password cannot be equal to old password.
                 */
                if (
                    password_verify(
                        $newPassword,

                        (string) $accountRow['password_hash']
                    )
                ) {

                    throw new RuntimeException(
                        'RESET_PASSWORD_REUSED'
                    );
                }


                /*
                 * Create secure password hash.
                 */
                $newPasswordHash =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );


                if (
                    !is_string(
                        $newPasswordHash
                    )
                    || $newPasswordHash === ''
                ) {

                    throw new RuntimeException(
                        'RESET_HASH_FAILED'
                    );
                }


                /*
                 * ---------------------------------------------
                 * UPDATE ACCOUNT
                 * ---------------------------------------------
                 *
                 * Also clear progressive login lockout.
                 */
                $updateUser =
                    $db->prepare(
                        "UPDATE auth_users

                         SET
                            password_hash =
                                :password_hash,

                            failed_attempts = 0,

                            locked_until = NULL

                         WHERE user_id = :user"
                    );


                $updateUser->execute([
                    'password_hash' =>
                    $newPasswordHash,

                    'user' =>
                    $userId
                ]);


                /*
                 * Consume current OTP.
                 */
                $consumeOtp =
                    $db->prepare(
                        "UPDATE
                            auth_password_reset_otps

                         SET
                            consumed_at = NOW()

                         WHERE otp_id = :otp

                           AND user_id = :user"
                    );


                $consumeOtp->execute([
                    'otp' =>
                    $otpId,

                    'user' =>
                    $userId
                ]);


                /*
                 * Invalidate every other outstanding OTP.
                 */
                $invalidateOthers =
                    $db->prepare(
                        "UPDATE
                            auth_password_reset_otps

                         SET
                            consumed_at = NOW()

                         WHERE user_id = :user

                           AND consumed_at
                               IS NULL"
                    );


                $invalidateOthers->execute([
                    'user' => $userId
                ]);


                $db->commit();


                /*
                 * Clear recovery session.
                 */
                fpClearSession();


                /*
                 * Rotate session after security-sensitive action.
                 */
                session_regenerate_id(
                    true
                );


                $_SESSION['fp_stage'] =
                    'done';


                $stage =
                    'done';


                $success =
                    'Your password has been reset successfully. '
                    . 'You can now sign in using your new password.';
            } catch (Throwable $e) {

                if ($db->inTransaction()) {

                    $db->rollBack();
                }


                if (
                    $e->getMessage()
                    === 'RESET_PASSWORD_REUSED'
                ) {

                    $error =
                        'Your new password must be different '
                        . 'from your previous password.';

                    $stage =
                        'password';
                } elseif (
                    $e->getMessage()
                    === 'RESET_OTP_INVALID'
                ) {

                    fpClearSession();


                    $error =
                        'Your verified OTP has expired. '
                        . 'Please start the recovery process again.';

                    $stage =
                        'email';
                } else {

                    error_log(
                        'BCP Forgot Password reset error: '
                            . $e->getMessage()
                    );


                    fpClearSession();


                    $error =
                        'Unable to reset your password right now. '
                        . 'Please start again.';

                    $stage =
                        'email';
                }
            }
        }


        /*
     * ========================================================
     * UNKNOWN ACTION
     * ========================================================
     */
    } else {

        $error =
            'Invalid password recovery action.';
    }
}


/*
 * ============================================================
 * CSRF TOKEN
 * ============================================================
 */

$csrf = authCsrf();


/*
 * ============================================================
 * OTP PAGE STATE
 * ============================================================
 */

$maskedEmail = (string) (
    $_SESSION['fp_email_masked']
    ?? ''
);


$sentAt = (int) (
    $_SESSION['fp_sent_at']
    ?? 0
);


if ($sentAt > 0) {

    $resendRemaining = max(
        0,

        FP_RESEND_SECONDS
            - (
                time()
                - $sentAt
            )
    );
} else {

    /*
     * No successful email has been sent in this session,
     * therefore there is no client-side resend cooldown.
     */
    $resendRemaining = 0;
}

?>
<!doctype html>

<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">

    <meta
        name="robots"
        content="noindex,nofollow">


    <title>
        Forgot Password | BCP Scheduling System
    </title>


    <link
        rel="icon"
        href="app/assets/images/BCP_LOGO.png"
        type="image/png">


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com">


    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin>


    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


    <link
        rel="stylesheet"
        href="app/assets/css/login.css">


    <style>
        .forgot-card {
            width: 100%;
            max-width: 470px;
        }


        .forgot-icon {
            width: 56px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            border-radius: 15px;
            background: #edf3ff;
            color: #1a3a8c;
            font-size: 22px;
        }


        .forgot-step {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 0 0 18px;
            padding: 11px 13px;
            border: 1px solid #e5eaf2;
            border-radius: 10px;
            background: #f7f9fc;
            color: #64748b;
            font-size: 12px;
            font-weight: 700;
        }


        .forgot-success {
            margin: 0 0 16px;
            padding: 13px 14px;
            border: 1px solid #bde8ce;
            border-radius: 10px;
            background: #effaf4;
            color: #17643a;
            font-size: 13px;
            line-height: 1.55;
        }


        .forgot-success i {
            margin-right: 7px;
        }


        .forgot-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 12px;
        }


        .forgot-secondary {
            width: 100%;
            min-height: 44px;
            padding: 11px 13px;
            border: 1px solid #dce3ee;
            border-radius: 10px;
            background: #ffffff;
            color: #34445d;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition:
                border-color .2s ease,
                background .2s ease,
                opacity .2s ease;
        }


        .forgot-secondary:hover:not(:disabled) {
            border-color: #b7c7e6;
            background: #f7f9fd;
        }


        .forgot-secondary:disabled {
            cursor: not-allowed;
            opacity: .6;
        }


        .forgot-back {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            margin-top: 20px;
            color: #355aa8;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
        }


        .forgot-back:hover {
            text-decoration: underline;
        }


        .forgot-email-hint {
            margin: -4px 0 16px;
            color: #758196;
            font-size: 12px;
            line-height: 1.55;
        }


        .otp-input {
            text-align: center;
            font-size: 26px !important;
            font-weight: 800 !important;
            letter-spacing: 10px;
            font-variant-numeric: tabular-nums;
        }


        .otp-meta {
            margin-top: 13px;
            color: #718096;
            font-size: 12px;
            line-height: 1.55;
            text-align: center;
        }


        .password-field-wrap {
            position: relative;
        }


        .password-field-wrap input {
            width: 100%;
            padding-right: 43px !important;
            box-sizing: border-box;
        }


        .password-toggle {
            position: absolute;
            top: 50%;
            right: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
            border: 0;
            background: transparent;
            color: #647b9e;
            cursor: pointer;
            transform: translateY(-50%);
        }


        .password-rules {
            display: grid;
            gap: 7px;
            margin: 12px 0 18px;
            padding: 13px 14px;
            border: 1px solid #e5eaf2;
            border-radius: 10px;
            background: #f8fafc;
            color: #6a778b;
            font-size: 12px;
        }


        .password-rules strong {
            margin-bottom: 2px;
            color: #3f4c60;
            font-size: 12px;
        }


        .password-rules span {
            display: flex;
            align-items: center;
            gap: 7px;
        }


        .password-rules span.is-ok {
            color: #18734a;
        }


        .password-rules span.is-ok i {
            color: #24925f;
        }


        .reset-complete-icon {
            background: #ecf9f1;
            color: #238a57;
        }


        @media (max-width: 520px) {

            .forgot-actions {
                grid-template-columns: 1fr;
            }

        }
    </style>

</head>


<body class="bcp-login-page">


    <main class="bcp-login">


        <!-- ======================================================
         LEFT PANEL
         ====================================================== -->

        <section class="login-intro">


            <div class="brand">

                <div class="brand-icon">
                    BCP
                </div>


                <div>

                    <strong>
                        BESTLINK COLLEGE
                    </strong>

                    <span>
                        OF THE PHILIPPINES
                    </span>

                </div>

            </div>


            <div class="intro-content">


                <span class="intro-badge">
                    ACCOUNT SECURITY
                </span>


                <h1>

                    Secure account<br>

                    <span>
                        recovery.
                    </span>

                </h1>


                <p>

                    Recover access to the BCP Academic
                    Scheduling Platform using your registered
                    and verified account email.

                </p>


            </div>


            <div class="intro-footer">

                BCP Class Scheduling System

            </div>


        </section>


        <!-- ======================================================
         RIGHT PANEL
         ====================================================== -->

        <section class="login-form-area">


            <div class="login-card forgot-card">


                <!-- ICON -->

                <div
                    class="
                    forgot-icon
                    <?= $stage === 'done'
                        ? 'reset-complete-icon'
                        : '' ?>
                ">

                    <?php if ($stage === 'email'): ?>

                        <i
                            class="fa-regular fa-envelope"
                            aria-hidden="true"></i>


                    <?php elseif ($stage === 'otp'): ?>

                        <i
                            class="fa-solid fa-shield-halved"
                            aria-hidden="true"></i>


                    <?php elseif ($stage === 'password'): ?>

                        <i
                            class="fa-solid fa-key"
                            aria-hidden="true"></i>


                    <?php else: ?>

                        <i
                            class="fa-solid fa-circle-check"
                            aria-hidden="true"></i>

                    <?php endif; ?>

                </div>


                <!-- HEADER -->

                <div class="login-card-header">


                    <span class="welcome-label">

                        PASSWORD RECOVERY

                    </span>


                    <?php if ($stage === 'email'): ?>


                        <h2>
                            Forgot your password?
                        </h2>


                        <p>

                            Enter the registered recovery email
                            for your Admin or Scheduler account.

                        </p>


                    <?php elseif ($stage === 'otp'): ?>


                        <h2>
                            Enter verification code
                        </h2>


                        <p>

                            Enter the 6-digit OTP sent to

                            <strong>
                                <?= fpEscape(
                                    $maskedEmail
                                ) ?>
                            </strong>.

                        </p>


                    <?php elseif ($stage === 'password'): ?>


                        <h2>
                            Create a new password
                        </h2>


                        <p>

                            Your email has been verified.
                            Create a secure new password
                            for your account.

                        </p>


                    <?php else: ?>


                        <h2>
                            Password reset complete
                        </h2>


                        <p>

                            Your account password has been
                            updated successfully.

                        </p>


                    <?php endif; ?>


                </div>


                <!-- ERROR -->

                <?php if ($error !== ''): ?>

                    <div
                        class="login-error"
                        role="alert">

                        <?= fpEscape(
                            $error
                        ) ?>

                    </div>

                <?php endif; ?>


                <!-- SUCCESS -->

                <?php if ($success !== ''): ?>

                    <div
                        class="forgot-success"
                        role="status"
                        aria-live="polite">

                        <i
                            class="fa-solid fa-circle-check"
                            aria-hidden="true"></i>

                        <?= fpEscape(
                            $success
                        ) ?>

                    </div>

                <?php endif; ?>


                <!-- ==================================================
                 STAGE 1: EMAIL
                 ================================================== -->

                <?php if ($stage === 'email'): ?>


                    <div class="forgot-step">

                        <i
                            class="fa-solid fa-envelope"
                            aria-hidden="true"></i>

                        Step 1 of 3 · Verify registered email

                    </div>


                    <form
                        method="post"
                        autocomplete="off">


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= fpEscape(
                                        $csrf
                                    ) ?>">


                        <input
                            type="hidden"
                            name="action"
                            value="send_otp">


                        <div class="form-group">


                            <label for="email">

                                Registered email

                            </label>


                            <input
                                type="email"
                                id="email"
                                name="email"
                                placeholder="Enter registered email"
                                maxlength="254"
                                autocomplete="email"
                                required
                                autofocus>


                        </div>


                        <p class="forgot-email-hint">

                            Only the email registered and verified
                            for an active Admin or Scheduler account
                            can receive a password reset OTP.

                        </p>


                        <button
                            class="login-button"
                            type="submit">

                            Send verification code

                            <span aria-hidden="true">
                                →
                            </span>

                        </button>


                    </form>


                    <!-- ==================================================
                 STAGE 2: OTP
                 ================================================== -->

                <?php elseif ($stage === 'otp'): ?>


                    <div class="forgot-step">

                        <i
                            class="fa-solid fa-shield-halved"
                            aria-hidden="true"></i>

                        Step 2 of 3 · Verify one-time password

                    </div>


                    <form
                        method="post"
                        autocomplete="off">


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= fpEscape(
                                        $csrf
                                    ) ?>">


                        <input
                            type="hidden"
                            name="action"
                            value="verify_otp">


                        <div class="form-group">


                            <label for="otp">

                                6-digit verification code

                            </label>


                            <input
                                class="otp-input"
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


                        <button
                            class="login-button"
                            type="submit">

                            Verify code

                            <span aria-hidden="true">
                                →
                            </span>

                        </button>


                    </form>


                    <div class="forgot-actions">


                        <!-- CHANGE EMAIL -->

                        <form
                            method="post">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= fpEscape(
                                            $csrf
                                        ) ?>">


                            <input
                                type="hidden"
                                name="action"
                                value="restart">


                            <button
                                class="forgot-secondary"
                                type="submit">

                                <i
                                    class="fa-solid fa-arrow-left"
                                    aria-hidden="true"></i>

                                Use another email

                            </button>


                        </form>


                        <!-- RESEND OTP -->

                        <form
                            method="post"
                            id="resendOtpForm">

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= fpEscape(
                                            $csrf
                                        ) ?>">


                            <input
                                type="hidden"
                                name="action"
                                value="resend_otp">


                            <button
                                class="forgot-secondary"
                                type="submit"
                                id="resendOtpButton"
                                <?= $resendRemaining > 0
                                    ? 'disabled'
                                    : '' ?>>


                                <?php if (
                                    $resendRemaining > 0
                                ): ?>

                                    Resend in

                                    <span
                                        id="resendCountdown"
                                        data-seconds="<?= (int) $resendRemaining ?>">

                                        <?= (int) $resendRemaining ?>

                                    </span>s


                                <?php else: ?>

                                    <i
                                        class="fa-solid fa-rotate"
                                        aria-hidden="true"></i>

                                    Resend OTP

                                <?php endif; ?>


                            </button>


                        </form>


                    </div>


                    <div class="otp-meta">

                        OTP expires after
                        <?= FP_OTP_EXPIRY_MINUTES ?>
                        minutes.

                        <br>

                        Maximum
                        <?= FP_OTP_MAX_ATTEMPTS ?>
                        incorrect verification attempts.

                    </div>


                    <!-- ==================================================
                 STAGE 3: NEW PASSWORD
                 ================================================== -->

                <?php elseif ($stage === 'password'): ?>


                    <div class="forgot-step">

                        <i
                            class="fa-solid fa-key"
                            aria-hidden="true"></i>

                        Step 3 of 3 · Create new password

                    </div>


                    <form
                        method="post"
                        autocomplete="off">


                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= fpEscape(
                                        $csrf
                                    ) ?>">


                        <input
                            type="hidden"
                            name="action"
                            value="reset_password">


                        <!-- NEW PASSWORD -->

                        <div class="form-group">


                            <label for="newPassword">

                                New password

                            </label>


                            <div class="password-field-wrap">


                                <input
                                    type="password"
                                    id="newPassword"
                                    name="new_password"
                                    placeholder="Enter new password"
                                    autocomplete="new-password"
                                    maxlength="128"
                                    required
                                    autofocus>


                                <button
                                    class="password-toggle"
                                    type="button"
                                    data-password-toggle="newPassword"
                                    aria-label="Show new password">

                                    <i
                                        class="fa-regular fa-eye"
                                        aria-hidden="true"></i>

                                </button>


                            </div>


                        </div>


                        <!-- CONFIRM PASSWORD -->

                        <div class="form-group">


                            <label for="confirmPassword">

                                Confirm new password

                            </label>


                            <div class="password-field-wrap">


                                <input
                                    type="password"
                                    id="confirmPassword"
                                    name="confirm_password"
                                    placeholder="Confirm new password"
                                    autocomplete="new-password"
                                    maxlength="128"
                                    required>


                                <button
                                    class="password-toggle"
                                    type="button"
                                    data-password-toggle="confirmPassword"
                                    aria-label="Show password confirmation">

                                    <i
                                        class="fa-regular fa-eye"
                                        aria-hidden="true"></i>

                                </button>


                            </div>


                        </div>


                        <!-- RULES -->

                        <div
                            class="password-rules"
                            aria-label="Password requirements">


                            <strong>
                                Password requirements
                            </strong>


                            <span id="ruleLength">

                                <i
                                    class="fa-regular fa-circle"
                                    aria-hidden="true"></i>

                                12–128 characters

                            </span>


                            <span id="ruleUpper">

                                <i
                                    class="fa-regular fa-circle"
                                    aria-hidden="true"></i>

                                At least one uppercase letter

                            </span>


                            <span id="ruleLower">

                                <i
                                    class="fa-regular fa-circle"
                                    aria-hidden="true"></i>

                                At least one lowercase letter

                            </span>


                            <span id="ruleNumber">

                                <i
                                    class="fa-regular fa-circle"
                                    aria-hidden="true"></i>

                                At least one number

                            </span>


                            <span id="ruleMatch">

                                <i
                                    class="fa-regular fa-circle"
                                    aria-hidden="true"></i>

                                Passwords match

                            </span>


                        </div>


                        <button
                            class="login-button"
                            type="submit">

                            Reset password

                            <span aria-hidden="true">
                                →
                            </span>

                        </button>


                    </form>


                    <!-- ==================================================
                 DONE
                 ================================================== -->

                <?php else: ?>


                    <a
                        class="login-button"
                        href="index.php"
                        style="
                        display:flex;
                        align-items:center;
                        justify-content:center;
                        text-decoration:none;
                    ">

                        Return to Sign In

                        <span aria-hidden="true">
                            →
                        </span>

                    </a>


                <?php endif; ?>


                <!-- BACK TO LOGIN -->

                <?php if ($stage !== 'done'): ?>

                    <a
                        class="forgot-back"
                        href="index.php">

                        <i
                            class="fa-solid fa-arrow-left"
                            aria-hidden="true"></i>

                        Back to Sign In

                    </a>

                <?php endif; ?>


            </div>


            <div class="login-bottom">

                &copy;
                <?= date('Y') ?>

                Bestlink College of the Philippines

            </div>


        </section>


    </main>


    <script>
        (() => {

            'use strict';


            /*
             * ========================================================
             * OTP NUMERIC ONLY
             * ========================================================
             */

            const otpInput =
                document.getElementById(
                    'otp'
                );


            if (otpInput) {

                otpInput.addEventListener(
                    'input',
                    () => {

                        otpInput.value =
                            otpInput.value
                            .replace(
                                /\D/g,
                                ''
                            )
                            .slice(
                                0,
                                6
                            );

                    }
                );

            }


            /*
             * ========================================================
             * RESEND COUNTDOWN
             * ========================================================
             */

            const resendCountdown =
                document.getElementById(
                    'resendCountdown'
                );


            const resendButton =
                document.getElementById(
                    'resendOtpButton'
                );


            if (
                resendCountdown &&
                resendButton
            ) {

                let seconds =
                    Number(
                        resendCountdown.dataset.seconds ||
                        0
                    );


                const tick = () => {

                    seconds =
                        Math.max(
                            0,
                            seconds
                        );


                    resendCountdown.textContent =
                        String(seconds);


                    if (seconds <= 0) {

                        resendButton.disabled =
                            false;


                        resendButton.innerHTML =
                            '<i class="fa-solid fa-rotate" aria-hidden="true"></i> Resend OTP';


                        return;
                    }


                    seconds--;


                    window.setTimeout(
                        tick,
                        1000
                    );

                };


                tick();

            }


            /*
             * ========================================================
             * SHOW / HIDE PASSWORD
             * ========================================================
             */

            document
                .querySelectorAll(
                    '[data-password-toggle]'
                )
                .forEach(
                    button => {

                        button.addEventListener(
                            'click',
                            () => {

                                const inputId =
                                    button.dataset.passwordToggle;


                                const input =
                                    document.getElementById(
                                        inputId
                                    );


                                if (!input) {
                                    return;
                                }


                                const show =
                                    input.type ===
                                    'password';


                                input.type =
                                    show ?
                                    'text' :
                                    'password';


                                const icon =
                                    button.querySelector(
                                        'i'
                                    );


                                if (icon) {

                                    icon.className =
                                        show ?
                                        'fa-regular fa-eye-slash' :
                                        'fa-regular fa-eye';
                                }


                                button.setAttribute(
                                    'aria-label',

                                    show ?
                                    'Hide password' :
                                    'Show password'
                                );


                                input.focus();

                            }
                        );

                    }
                );


            /*
             * ========================================================
             * PASSWORD REQUIREMENTS
             * ========================================================
             */

            const newPassword =
                document.getElementById(
                    'newPassword'
                );


            const confirmPassword =
                document.getElementById(
                    'confirmPassword'
                );


            const setRule = (
                id,
                valid
            ) => {

                const row =
                    document.getElementById(
                        id
                    );


                if (!row) {
                    return;
                }


                row.classList.toggle(
                    'is-ok',
                    valid
                );


                const icon =
                    row.querySelector(
                        'i'
                    );


                if (icon) {

                    icon.className =
                        valid ?
                        'fa-solid fa-circle-check' :
                        'fa-regular fa-circle';
                }

            };


            const validatePassword = () => {

                const value =
                    newPassword?.value ||
                    '';


                const confirmation =
                    confirmPassword?.value ||
                    '';


                setRule(
                    'ruleLength',

                    value.length >= 12 &&
                    value.length <= 128
                );


                setRule(
                    'ruleUpper',

                    /[A-Z]/.test(
                        value
                    )
                );


                setRule(
                    'ruleLower',

                    /[a-z]/.test(
                        value
                    )
                );


                setRule(
                    'ruleNumber',

                    /[0-9]/.test(
                        value
                    )
                );


                setRule(
                    'ruleMatch',

                    value.length > 0 &&
                    value === confirmation
                );

            };


            newPassword?.addEventListener(
                'input',
                validatePassword
            );


            confirmPassword?.addEventListener(
                'input',
                validatePassword
            );


        })();
    </script>


</body>

</html>