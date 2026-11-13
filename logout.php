<?php

declare(strict_types=1);

require_once __DIR__ . '/app/shared/auth.php';
require_once __DIR__ . '/app/shared/audit.php';

authStart();
authNoCache();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!authCsrfValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid logout request.');
}

if (isset($_SESSION['auth_user_id'])) {
    try { auditLog(authDb(), 'LOGOUT', 'AUTHENTICATION', 'AUTH_USER', (string) $_SESSION['auth_user_id'], 'Account signed out.'); }
    catch (Throwable $e) { error_log('BCP logout audit error: ' . $e->getMessage()); }
}
authClear();

header('Clear-Site-Data: "cache"');

// Return to the correct login page in both local and hosted environments.
header(
    'Location: ' . authLoginUrl(),
    true,
    303
);

exit;
