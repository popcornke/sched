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
    array $metadata = []
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

        $userId = (int) ($_SESSION['auth_user_id'] ?? 0);
        $username = trim((string) ($_SESSION['auth_username'] ?? ''));
        $role = trim((string) ($_SESSION['auth_role'] ?? ''));

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
