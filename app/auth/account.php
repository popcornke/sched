<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';

authRequire(
    false,
    ['ADMIN'],
    true
);

authNoCache();

function accountEsc(
    string $value
): string {
    return htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

$db = authDb();
$userId = (int) (
    $_SESSION['auth_user_id']
    ?? 0
);

$stmt = $db->prepare(
    'SELECT
        user_id,
        username,
        email,
        email_verified_at,
        role,
        is_active,
        created_at
     FROM auth_users
     WHERE user_id = :user_id
       AND role = "ADMIN"
     LIMIT 1'
);

$stmt->execute([
    'user_id' => $userId
]);

$account =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (
    !$account
    || (int) $account['is_active'] !== 1
) {
    authClear();

    header(
        'Location: '
        . authLoginUrl(),
        true,
        303
    );

    exit;
}

$passwordState =
    authAdminPasswordState(
        $db,
        $userId
    );

$csrf = authCsrf();

$username =
    (string) $account['username'];

$email =
    (string) (
        $account['email']
        ?? ''
    );

$role =
    (string) $account['role'];

$initial = strtoupper(
    substr(
        $username !== ''
            ? $username
            : 'A',
        0,
        1
    )
);

$required =
    $passwordState['expired']
    || (
        (string) (
            $_GET['required']
            ?? ''
        ) === '1'
    );

$ACTIVE_NAV =
    'account_settings';

$APP_ROOT = '../';
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
        Account Settings | BCP Scheduling
    </title>

    <link
        rel="icon"
        href="../assets/images/BCP_LOGO.png"
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
        href="../assets/css/dashboard.css">

    <link
        rel="stylesheet"
        href="../assets/css/account-settings.css">
</head>

<body class="bcp-dashboard-page account-settings-page">

<?php
require_once dirname(__DIR__)
    . '/includes/sidebar.php';
?>

<div class="main">
    <div class="topbar">
        <button
            class="hamburger"
            id="hamburgerBtn"
            aria-label="Toggle sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <span class="topbar-spacer"></span>

        <div class="topbar-right">
            <span class="role-badge">
                <i
                    class="fa-solid fa-shield-halved"
                    style="color:#1a3a8c;"></i>
                <?= accountEsc($role) ?>
            </span>

            <a
                href="./account.php"
                class="avatar"
                title="Account Settings"
                aria-current="page">
                <?= accountEsc($initial) ?>
            </a>
        </div>
    </div>

    <main
        class="content account-settings"
        id="accountSettingsMain">

        <header class="account-settings__hero">
            <div>
                <span class="account-settings__eyebrow">
                    <i class="fa-solid fa-user-shield"></i>
                    ADMINISTRATOR SECURITY
                </span>

                <h1>
                    Account Settings<span>.</span>
                </h1>

                <p>
                    Manage your administrator identity,
                    verified login email, password,
                    and security status.
                </p>
            </div>

            <a
                class="account-settings__back"
                href="../dashboard/dashboard.php">
                <i class="fa-solid fa-arrow-left"></i>
                Dashboard
            </a>
        </header>

        <?php if ($required): ?>
            <section
                class="account-alert is-danger"
                role="alert">
                <span class="account-alert__icon">
                    <i class="fa-solid fa-lock"></i>
                </span>

                <div>
                    <strong>
                        Password change required
                    </strong>

                    <p>
                        Your administrator password has
                        reached the 30-day security limit.
                        Create a new password before
                        returning to the scheduling modules.
                    </p>
                </div>
            </section>

        <?php elseif ($passwordState['warning']): ?>
            <section
                class="account-alert is-warning"
                role="status">
                <span class="account-alert__icon">
                    <i
                        class="fa-solid fa-triangle-exclamation"></i>
                </span>

                <div>
                    <strong>
                        Password expires soon
                    </strong>

                    <p>
                        You have
                        <?= (int) $passwordState['days_remaining'] ?>
                        day(s) remaining. Change your
                        password before it expires.
                    </p>
                </div>
            </section>
        <?php endif; ?>

        <div
            class="account-message"
            id="accountMessage"
            role="status"
            aria-live="polite"
            hidden>
        </div>

        <section
            class="account-overview"
            aria-label="Account overview">

            <article class="account-profile-card">
                <span class="account-profile-card__avatar">
                    <?= accountEsc($initial) ?>
                </span>

                <div>
                    <span>Signed in as</span>

                    <strong id="profileUsername">
                        <?= accountEsc($username) ?>
                    </strong>

                    <p id="profileEmail">
                        <?= accountEsc($email) ?>
                    </p>
                </div>

                <span class="account-profile-card__role">
                    <?= accountEsc($role) ?>
                </span>
            </article>

            <article class="security-status-card">
                <div class="security-status-card__head">
                    <span>
                        <i class="fa-solid fa-key"></i>
                    </span>

                    <div>
                        <small>Password status</small>
                        <strong id="passwordStatus">
                            <?= $passwordState['expired']
                                ? 'Expired'
                                : 'Active' ?>
                        </strong>
                    </div>
                </div>

                <div class="security-status-card__grid">
                    <div>
                        <span>Last changed</span>
                        <strong id="passwordChangedAt">
                            <?= accountEsc(
                                (string) (
                                    $passwordState['password_changed_at']
                                    ?? 'Not recorded'
                                )
                            ) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Expires</span>
                        <strong id="passwordExpiresAt">
                            <?= accountEsc(
                                (string) (
                                    $passwordState['password_expires_at']
                                    ?? 'Required now'
                                )
                            ) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Remaining</span>
                        <strong id="passwordDaysRemaining">
                            <?= $passwordState['expired']
                                ? 'Required now'
                                : (
                                    (int) $passwordState['days_remaining']
                                    . ' day(s)'
                                ) ?>
                        </strong>
                    </div>
                </div>
            </article>
        </section>

        <div class="account-grid">
            <section
                class="account-panel"
                aria-labelledby="usernameTitle">

                <div class="account-panel__head">
                    <span class="account-panel__icon">
                        <i class="fa-solid fa-user"></i>
                    </span>

                    <div>
                        <p>PROFILE</p>
                        <h2 id="usernameTitle">
                            Administrator username
                        </h2>
                    </div>
                </div>

                <p class="account-panel__lead">
                    Update the username used on the
                    administrator login screen. Your current
                    password is required to confirm the change.
                </p>

                <form
                    class="account-form"
                    id="usernameForm"
                    autocomplete="off">

                    <label>
                        Username

                        <div class="account-input">
                            <i class="fa-solid fa-at"></i>

                            <input
                                id="usernameInput"
                                name="username"
                                type="text"
                                minlength="3"
                                maxlength="80"
                                value="<?= accountEsc($username) ?>"
                                autocomplete="username"
                                required>
                        </div>
                    </label>

                    <label>
                        Current password

                        <div class="account-input">
                            <i class="fa-solid fa-lock"></i>

                            <input
                                id="usernamePassword"
                                name="current_password"
                                type="password"
                                maxlength="128"
                                autocomplete="current-password"
                                required>

                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle="usernamePassword"
                                aria-label="Show current password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <button
                        class="account-submit"
                        type="submit">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save username
                    </button>
                </form>
            </section>

            <section
                class="account-panel"
                aria-labelledby="emailTitle">

                <div class="account-panel__head">
                    <span class="account-panel__icon">
                        <i class="fa-solid fa-envelope-circle-check"></i>
                    </span>

                    <div>
                        <p>LOGIN VERIFICATION</p>
                        <h2 id="emailTitle">
                            Verified email
                        </h2>
                    </div>
                </div>

                <p class="account-panel__lead">
                    Login OTP codes are sent to this email.
                    A replacement address is saved only after
                    you verify a code sent to the new address.
                </p>

                <div class="verified-email">
                    <span>
                        Current verified email
                    </span>

                    <strong id="verifiedEmail">
                        <?= accountEsc($email) ?>
                    </strong>

                    <small>
                        <i class="fa-solid fa-circle-check"></i>
                        Verified for administrator login
                    </small>
                </div>

                <form
                    class="account-form"
                    id="emailRequestForm"
                    autocomplete="off">

                    <label>
                        New email address

                        <div class="account-input">
                            <i class="fa-solid fa-envelope"></i>

                            <input
                                id="newEmail"
                                name="new_email"
                                type="email"
                                maxlength="254"
                                autocomplete="email"
                                required>
                        </div>
                    </label>

                    <label>
                        Current password

                        <div class="account-input">
                            <i class="fa-solid fa-lock"></i>

                            <input
                                id="emailPassword"
                                name="current_password"
                                type="password"
                                maxlength="128"
                                autocomplete="current-password"
                                required>

                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle="emailPassword"
                                aria-label="Show current password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <button
                        class="account-submit"
                        type="submit">
                        <i class="fa-regular fa-paper-plane"></i>
                        Send verification code
                    </button>
                </form>

                <form
                    class="account-form account-form--otp"
                    id="emailConfirmForm"
                    autocomplete="off"
                    hidden>

                    <label>
                        6-digit verification code

                        <div class="account-input">
                            <i class="fa-solid fa-shield-halved"></i>

                            <input
                                id="emailOtp"
                                name="otp"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                minlength="6"
                                maxlength="6"
                                autocomplete="one-time-code"
                                placeholder="000000"
                                required>
                        </div>
                    </label>

                    <button
                        class="account-submit"
                        type="submit">
                        <i class="fa-solid fa-circle-check"></i>
                        Verify and save email
                    </button>

                    <small class="account-form__note">
                        The code expires after 10 minutes.
                    </small>
                </form>
            </section>

            <section
                class="account-panel account-panel--wide"
                id="passwordPanel"
                aria-labelledby="passwordTitle">

                <div class="account-panel__head">
                    <span class="account-panel__icon">
                        <i class="fa-solid fa-key"></i>
                    </span>

                    <div>
                        <p>SECURITY</p>
                        <h2 id="passwordTitle">
                            Change password
                        </h2>
                    </div>
                </div>

                <p class="account-panel__lead">
                    Administrator passwords expire every
                    30 days. You cannot reuse your current
                    password or any of your last three
                    passwords.
                </p>

                <form
                    class="account-form account-form--password"
                    id="passwordForm"
                    autocomplete="off">

                    <label>
                        Current password

                        <div class="account-input">
                            <i class="fa-solid fa-lock"></i>

                            <input
                                id="currentPassword"
                                name="current_password"
                                type="password"
                                maxlength="128"
                                autocomplete="current-password"
                                required>

                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle="currentPassword"
                                aria-label="Show current password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <label>
                        New password

                        <div class="account-input">
                            <i class="fa-solid fa-shield-halved"></i>

                            <input
                                id="newPassword"
                                name="new_password"
                                type="password"
                                maxlength="128"
                                autocomplete="new-password"
                                required>

                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle="newPassword"
                                aria-label="Show new password">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <label>
                        Confirm new password

                        <div class="account-input">
                            <i class="fa-solid fa-check"></i>

                            <input
                                id="confirmPassword"
                                name="confirm_password"
                                type="password"
                                maxlength="128"
                                autocomplete="new-password"
                                required>

                            <button
                                type="button"
                                class="password-toggle"
                                data-toggle="confirmPassword"
                                aria-label="Show password confirmation">
                                <i class="fa-regular fa-eye"></i>
                            </button>
                        </div>
                    </label>

                    <div
                        class="password-rules"
                        aria-label="Password requirements">

                        <strong>
                            Password requirements
                        </strong>

                        <span id="ruleLength">
                            <i class="fa-regular fa-circle"></i>
                            12–128 characters
                        </span>

                        <span id="ruleUpper">
                            <i class="fa-regular fa-circle"></i>
                            At least one uppercase letter
                        </span>

                        <span id="ruleLower">
                            <i class="fa-regular fa-circle"></i>
                            At least one lowercase letter
                        </span>

                        <span id="ruleNumber">
                            <i class="fa-regular fa-circle"></i>
                            At least one number
                        </span>

                        <span id="ruleMatch">
                            <i class="fa-regular fa-circle"></i>
                            Confirmation matches
                        </span>
                    </div>

                    <button
                        class="account-submit"
                        type="submit">
                        <i class="fa-solid fa-key"></i>
                        Save new password
                    </button>
                </form>
            </section>

            <section
                class="account-panel account-panel--wide session-panel"
                aria-labelledby="sessionTitle">

                <div class="account-panel__head">
                    <span class="account-panel__icon">
                        <i class="fa-solid fa-shield"></i>
                    </span>

                    <div>
                        <p>SESSION SECURITY</p>
                        <h2 id="sessionTitle">
                            Active security policy
                        </h2>
                    </div>
                </div>

                <div class="session-policy">
                    <article>
                        <span>
                            <i class="fa-regular fa-clock"></i>
                        </span>

                        <div>
                            <small>Idle timeout</small>
                            <strong>10 minutes</strong>
                            <p>
                                Inactivity automatically
                                ends the authenticated session.
                            </p>
                        </div>
                    </article>

                    <article>
                        <span>
                            <i class="fa-solid fa-hourglass-half"></i>
                        </span>

                        <div>
                            <small>Maximum session</small>
                            <strong>8 hours</strong>
                            <p>
                                A login session ends after
                                eight hours even while active.
                            </p>
                        </div>
                    </article>

                    <article>
                        <span>
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </span>

                        <div>
                            <small>Password rotation</small>
                            <strong>Every 30 days</strong>
                            <p>
                                A warning begins seven days
                                before expiration.
                            </p>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <footer class="account-footer">
            <span>
                <i class="fa-solid fa-lock"></i>
                Administrator account security
            </span>

            <span>
                Passwords are stored only as secure hashes.
            </span>

            <span>
                Keyboard:
                <kbd>P</kbd> password ·
                <kbd>U</kbd> username
            </span>
        </footer>
    </main>
</div>

<script>
(() => {
    'use strict';

    const csrf =
        <?= json_encode(
            $csrf,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        ) ?>;

    const api =
        '../api/account-settings.php';

    const required =
        <?= $required ? 'true' : 'false' ?>;

    const $ = id =>
        document.getElementById(id);

    let busy = false;

    const message = (
        text,
        state = 'success'
    ) => {
        const root = $('accountMessage');

        if (!root) {
            return;
        }

        root.hidden = false;
        root.className =
            `account-message is-${state}`;

        root.textContent = text;

        root.scrollIntoView({
            behavior: 'smooth',
            block: 'nearest'
        });
    };

    const clearMessage = () => {
        const root = $('accountMessage');

        if (root) {
            root.hidden = true;
        }
    };

    async function post(
        action,
        payload = {}
    ) {
        if (busy) {
            throw new Error(
                'Another account update is still running.'
            );
        }

        busy = true;

        try {
            const response = await fetch(
                api,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    cache: 'no-store',
                    headers: {
                        'Content-Type':
                            'application/json'
                    },
                    body: JSON.stringify({
                        action,
                        csrf_token: csrf,
                        ...payload
                    })
                }
            );

            let data = null;

            try {
                data = await response.json();
            } catch {
                throw new Error(
                    'Account Settings returned an invalid response.'
                );
            }

            if (
                !response.ok
                || data.success !== true
            ) {
                throw new Error(
                    data.message
                    || data.status
                    || 'Account update failed.'
                );
            }

            return data;
        } finally {
            busy = false;
        }
    }

    document
        .querySelectorAll(
            '[data-toggle]'
        )
        .forEach(button => {
            button.addEventListener(
                'click',
                () => {
                    const input =
                        document.getElementById(
                            button.dataset.toggle
                        );

                    if (!input) {
                        return;
                    }

                    const show =
                        input.type === 'password';

                    input.type =
                        show
                            ? 'text'
                            : 'password';

                    const icon =
                        button.querySelector('i');

                    if (icon) {
                        icon.className =
                            show
                                ? 'fa-regular fa-eye-slash'
                                : 'fa-regular fa-eye';
                    }

                    button.setAttribute(
                        'aria-label',
                        show
                            ? 'Hide password'
                            : 'Show password'
                    );

                    input.focus();
                }
            );
        });

    $('usernameForm')?.addEventListener(
        'submit',
        async event => {
            event.preventDefault();
            clearMessage();

            const submit =
                event.currentTarget
                    .querySelector(
                        'button[type="submit"]'
                    );

            if (submit) {
                submit.disabled = true;
            }

            try {
                const data = await post(
                    'update_username',
                    {
                        username:
                            $('usernameInput').value,
                        current_password:
                            $('usernamePassword').value
                    }
                );

                $('profileUsername').textContent =
                    data.username;

                $('usernamePassword').value = '';

                message(data.message);
            } catch (error) {
                message(
                    error.message,
                    'error'
                );
            } finally {
                if (submit) {
                    submit.disabled = false;
                }
            }
        }
    );

    $('emailRequestForm')
        ?.addEventListener(
            'submit',
            async event => {
                event.preventDefault();
                clearMessage();

                const submit =
                    event.currentTarget
                        .querySelector(
                            'button[type="submit"]'
                        );

                if (submit) {
                    submit.disabled = true;
                }

                try {
                    const data = await post(
                        'request_email_change',
                        {
                            new_email:
                                $('newEmail').value,
                            current_password:
                                $('emailPassword').value
                        }
                    );

                    $('emailPassword').value = '';
                    $('emailConfirmForm').hidden = false;
                    $('emailOtp').focus();

                    message(data.message);
                } catch (error) {
                    message(
                        error.message,
                        'error'
                    );
                } finally {
                    if (submit) {
                        submit.disabled = false;
                    }
                }
            }
        );

    $('emailConfirmForm')
        ?.addEventListener(
            'submit',
            async event => {
                event.preventDefault();
                clearMessage();

                const submit =
                    event.currentTarget
                        .querySelector(
                            'button[type="submit"]'
                        );

                if (submit) {
                    submit.disabled = true;
                }

                try {
                    const data = await post(
                        'confirm_email_change',
                        {
                            otp:
                                $('emailOtp').value
                        }
                    );

                    $('verifiedEmail').textContent =
                        data.email;

                    $('profileEmail').textContent =
                        data.email;

                    $('newEmail').value = '';
                    $('emailOtp').value = '';
                    $('emailConfirmForm').hidden = true;

                    message(data.message);
                } catch (error) {
                    message(
                        error.message,
                        'error'
                    );
                } finally {
                    if (submit) {
                        submit.disabled = false;
                    }
                }
            }
        );

    $('emailOtp')?.addEventListener(
        'input',
        event => {
            event.currentTarget.value =
                event.currentTarget.value
                    .replace(/\D/g, '')
                    .slice(0, 6);
        }
    );

    const next =
        $('newPassword');

    const confirm =
        $('confirmPassword');

    const setRule = (
        id,
        ok
    ) => {
        const row = $(id);

        if (!row) {
            return;
        }

        row.classList.toggle(
            'is-ok',
            ok
        );

        const icon =
            row.querySelector('i');

        if (icon) {
            icon.className =
                ok
                    ? 'fa-solid fa-circle-check'
                    : 'fa-regular fa-circle';
        }
    };

    const validatePassword = () => {
        const value =
            next?.value
            || '';

        setRule(
            'ruleLength',
            value.length >= 12
            && value.length <= 128
        );

        setRule(
            'ruleUpper',
            /[A-Z]/.test(value)
        );

        setRule(
            'ruleLower',
            /[a-z]/.test(value)
        );

        setRule(
            'ruleNumber',
            /[0-9]/.test(value)
        );

        setRule(
            'ruleMatch',
            value.length > 0
            && value
                === (
                    confirm?.value
                    || ''
                )
        );
    };

    next?.addEventListener(
        'input',
        validatePassword
    );

    confirm?.addEventListener(
        'input',
        validatePassword
    );

    $('passwordForm')?.addEventListener(
        'submit',
        async event => {
            event.preventDefault();
            clearMessage();

            const submit =
                event.currentTarget
                    .querySelector(
                        'button[type="submit"]'
                    );

            if (submit) {
                submit.disabled = true;
            }

            try {
                const data = await post(
                    'change_password',
                    {
                        current_password:
                            $('currentPassword').value,
                        new_password:
                            $('newPassword').value,
                        confirm_password:
                            $('confirmPassword').value
                    }
                );

                message(data.message);

                event.currentTarget.reset();
                validatePassword();

                setTimeout(
                    () => {
                        window.location.href =
                            data.redirect;
                    },
                    900
                );
            } catch (error) {
                message(
                    error.message,
                    'error'
                );

                if (submit) {
                    submit.disabled = false;
                }
            }
        }
    );

    document.addEventListener(
        'keydown',
        event => {
            const tag =
                document.activeElement
                    ?.tagName;

            if (
                ['INPUT', 'TEXTAREA', 'SELECT']
                    .includes(tag)
                || event.ctrlKey
                || event.altKey
                || event.metaKey
            ) {
                return;
            }

            const key =
                event.key.toLowerCase();

            if (key === 'p') {
                event.preventDefault();

                $('passwordPanel')
                    ?.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });

                $('currentPassword')
                    ?.focus({
                        preventScroll: true
                    });
            }

            if (key === 'u') {
                event.preventDefault();

                $('usernameInput')
                    ?.focus();
            }
        }
    );

    if (required) {
        $('passwordPanel')
            ?.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });

        setTimeout(
            () => {
                $('currentPassword')
                    ?.focus({
                        preventScroll: true
                    });
            },
            250
        );
    }
})();
</script>

</body>
</html>
