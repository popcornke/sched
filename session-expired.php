<?php

declare(strict_types=1);

require_once __DIR__
    . '/app/shared/auth.php';

authStart();
authNoCache();

/*
 * If a valid new login already exists, never trap the user on the
 * timeout screen. Send them straight back to the dashboard.
 */
if (authLoggedIn()) {
    header(
        'Location: '
        . authBasePath()
        . '/app/dashboard/dashboard.php',
        true,
        303
    );

    exit;
}

$reasonKey = strtolower(
    trim(
        (string) (
            $_GET['reason']
            ?? 'expired'
        )
    )
);

$reason = match ($reasonKey) {
    'idle' =>
        'IDLE_TIMEOUT',

    'maximum' =>
        'MAX_SESSION_LIFETIME',

    'revoked' =>
        'SESSION_REVOKED',

    default =>
        'EXPIRED',
};

$title = match ($reason) {
    'IDLE_TIMEOUT' =>
        'Session Timeout',

    'MAX_SESSION_LIFETIME' =>
        'Maximum Session Reached',

    'SESSION_REVOKED' =>
        'Session Ended',

    default =>
        'Session Expired',
};

$message = match ($reason) {
    'IDLE_TIMEOUT' =>
        'Your session expired because there was no activity for '
        . authHumanDuration(
            SESSION_IDLE_LIMIT
        )
        . '. For your account security, please sign in again.',

    'MAX_SESSION_LIFETIME' =>
        'Your session reached the maximum allowed duration of '
        . authHumanDuration(
            SESSION_MAX_LIFETIME
        )
        . '. Please sign in again to continue.',

    'SESSION_REVOKED' =>
        'Your account session is no longer active. Please sign in again to continue.',

    default =>
        'Your session is no longer valid. Please sign in again to continue.',
};

$loginUrl =
    authLoginUrl();

$cssUrl =
    authBasePath()
    . '/app/assets/css/session-timeout.css';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="robots"
        content="noindex,nofollow"
    >
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> | BCP Scheduling</title>
    <link
        rel="stylesheet"
        href="<?= htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') ?>"
    >
</head>
<body class="bcp-session-expired-page bcp-session-locked">

<div
    class="bcp-session-timeout"
    aria-hidden="false"
>
    <div
        class="bcp-session-timeout__backdrop"
        aria-hidden="true"
    ></div>

    <section
        class="bcp-session-timeout__dialog"
        role="alertdialog"
        aria-modal="true"
        aria-labelledby="bcpSessionTimeoutTitle"
        aria-describedby="bcpSessionTimeoutReason"
    >
        <span
            class="bcp-session-timeout__icon"
            aria-hidden="true"
        >
            🔒
        </span>

        <span class="bcp-session-timeout__eyebrow">
            BCP ACCOUNT SECURITY
        </span>

        <h1 id="bcpSessionTimeoutTitle">
            <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
        </h1>

        <p
            class="bcp-session-timeout__reason"
            id="bcpSessionTimeoutReason"
        >
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </p>

        <div
            class="bcp-session-timeout__counter"
            id="bcpSessionTimeoutCounter"
            aria-label="Redirect countdown"
        >
            <strong id="bcpSessionTimeoutCountdown">
                5
            </strong>
        </div>

        <span class="bcp-session-timeout__seconds">
            seconds until login
        </span>

        <a
            class="bcp-session-timeout__login"
            id="bcpSessionTimeoutLogin"
            href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>"
        >
            Go to Login Now
        </a>

        <p class="bcp-session-timeout__note">
            The expired session cannot be resumed.
        </p>
    </section>
</div>

<script>
(() => {
    'use strict';

    const loginUrl =
        <?= json_encode($loginUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;

    const countdown =
        document.getElementById(
            'bcpSessionTimeoutCountdown'
        );

    const counter =
        document.getElementById(
            'bcpSessionTimeoutCounter'
        );

    const login =
        document.getElementById(
            'bcpSessionTimeoutLogin'
        );

    const durationMs = 5000;
    const startedAt =
        performance.now();

    let frame = null;

    function goLogin() {
        if (frame !== null) {
            cancelAnimationFrame(
                frame
            );
        }

        window.location.replace(
            loginUrl
        );
    }

    login.addEventListener(
        'click',
        event => {
            event.preventDefault();
            goLogin();
        }
    );

    function animate(now) {
        const remaining =
            Math.max(
                0,
                durationMs
                - (
                    now - startedAt
                )
            );

        const progress =
            remaining
            / durationMs;

        countdown.textContent =
            String(
                Math.max(
                    0,
                    Math.ceil(
                        remaining
                        / 1000
                    )
                )
            );

        counter.style.setProperty(
            '--bcp-session-progress',
            String(
                Math.max(
                    0,
                    Math.min(
                        1,
                        progress
                    )
                )
            )
        );

        if (remaining <= 0) {
            countdown.textContent =
                '0';

            counter.style.setProperty(
                '--bcp-session-progress',
                '0'
            );

            goLogin();
            return;
        }

        frame =
            requestAnimationFrame(
                animate
            );
    }

    frame =
        requestAnimationFrame(
            animate
        );
})();
</script>

</body>
</html>
