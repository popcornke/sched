<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/audit.php';

authStart();
authNoCache();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!authCsrfValid($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Invalid logout request.');
}

if (isset($_SESSION['auth_user_id'])) {
    try { auditLog(authDb(), 'LOGOUT', 'AUTHENTICATION', 'AUTH_USER', (string) $_SESSION['auth_user_id'], 'Teacher signed out.'); }
    catch (Throwable $e) { error_log('BCP logout audit error: ' . $e->getMessage()); }
}
authClear();
header('Clear-Site-Data: "cache"');
header('Location: /sched/app/teacher/teacher-login.php', true, 303);
exit;
