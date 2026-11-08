<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/audit.php';

authStart();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || !authCsrfValid($_POST['csrf_token'] ?? null)) {
    http_response_code(405);
    exit('Invalid sign-out request.');
}

try {
    if (($_SESSION['auth_role'] ?? '') === 'SUPER_ADMIN') {
        auditLog(authDb(), 'LOGOUT', 'SUPER_ADMIN_AUTH', 'AUTH_USER', (string) ($_SESSION['auth_user_id'] ?? ''), 'Super Administrator signed out.');
    }
} catch (Throwable $e) {
    error_log('BCP super-admin logout audit: ' . $e->getMessage());
}

authClear();
header('Location: login.php', true, 303);
exit;
