<?php

declare(strict_types=1);

/*
 * BCP Authentication
 * Central session and access protection.
 */

function authBasePath(): string
{
    $scriptName = str_replace(
        '\\',
        '/',
        $_SERVER['SCRIPT_NAME'] ?? ''
    );

    $appPosition = strpos(
        $scriptName,
        '/app/'
    );

    if ($appPosition !== false) {
        return rtrim(
            substr(
                $scriptName,
                0,
                $appPosition
            ),
            '/'
        );
    }

    $directory = str_replace(
        '\\',
        '/',
        dirname($scriptName)
    );

    if (
        $directory === '/'
        || $directory === '.'
    ) {
        return '';
    }

    return rtrim(
        $directory,
        '/'
    );
}

function authLoginUrl(): string
{
    return authBasePath() . '/index.php';
}

function authLogoutUrl(): string
{
    return authBasePath() . '/logout.php';
}

function authAccountUrl(): string
{
    return authBasePath() . '/app/auth/account.php';
}

const SESSION_IDLE_LIMIT = 600;      // 10 minutes
const SESSION_MAX_LIFETIME = 28800;  // 8 hours

/*
 * Admin password rotation:
 * - warning begins at 7 days remaining
 * - password becomes mandatory to change after 30 days
 */
const ADMIN_PASSWORD_MAX_AGE_DAYS = 30;
const ADMIN_PASSWORD_WARNING_DAYS = 7;

function authDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('DB_PORT') ?: 3306);
    $database = getenv('DB_NAME') ?: 'bcp_scheduling';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '';

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $database
    );

    $pdo = new PDO(
        $dsn,
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    return $pdo;
}

function authStart(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $forwardedProto = strtolower(
        trim(
            (string) (
                $_SERVER['HTTP_X_FORWARDED_PROTO']
                ?? ''
            )
        )
    );

    if (str_contains($forwardedProto, ',')) {
        $forwardedProto = trim(
            explode(',', $forwardedProto, 2)[0]
        );
    }

    $https =
        (
            !empty($_SERVER['HTTPS'])
            && strtolower((string) $_SERVER['HTTPS']) !== 'off'
        )
        || $forwardedProto === 'https';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_trans_sid', '0');

    session_name('BCP_AUTH');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

function authNoCache(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');
}

function authCsrf(): string
{
    authStart();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function authCsrfValid($token): bool
{
    authStart();

    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function authClear(): void
{
    authStart();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite']
        ]);
    }

    session_destroy();
}

function authLoggedIn(): bool
{
    authStart();

    if (empty($_SESSION['auth_user_id'])) {
        return false;
    }

    $now = time();

    $lastActivity = (int) (
        $_SESSION['auth_last_activity'] ?? 0
    );

    $loginTime = (int) (
        $_SESSION['auth_login_time'] ?? 0
    );

    if (
        $lastActivity <= 0
        || $loginTime <= 0
        || ($now - $lastActivity) > SESSION_IDLE_LIMIT
        || ($now - $loginTime) > SESSION_MAX_LIFETIME
    ) {
        authClear();

        return false;
    }

    return true;
}

/**
 * Return the ADMIN password-rotation state.
 *
 * This deliberately uses database time for the 30-day calculation
 * so Railway/local PHP timezone differences do not alter expiration.
 */
function authAdminPasswordState(
    PDO $db,
    int $userId
): array {
    $stmt = $db->prepare(
        'SELECT
            password_changed_at,
            CASE
                WHEN password_changed_at IS NULL THEN 1
                WHEN DATE_ADD(
                    password_changed_at,
                    INTERVAL 30 DAY
                ) <= NOW() THEN 1
                ELSE 0
            END AS password_expired,
            CASE
                WHEN password_changed_at IS NULL THEN 0
                ELSE GREATEST(
                    0,
                    TIMESTAMPDIFF(
                        SECOND,
                        NOW(),
                        DATE_ADD(
                            password_changed_at,
                            INTERVAL 30 DAY
                        )
                    )
                )
            END AS seconds_remaining
         FROM auth_users
         WHERE user_id = :user_id
           AND role = "ADMIN"
         LIMIT 1'
    );

    $stmt->execute([
        'user_id' => $userId
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return [
            'applies' => false,
            'expired' => false,
            'warning' => false,
            'days_remaining' => null,
            'seconds_remaining' => null,
            'password_changed_at' => null,
            'password_expires_at' => null,
        ];
    }

    $seconds = max(
        0,
        (int) ($row['seconds_remaining'] ?? 0)
    );

    $expired =
        (int) ($row['password_expired'] ?? 1) === 1;

    $daysRemaining = $expired
        ? 0
        : (int) ceil($seconds / 86400);

    $changedAt = $row['password_changed_at'];

    return [
        'applies' => true,
        'expired' => $expired,
        'warning' =>
            !$expired
            && $daysRemaining <= ADMIN_PASSWORD_WARNING_DAYS,
        'days_remaining' => $daysRemaining,
        'seconds_remaining' => $seconds,
        'password_changed_at' => $changedAt,
        'password_expires_at' =>
            $changedAt === null
                ? null
                : date(
                    'Y-m-d H:i:s',
                    strtotime(
                        (string) $changedAt
                        . ' +'
                        . ADMIN_PASSWORD_MAX_AGE_DAYS
                        . ' days'
                    )
                ),
    ];
}

/**
 * Central access gate.
 *
 * $allowExpiredAdminPassword must ONLY be true for:
 * - the Admin Account Settings page
 * - its API
 *
 * This lets an expired admin change the password while every
 * other protected admin page remains blocked.
 */
function authRequire(
    bool $api = false,
    array $allowedRoles = ['ADMIN', 'SCHEDULER'],
    bool $allowExpiredAdminPassword = false
): void {
    authNoCache();

    if (!authLoggedIn()) {
        if ($api) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'status' => 'AUTH_REQUIRED',
                'message' => 'Please log in again.'
            ]);

            exit;
        }

        header('Location: ' . authLoginUrl(), true, 303);
        exit;
    }

    $db = authDb();

    $stmt = $db->prepare(
        'SELECT
            role,
            is_active
         FROM auth_users
         WHERE user_id = :id
         LIMIT 1'
    );

    $stmt->execute([
        'id' => (int) $_SESSION['auth_user_id']
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || (int) $user['is_active'] !== 1) {
        authClear();

        if ($api) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'status' => 'SESSION_REVOKED'
            ]);

            exit;
        }

        header('Location: ' . authLoginUrl(), true, 303);
        exit;
    }

    $role = (string) $user['role'];

    if (!in_array($role, $allowedRoles, true)) {
        if ($api) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');

            echo json_encode([
                'success' => false,
                'status' => 'ACCESS_DENIED',
                'message' => 'Access denied.'
            ]);

            exit;
        }

        http_response_code(403);
        exit('Access denied.');
    }

    $_SESSION['auth_role'] = $role;
    $_SESSION['auth_last_activity'] = time();

    /*
     * ADMIN 30-day password rotation.
     *
     * SCHEDULER and TEACHER accounts are not changed by this rule.
     * Teacher password state continues to use teacher_accounts.
     */
    if ($role === 'ADMIN') {
        try {
            $passwordState = authAdminPasswordState(
                $db,
                (int) $_SESSION['auth_user_id']
            );
        } catch (Throwable $e) {
            error_log(
                'BCP admin password-state error: '
                . $e->getMessage()
            );

            if ($api) {
                http_response_code(503);
                header(
                    'Content-Type: application/json; charset=utf-8'
                );

                echo json_encode([
                    'success' => false,
                    'status' =>
                        'ACCOUNT_SECURITY_NOT_READY',
                    'message' =>
                        'Admin account security migration is required.'
                ]);

                exit;
            }

            http_response_code(503);
            exit(
                'Admin account security is not ready. '
                . 'Run migration 018_admin_account_security.sql.'
            );
        }

        $_SESSION['auth_password_expired'] =
            $passwordState['expired'] ? 1 : 0;

        $_SESSION['auth_password_warning'] =
            $passwordState['warning'] ? 1 : 0;

        $_SESSION['auth_password_days_remaining'] =
            $passwordState['days_remaining'];

        if (
            $passwordState['expired']
            && !$allowExpiredAdminPassword
        ) {
            if ($api) {
                http_response_code(428);
                header(
                    'Content-Type: application/json; charset=utf-8'
                );

                echo json_encode([
                    'success' => false,
                    'status' =>
                        'PASSWORD_CHANGE_REQUIRED',
                    'message' =>
                        'Your administrator password has expired.',
                    'account_url' => authAccountUrl()
                ]);

                exit;
            }

            header(
                'Location: '
                . authAccountUrl()
                . '?required=1',
                true,
                303
            );

            exit;
        }
    } else {
        unset(
            $_SESSION['auth_password_expired'],
            $_SESSION['auth_password_warning'],
            $_SESSION['auth_password_days_remaining']
        );
    }

    if (
        time() - (int) (
            $_SESSION['auth_regenerated_at'] ?? 0
        ) > 900
    ) {
        session_regenerate_id(true);

        $_SESSION['auth_regenerated_at'] = time();
    }
}
