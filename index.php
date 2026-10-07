<?php

declare(strict_types=1);

require_once __DIR__ . '/app/shared/auth.php';
require_once __DIR__ . '/app/shared/login-otp.php';

authStart();
authNoCache();


$dashboard = 'app/dashboard/dashboard.php';


/*
 * ============================================================
 * LOGIN SECURITY CONFIGURATION
 * ============================================================
 *
 * Progressive account lock:
 *
 * 3 failed attempts  = 15 minutes
 * 6 failed attempts  = 30 minutes
 * 9 failed attempts  = 45 minutes
 * 12 failed attempts = 60 minutes
 * ...
 *
 * Maximum lock:
 * 24 hours
 *
 * Successful login resets failed_attempts to zero.
 * ============================================================
 */

const LOGIN_ATTEMPTS_PER_LOCK = 3;

const LOGIN_LOCK_STEP_MINUTES = 15;

const LOGIN_MAX_LOCK_MINUTES = 1440;


/*
 * Dummy password hash.
 *
 * Used for usernames that do not exist.
 *
 * This helps reduce timing differences
 * between valid and invalid usernames.
 */
const LOGIN_DUMMY_HASH =
'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';


/*
 * ============================================================
 * ALREADY LOGGED IN
 * ============================================================
 */

if (authLoggedIn()) {

    header(
        'Location: ' . $dashboard,
        true,
        303
    );

    exit;
}


/*
 * ============================================================
 * INITIAL UI STATE
 * ============================================================
 */

$error = '';

$showAttemptStatus = false;

$attemptNumber = 0;

$attemptsRemaining =
    LOGIN_ATTEMPTS_PER_LOCK;

$isLockedUi = false;

$lockSecondsRemaining = 0;

$submittedUsername = '';


/*
 * ============================================================
 * HANDLE LOGIN POST
 * ============================================================
 */

if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET')
    === 'POST'
) {

    $username = trim(
        (string) (
            $_POST['username']
            ?? ''
        )
    );


    $submittedUsername =
        $username;


    $password = (string) (
        $_POST['password']
        ?? ''
    );


    $token =
        $_POST['csrf_token']
        ?? null;


    /*
     * ========================================================
     * 1. CSRF VALIDATION
     * ========================================================
     */

    if (!authCsrfValid($token)) {

        http_response_code(403);

        $error =
            'Session expired. Refresh the page and try again.';


        /*
     * ========================================================
     * 2. INPUT VALIDATION
     * ========================================================
     */
    } elseif (
        $username === ''
        || $password === ''
        || strlen($username) > 80
        || strlen($password) > 1024
    ) {

        $error =
            'Invalid username or password.';


        /*
     * ========================================================
     * 3. DATABASE AUTHENTICATION
     * ========================================================
     */
    } else {

        try {

            $db = authDb();

            $db->setAttribute(
                PDO::ATTR_ERRMODE,
                PDO::ERRMODE_EXCEPTION
            );


            /*
             * ==================================================
             * READ ACCOUNT
             * ==================================================
             */

            $stmt = $db->prepare(
                'SELECT
                    user_id,
                    username,
                    email,
                    email_verified_at,
                    password_hash,
                    role,
                    is_active,
                    failed_attempts,
                    locked_until,

                    (
                        locked_until IS NOT NULL
                        AND locked_until > NOW()
                    ) AS is_locked,

                    CASE

                        WHEN
                            locked_until IS NOT NULL
                            AND locked_until > NOW()

                        THEN TIMESTAMPDIFF(
                            SECOND,
                            NOW(),
                            locked_until
                        )

                        ELSE 0

                    END AS lock_seconds_remaining

                 FROM auth_users

                 WHERE username = :username

                   AND role IN (
                       "ADMIN",
                       "SCHEDULER"
                   )

                 LIMIT 1'
            );


            $stmt->execute([
                'username' => $username
            ]);


            $user =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /*
             * ==================================================
             * USERNAME DOES NOT EXIST
             * ==================================================
             */

            if (!$user) {

                /*
                 * Still perform password_verify().
                 *
                 * Helps reduce timing differences
                 * used for username enumeration.
                 */
                password_verify(
                    $password,
                    LOGIN_DUMMY_HASH
                );


                $error =
                    'Invalid username or password, '
                    . 'or account temporarily locked.';


                /*
             * ==================================================
             * ACCOUNT CURRENTLY LOCKED
             * ==================================================
             */
            } elseif (
                (int) $user['is_locked']
                === 1
            ) {

                /*
                 * IMPORTANT:
                 *
                 * Do not increase failed_attempts
                 * while account is already locked.
                 *
                 * Otherwise another person could
                 * repeatedly extend someone else\'s
                 * lockout.
                 */

                $isLockedUi =
                    true;


                $lockSecondsRemaining =
                    max(
                        1,
                        (int) (
                            $user['lock_seconds_remaining']
                            ?? 0
                        )
                    );


                $error =
                    'Too many failed login attempts. '
                    . 'This account is temporarily locked.';


                /*
             * ==================================================
             * ACCOUNT IS NOT LOCKED
             * ==================================================
             */
            } else {

                /*
                 * Always verify the password
                 * using password_verify().
                 */
                $passwordCorrect =
                    password_verify(
                        $password,
                        (string) $user['password_hash']
                    );


                $accountActive =
                    (int) $user['is_active'] === 1;


                /*
                 * =================================================
                 * FAILED LOGIN
                 * =================================================
                 */

                if (
                    !$passwordCorrect
                    || !$accountActive
                ) {

                    /*
                     * Only active, existing accounts
                     * accumulate failed login attempts.
                     */
                    if ($accountActive) {

                        $currentAttempts =
                            (int) (
                                $user['failed_attempts']
                                ?? 0
                            );


                        $nextAttempts =
                            $currentAttempts + 1;


                        /*
                         * Calculate the attempt number
                         * inside the current 3-attempt cycle.
                         *
                         * Examples:
                         *
                         * total attempt 1 -> 1 of 3
                         * total attempt 2 -> 2 of 3
                         * total attempt 3 -> lock
                         *
                         * total attempt 4 -> 1 of 3
                         * total attempt 5 -> 2 of 3
                         * total attempt 6 -> lock
                         */
                        $cycleAttempt =
                            (
                                (
                                    $nextAttempts - 1
                                )
                                % LOGIN_ATTEMPTS_PER_LOCK
                            )
                            + 1;


                        $attemptNumber =
                            $cycleAttempt;


                        $attemptsRemaining =
                            max(
                                0,

                                LOGIN_ATTEMPTS_PER_LOCK
                                    - $cycleAttempt
                            );


                        $showAttemptStatus =
                            true;


                        /*
                         * Every third failed attempt
                         * triggers a lock.
                         */
                        $shouldLock =
                            (
                                $nextAttempts
                                % LOGIN_ATTEMPTS_PER_LOCK
                            ) === 0;


                        /*
                         * =========================================
                         * LOCK ACCOUNT
                         * =========================================
                         */

                        if ($shouldLock) {

                            /*
                             * Determine progressive lock level.
                             *
                             * total failed attempts:
                             *
                             * 3 -> level 1
                             * 6 -> level 2
                             * 9 -> level 3
                             */
                            $lockLevel = intdiv(
                                $nextAttempts,
                                LOGIN_ATTEMPTS_PER_LOCK
                            );


                            /*
                             * 15, 30, 45, 60...
                             *
                             * Max = 1440 minutes / 24 hours.
                             */
                            $lockMinutes =
                                min(
                                    LOGIN_MAX_LOCK_MINUTES,

                                    $lockLevel
                                        * LOGIN_LOCK_STEP_MINUTES
                                );


                            $lockMinutes =
                                (int) $lockMinutes;


                            /*
                             * $lockMinutes is generated
                             * internally by the server.
                             *
                             * It is not user input.
                             */
                            $update = $db->prepare(
                                'UPDATE auth_users

                                 SET
                                    failed_attempts =
                                        :attempts,

                                    locked_until =
                                        DATE_ADD(
                                            NOW(),
                                            INTERVAL '
                                    . $lockMinutes .
                                    ' MINUTE
                                        )

                                 WHERE user_id = :id'
                            );


                            $update->execute([
                                'attempts' =>
                                $nextAttempts,

                                'id' =>
                                (int) $user['user_id']
                            ]);


                            /*
                             * Update UI.
                             */
                            $isLockedUi =
                                true;


                            $lockSecondsRemaining =
                                $lockMinutes * 60;


                            $attemptNumber =
                                LOGIN_ATTEMPTS_PER_LOCK;


                            $attemptsRemaining =
                                0;


                            $error =
                                'Too many failed login attempts. '
                                . 'This account has been temporarily locked.';


                            /*
                         * =========================================
                         * NORMAL FAILED ATTEMPT
                         * =========================================
                         */
                        } else {

                            $update = $db->prepare(
                                'UPDATE auth_users

                                 SET
                                    failed_attempts =
                                        :attempts,

                                    locked_until =
                                        NULL

                                 WHERE user_id = :id'
                            );


                            $update->execute([
                                'attempts' =>
                                $nextAttempts,

                                'id' =>
                                (int) $user['user_id']
                            ]);


                            $error =
                                'Invalid username or password.';
                        }


                        /*
                     * Inactive account.
                     */
                    } else {

                        $error =
                            'Invalid username or password, '
                            . 'or account temporarily locked.';
                    }


                    /*
                 * =================================================
                 * SUCCESSFUL LOGIN
                 * =================================================
                 */
                } else {

                    /*
                     * Successful authentication resets:
                     *
                     * - failed attempts
                     * - active lock
                     *
                     * This also resets the progressive
                     * lock level because failed_attempts
                     * goes back to zero.
                     */
                    $reset = $db->prepare(
                        'UPDATE auth_users

                         SET
                            failed_attempts = 0,
                            locked_until = NULL

                         WHERE user_id = :id'
                    );


                    $reset->execute([
                        'id' =>
                        (int) $user['user_id']
                    ]);


                    /*
                     * =================================================
                     * SECOND FACTOR: EMAIL OTP
                     * =================================================
                     *
                     * Correct username/password does NOT create an
                     * authenticated session yet. The account must first
                     * pass email OTP verification.
                     */
                    $email = trim(
                        (string) (
                            $user['email']
                            ?? ''
                        )
                    );

                    $emailVerifiedAt =
                        $user['email_verified_at']
                        ?? null;


                    if (
                        !filter_var(
                            $email,
                            FILTER_VALIDATE_EMAIL
                        )
                        || $emailVerifiedAt === null
                    ) {

                        $error =
                            'This account does not have a verified email address. '
                            . 'Contact the system administrator.';

                    } else {

                        /*
                         * Rotate the pre-authentication session before
                         * storing the pending 2FA state.
                         */
                        session_regenerate_id(
                            true
                        );

                        $_SESSION = [];

                        authCsrf();


                        $otpResult =
                            loginOtpIssue(
                                $db,
                                $user
                            );


                        if (
                            !($otpResult['success'] ?? false)
                        ) {

                            loginOtpClearPending();

                            $error =
                                (string) (
                                    $otpResult['message']
                                    ?? 'Unable to send the verification code.'
                                );

                        } else {

                            header(
                                'Location: verify-login-otp.php',
                                true,
                                303
                            );

                            exit;
                        }
                    }
                }
            }
        } catch (Throwable $e) {

            error_log(
                'BCP login error: '
                    . $e->getMessage()
            );


            $error =
                'Login service is temporarily unavailable.';
        }
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
 * HTML ESCAPE
 * ============================================================
 */

function loginEscape(
    string $text
): string {

    return htmlspecialchars(
        $text,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>

<html lang="en">


<head>

    <meta charset="UTF-8">


    <meta
        name="viewport"
        content="width=device-width, initial-scale=1">


    <title>
        Login | BCP Scheduling System
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


    <!-- FontAwesome -->
    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">


    <!-- Existing Login CSS -->
    <link
        rel="stylesheet"
        href="app/assets/css/login.css">


    <!--
        Extra styles only for:
        - failed attempt counter
        - account lock countdown
        - forgot password link
    -->
    <style>
        .login-security-status {
            margin: 0 0 16px;
            padding: 13px 14px;
            border-radius: 10px;
            font-family:
                "Plus Jakarta Sans",
                sans-serif;
        }


        .login-security-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }


        .login-security-icon {
            flex: 0 0 auto;

            width: 35px;
            height: 35px;

            display: inline-flex;
            align-items: center;
            justify-content: center;

            border-radius: 9px;
        }


        .login-security-content {
            flex: 1;
            min-width: 0;
        }


        .login-security-title {
            display: block;

            margin-bottom: 3px;

            font-size: 13px;
            font-weight: 700;
            line-height: 1.35;
        }


        .login-security-text {
            display: block;

            font-size: 12px;
            line-height: 1.5;
        }


        /*
         * Attempt warning.
         */

        .login-security-warning {
            border: 1px solid #f0d38b;
            background: #fff9e9;
            color: #74590b;
        }


        .login-security-warning .login-security-icon {
            background: #ffefba;
            color: #9b7000;
        }


        /*
         * Locked account.
         */

        .login-security-locked {
            border: 1px solid #edbbbb;
            background: #fff1f1;
            color: #872929;
        }


        .login-security-locked .login-security-icon {
            background: #ffdddd;
            color: #b73737;
        }


        /*
         * Live countdown.
         */

        .login-lock-timer {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            margin-top: 7px;

            min-width: 72px;

            padding: 5px 9px;

            border-radius: 7px;

            background:
                rgba(255,
                    255,
                    255,
                    .78);

            font-size: 14px;
            font-weight: 800;

            font-variant-numeric:
                tabular-nums;

            letter-spacing: .04em;
        }


        /*
         * Password row utilities.
         */

        .login-password-wrap {
            position: relative;

            display: flex;
            align-items: center;
        }


        .login-password-wrap input {
            width: 100%;

            padding-right: 42px;

            box-sizing: border-box;
        }


        .login-password-toggle {
            position: absolute;

            right: 12px;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 0;

            border: none;

            background:
                transparent;

            color: #647b9e;

            cursor: pointer;

            outline: none;
        }


        /*
         * Forgot Password.
         */

        .login-forgot-row {
            display: flex;
            justify-content: flex-end;

            margin-top: -3px;
            margin-bottom: 15px;
        }


        .login-forgot-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;

            color: #3158ad;

            font-size: 12px;
            font-weight: 700;

            text-decoration: none;

            transition:
                color .2s ease;
        }


        .login-forgot-link:hover {
            color: #173f8f;
            text-decoration: underline;
        }


        /*
         * Locked form.
         */

        .login-form-locked input,

        .login-form-locked .login-button {
            cursor: not-allowed !important;
        }


        .login-form-locked input {
            opacity: .66;
        }


        .login-form-locked .login-button {
            opacity: .62;
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

                    ACADEMIC SCHEDULING PLATFORM

                </span>


                <h1>

                    A smarter way to<br>

                    manage academic<br>

                    <span>
                        schedules.
                    </span>

                </h1>


                <p>

                    Manage class schedules,
                    faculty assignments,
                    examinations,
                    and classroom availability
                    in one place.

                </p>


            </div>


            <div class="intro-footer">

                BCP Class Scheduling System

            </div>


        </section>


        <!-- ======================================================
         RIGHT LOGIN PANEL
         ====================================================== -->

        <section class="login-form-area">


            <div class="login-card">


                <div class="login-card-header">


                    <span class="welcome-label">

                        WELCOME BACK

                    </span>


                    <h2>

                        Sign in to your account

                    </h2>


                    <p>

                        Enter your account credentials
                        to continue.

                    </p>


                </div>


                <!-- =================================================
                 ERROR
                 ================================================= -->

                <?php if ($error !== ''): ?>

                    <div
                        class="login-error"
                        role="alert">

                        <?= loginEscape(
                            $error
                        ) ?>

                    </div>

                <?php endif; ?>


                <!-- =================================================
                 ATTEMPT COUNTER
                 ================================================= -->

                <?php if (
                    $showAttemptStatus
                    && !$isLockedUi
                ): ?>

                    <div
                        class="
                        login-security-status
                        login-security-warning
                    "
                        role="status"
                        aria-live="polite">


                        <div class="login-security-row">


                            <span
                                class="login-security-icon"
                                aria-hidden="true">

                                <i
                                    class="fa-solid fa-shield-halved"></i>

                            </span>


                            <div class="login-security-content">


                                <strong
                                    class="login-security-title">

                                    Login attempt

                                    <?= (int) $attemptNumber ?>

                                    of

                                    <?= LOGIN_ATTEMPTS_PER_LOCK ?>

                                </strong>


                                <span
                                    class="login-security-text">

                                    <?php if (
                                        $attemptsRemaining === 1
                                    ): ?>

                                        1 incorrect attempt remaining
                                        before a temporary security lock.

                                    <?php else: ?>

                                        <?= (int) $attemptsRemaining ?>

                                        incorrect attempts remaining
                                        before a temporary security lock.

                                    <?php endif; ?>

                                </span>


                            </div>


                        </div>


                    </div>

                <?php endif; ?>


                <!-- =================================================
                 ACCOUNT LOCK TIMER
                 ================================================= -->

                <?php if ($isLockedUi): ?>

                    <div
                        class="
                        login-security-status
                        login-security-locked
                    "
                        role="alert"
                        aria-live="assertive">


                        <div class="login-security-row">


                            <span
                                class="login-security-icon"
                                aria-hidden="true">

                                <i
                                    class="fa-solid fa-lock"></i>

                            </span>


                            <div class="login-security-content">


                                <strong
                                    class="login-security-title">

                                    Login temporarily locked

                                </strong>


                                <span
                                    class="login-security-text">

                                    Login becomes available
                                    again in:

                                </span>


                                <span
                                    class="login-lock-timer"
                                    id="loginLockTimer"
                                    data-seconds="<?= (int) $lockSecondsRemaining ?>">

                                    --:--

                                </span>


                            </div>


                        </div>


                    </div>

                <?php endif; ?>


                <!-- =================================================
                 LOGIN FORM
                 ================================================= -->

                <form
                    method="POST"
                    action=""
                    autocomplete="on"
                    id="loginForm"
                    class="<?= $isLockedUi
                                ? 'login-form-locked'
                                : '' ?>">


                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= loginEscape(
                                    $csrf
                                ) ?>">


                    <!-- USERNAME -->

                    <div class="form-group">


                        <label for="username">

                            Username

                        </label>


                        <input
                            type="text"
                            id="username"
                            name="username"
                            placeholder="Enter your username"
                            autocomplete="username"
                            maxlength="80"
                            value="<?= loginEscape(
                                        $submittedUsername
                                    ) ?>"
                            <?= $isLockedUi
                                ? 'disabled'
                                : '' ?>
                            required
                            autofocus>


                    </div>


                    <!-- PASSWORD -->

                    <div class="form-group">


                        <label for="password">

                            Password

                        </label>


                        <div class="login-password-wrap">


                            <input
                                type="password"
                                id="password"
                                name="password"
                                placeholder="Enter your password"
                                autocomplete="current-password"
                                maxlength="1024"
                                <?= $isLockedUi
                                    ? 'disabled'
                                    : '' ?>
                                required>


                            <button
                                class="login-password-toggle"
                                type="button"
                                id="togglePassword"
                                title="Show or hide password"
                                aria-label="Show password"
                                <?= $isLockedUi
                                    ? 'disabled'
                                    : '' ?>>

                                <i
                                    class="fa-regular fa-eye"
                                    id="toggleIcon"
                                    aria-hidden="true"></i>

                            </button>


                        </div>


                    </div>


                    <!-- FORGOT PASSWORD -->

                    <div class="login-forgot-row">


                        <a
                            class="login-forgot-link"
                            href="forgot-password.php">

                            <i
                                class="fa-solid fa-key"
                                aria-hidden="true"></i>

                            Forgot password?

                        </a>


                    </div>


                    <!-- SIGN IN -->

                    <button
                        class="login-button"
                        id="loginButton"
                        type="submit"
                        <?= $isLockedUi
                            ? 'disabled'
                            : '' ?>>


                        <?php if ($isLockedUi): ?>


                            <i
                                class="fa-solid fa-lock"
                                aria-hidden="true"></i>

                            Temporarily Locked


                        <?php else: ?>


                            Sign In

                            <span aria-hidden="true">
                                →
                            </span>


                        <?php endif; ?>


                    </button>


                </form>


                <p class="login-support">

                    Authorized Admin and Scheduler
                    access only.

                </p>


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
             * SHOW / HIDE PASSWORD
             * ========================================================
             */

            const togglePassword =
                document.getElementById(
                    'togglePassword'
                );


            const password =
                document.getElementById(
                    'password'
                );


            const toggleIcon =
                document.getElementById(
                    'toggleIcon'
                );


            if (
                togglePassword &&
                password &&
                toggleIcon
            ) {

                togglePassword.addEventListener(
                    'click',
                    () => {


                        if (
                            togglePassword.disabled
                        ) {
                            return;
                        }


                        const show =
                            password.type ===
                            'password';


                        password.type =
                            show ?
                            'text' :
                            'password';


                        toggleIcon.className =
                            show ?
                            'fa-regular fa-eye-slash' :
                            'fa-regular fa-eye';


                        toggleIcon.style.color =
                            show ?
                            '#1a3a8c' :
                            '#647b9e';


                        togglePassword.setAttribute(
                            'aria-label',

                            show ?
                            'Hide password' :
                            'Show password'
                        );


                        password.focus();

                    }
                );

            }


            /*
             * ========================================================
             * LIVE ACCOUNT LOCK COUNTDOWN
             * ========================================================
             *
             * JavaScript only displays the remaining time.
             *
             * Actual enforcement remains server-side
             * using auth_users.locked_until.
             * ========================================================
             */

            const loginLockTimer =
                document.getElementById(
                    'loginLockTimer'
                );


            if (loginLockTimer) {


                let secondsRemaining =
                    Number(
                        loginLockTimer.dataset.seconds ||
                        0
                    );


                const formatLockTime = (
                    totalSeconds
                ) => {


                    totalSeconds =
                        Math.max(
                            0,
                            Math.floor(
                                totalSeconds
                            )
                        );


                    const hours =
                        Math.floor(
                            totalSeconds /
                            3600
                        );


                    const minutes =
                        Math.floor(
                            (
                                totalSeconds %
                                3600
                            ) /
                            60
                        );


                    const seconds =
                        totalSeconds %
                        60;


                    const formattedMinutes =
                        String(
                            minutes
                        ).padStart(
                            2,
                            '0'
                        );


                    const formattedSeconds =
                        String(
                            seconds
                        ).padStart(
                            2,
                            '0'
                        );


                    if (hours > 0) {

                        return (
                            String(hours) +
                            ':' +
                            formattedMinutes +
                            ':' +
                            formattedSeconds
                        );
                    }


                    return (
                        formattedMinutes +
                        ':' +
                        formattedSeconds
                    );

                };


                const updateLockTimer = () => {


                    loginLockTimer.textContent =
                        formatLockTime(
                            secondsRemaining
                        );


                    if (
                        secondsRemaining <= 0
                    ) {

                        loginLockTimer.textContent =
                            '00:00';


                        /*
                         * Refresh so PHP/MySQL
                         * rechecks locked_until.
                         */
                        window.setTimeout(
                            () => {

                                window.location.href =
                                    'index.php';

                            },
                            700
                        );


                        return;
                    }


                    secondsRemaining--;


                    window.setTimeout(
                        updateLockTimer,
                        1000
                    );

                };


                updateLockTimer();

            }


        })();
    </script>


</body>

</html>