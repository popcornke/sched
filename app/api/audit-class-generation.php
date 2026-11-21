<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/shared/auth.php';
require_once dirname(__DIR__) . '/shared/audit.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function genAuditReply(int $status, string $message, bool $ok = false): never {
    http_response_code($status);
    echo json_encode(['success' => $ok, 'message' => $message]);
    exit;
}
authRequire(true, ['ADMIN', 'SCHEDULER']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') genAuditReply(405, 'POST required');
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data) || !authCsrfValid($data['csrf_token'] ?? null)) genAuditReply(403, 'Invalid CSRF');
$token = $data['save_token'] ?? null;
if (!is_string($token) || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) genAuditReply(422, 'Invalid preview token');
$verifiedActor = [
    'auth_user_id' => (int)($_SESSION['auth_user_id'] ?? 0),
    'auth_username' => (string)($_SESSION['auth_username'] ?? ''),
    'auth_role' => (string)($_SESSION['auth_role'] ?? ''),
];
session_write_close();
$sid = $_COOKIE['BCP_SCHED_DEMO'] ?? null;
if (!is_string($sid) || !preg_match('/\A[A-Za-z0-9,-]{16,128}\z/D', $sid)) genAuditReply(409, 'Preview session missing');
session_name('BCP_SCHED_DEMO');
session_id($sid);
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'path' => '/']);
if (!session_start()) genAuditReply(500, 'Preview session unavailable');
$preview = $_SESSION['bcp_schedule_preview'] ?? null;
if (!is_array($preview)
    || !hash_equals((string)($preview['token'] ?? ''), $token)
    || time() - (int)($preview['created_at'] ?? 0) > 1200
    || ($preview['result']['status'] ?? '') !== 'DEMO_PREVIEW_GENERATED'
    || ($preview['result']['audit']['passed'] ?? false) !== true
    || ($preview['result']['existing_snapshot_constraints_applied'] ?? false) !== true
    || ($preview['result']['success'] ?? false) !== true) {
    session_write_close();
    genAuditReply(409, 'No matching audited preview');
}
if (($preview['generation_audit_logged'] ?? false) === true) {
    session_write_close();
    genAuditReply(200, 'Generation already audited', true);
}
$program = $preview['program'] ?? [];
$period = $preview['academic_period'] ?? [];
$ok = auditLog(authDb(), 'CLASS_SCHEDULE_GENERATED', 'CLASS_SCHEDULING', 'PROGRAM',
    (string)($program['program_id'] ?? ''), 'Admin generated an audited class timetable preview; not yet saved.',
    ['program_code' => (string)($program['program_code'] ?? ''),
     'period_id' => (int)($period['academic_period_id'] ?? 0),
     'meeting_count' => count($preview['result']['assignments'] ?? []),
     'outcome' => 'PREVIEW_ONLY'], $verifiedActor);
if ($ok) $_SESSION['bcp_schedule_preview']['generation_audit_logged'] = true;
session_write_close();
if (!$ok) genAuditReply(500, 'Audit log write failed');
genAuditReply(200, 'Generation audited', true);
