<?php

declare(strict_types=1);

/**
 * Lightweight system-wide audit writer.
 *
 * The audit table is introduced by migration 027_super_admin.sql.  This helper
 * intentionally fails open: a logging problem must never corrupt or block a
 * scheduling transaction.  Callers may inspect the boolean return value when
 * they need to show diagnostics.
 */
function auditClientIp(): ?string
{
    $value = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? ''));
    if ($value === '') {
        return null;
    }

    if (str_contains($value, ',')) {
        $value = trim(explode(',', $value, 2)[0]);
    }

    return substr($value, 0, 64);
}

function auditLog(
    PDO $db,
    string $actionType,
    string $module,
    ?string $targetType = null,
    ?string $targetId = null,
    ?string $description = null,
    array $metadata = [],
    ?array $verifiedActor = null
): bool {
    try {
        $stmt = $db->prepare(
            'INSERT INTO system_audit_logs (
                actor_user_id,
                actor_username_snapshot,
                actor_role_snapshot,
                action_type,
                module,
                target_type,
                target_id,
                description,
                metadata_json,
                ip_address,
                user_agent
             ) VALUES (
                :actor_user_id,
                :actor_username,
                :actor_role,
                :action_type,
                :module,
                :target_type,
                :target_id,
                :description,
                :metadata_json,
                :ip_address,
                :user_agent
             )'
        );

        // A separate scheduling-preview PHP session may be active here.
        // A verified actor snapshot can be supplied by an authenticated endpoint.
        $identity = $verifiedActor ?? $_SESSION;
        $userId = (int) ($identity['auth_user_id'] ?? 0);
        $username = trim((string) ($identity['auth_username'] ?? ''));
        $role = trim((string) ($identity['auth_role'] ?? ''));

        $stmt->execute([
            'actor_user_id' => $userId > 0 ? $userId : null,
            'actor_username' => $username !== '' ? substr($username, 0, 80) : null,
            'actor_role' => $role !== '' ? substr($role, 0, 40) : null,
            'action_type' => substr(strtoupper(trim($actionType)), 0, 80),
            'module' => substr(trim($module), 0, 80),
            'target_type' => $targetType === null ? null : substr(trim($targetType), 0, 80),
            'target_id' => $targetId === null ? null : substr(trim($targetId), 0, 120),
            'description' => $description,
            'metadata_json' => $metadata === []
                ? null
                : json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            'ip_address' => auditClientIp(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
                : null,
        ]);

        return true;
    } catch (Throwable $e) {
        error_log('BCP audit log write failed: ' . $e->getMessage());
        return false;
    }
}

/** Authentication audit before the session is established. Only use a DB-resolved account. */
function auditAuthentication(PDO $db, string $action, ?array $account, string $detail): bool
{
    $userId = (int) ($account['user_id'] ?? 0);
    if ($userId <= 0) return false; // Do not expose whether unknown names exist.
    try {
        $stmt = $db->prepare('INSERT INTO system_audit_logs
            (actor_user_id, actor_username_snapshot, actor_role_snapshot,
             action_type, module, target_type, target_id, description, ip_address, user_agent)
             VALUES (:id, :username, :role, :action, :module, :target_type, :target_id,
                     :description, :ip, :agent)');
        return $stmt->execute([
            'id' => $userId,
            'username' => substr((string) ($account['username'] ?? ''), 0, 80),
            'role' => substr((string) ($account['role'] ?? ''), 0, 40),
            'action' => substr($action, 0, 80),
            'module' => 'AUTHENTICATION',
            'target_type' => 'AUTH_USER',
            'target_id' => (string) $userId,
            'description' => $detail,
            'ip' => auditClientIp(),
            'agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    } catch (Throwable $e) {
        error_log('BCP authentication audit failed: ' . $e->getMessage());
        return false;
    }
}



/**
 * Broad audit of authenticated UI navigation and operational HTTP requests.
 * REQUEST_COMPLETED means only the endpoint returned; it is NOT a successful
 * domain action. Committed changes must use auditLog after their DB commit.
 * No form bodies, OTPs, credentials, tokens, or free-text remarks are recorded.
 */
function auditTrackOperationalRequest(PDO $db): void
{
    static $registered = false;
    if ($registered) return;
    $role = strtoupper((string)($_SESSION['auth_role'] ?? ''));
    if (!in_array($role, ['SUPER_ADMIN','ADMIN','SCHEDULER','ACADEMIC_OFFICE','STAFF'], true)) return;
    $actorId = (int)($_SESSION['auth_user_id'] ?? 0);
    if ($actorId <= 0) return;

    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $route = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    $filename = strtolower(basename($route));
    if ($route === '' || !in_array($method, ['GET','POST','PUT','PATCH','DELETE'], true)) return;

    // Avoid flooding on automatic polling, notification refresh, health checks.
    $ignore = ['session-status.php','db-health.php','python-health.php',
       'notifications.php','generate-status.php','academic-preview-status.php',
       'exam-approval-status.php','report-data.php'];
    if (in_array($filename, $ignore, true)) return;

    $isWrite = in_array($method, ['POST','PUT','PATCH','DELETE'], true);
    $isApi = str_contains($route, '/api/') || str_ends_with($filename, '-api.php')
        || str_contains($filename, '-data.php') || str_contains($filename, '-status.php');
    $isDownload = (bool)preg_match('/(export|print|download|document|report)/', $filename);
    // All authenticated page navigation is tracked; API GETs are tracked only
    // for explicit user-facing exports. Passive AJAX read/poll is omitted.
    if (!$isWrite && ($isApi || $filename === 'shared.php') && !$isDownload) return;

    $module = 'GENERAL';
    foreach ([
        '/super-admin/' => 'SUPER_ADMIN',
        '/academic-office/' => 'ACADEMIC_OFFICE',
        '/staff/' => 'STAFF',
        '/modules/02-' => 'TEACHER_MAPPING',
        '/modules/03-' => 'CONFLICT_CHECKER',
        '/modules/04-' => 'EXAM_SCHEDULING',
        '/modules/05-' => 'SUBSTITUTE_TRACKER',
        '/modules/06-' => 'SPECIAL_CLASS_SCHEDULING',
        '/modules/07-' => 'ROOM_AVAILABILITY',
        '/modules/08-' => 'SCHEDULE_CLONING',
        '/modules/09-' => 'TIME_BLOCKS',
        '/modules/10-' => 'CALENDAR_INTEGRATION',
        '/section/' => 'SECTION_ASSIGNMENT',
        '/dashboard/' => 'DASHBOARD',
        '/api/' => 'SCHEDULING_API',
    ] as $prefix => $value) {
        if (str_contains($route, $prefix)) { $module = $value; break; }
    }
    $base = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_',pathinfo($filename, PATHINFO_FILENAME)) ?? 'ACTION');
    $actionName = '';
    if ($isWrite) {
        $rawAction = $_POST['action'] ?? $_POST['decision'] ?? $_GET['action'] ?? null;
        if (!is_string($rawAction)) {
            // Reading limited JSON to identify the requested action, not payload.
            $raw = file_get_contents('php://input');
            if (is_string($raw) && strlen($raw) <= 262144) {
                $json = json_decode($raw, true);
                if (is_array($json)) {
                    foreach (['action','decision','operation'] as $field) {
                        if (isset($json[$field]) && is_string($json[$field])) {
                            $rawAction = $json[$field]; break;
                        }
                    }
                }
            }
        }
        $actionName = is_string($rawAction) ? strtoupper(preg_replace('/[^A-Za-z0-9]+/', '_',substr($rawAction, 0, 32)) ?? '') : '';
    }
    $verb = $isWrite ? 'REQUEST' : ($isDownload ? 'EXPORT_VIEW' : 'PAGE_VIEW');
    $action = substr($verb . '_' . $base . ($actionName === '' ? '' : '_' . $actionName), 0, 80);
    $username = substr((string)($_SESSION['auth_username'] ?? ''), 0, 80);
    $registered = true;
    register_shutdown_function(static function () use ($db,$role,$actorId,$username,$method,$route,$module,$action): void {
        $status = http_response_code();
        $status = is_int($status) && $status > 0 ? $status : 200;
        $lastError = error_get_last();
        $fatal = $lastError && in_array($lastError['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true);
        $result = $fatal ? 'PHP_FATAL' : ($status >= 400 ? 'HTTP_ERROR' : 'REQUEST_COMPLETED');
        // Do not overwrite auth-related actor IDs from a session that closed.
        try {
            $stmt = $db->prepare('INSERT INTO system_audit_logs
                (actor_user_id,actor_username_snapshot,actor_role_snapshot,action_type,module,
                 target_type,target_id,description,metadata_json,ip_address,user_agent)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $actorId,$username,$role,$action,$module,'ROUTE',substr($route,0,120),
                'Authenticated interaction: ' . $result . ' (HTTP ' . $status . ').',
                json_encode(['method'=>$method,'http_status'=>$status,'outcome'=>$result], JSON_INVALID_UTF8_SUBSTITUTE),
                auditClientIp(),substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,255),
            ]);
        } catch (Throwable $e) {
            error_log('BCP request audit failed: ' . $e->getMessage());
        }
    });
}
