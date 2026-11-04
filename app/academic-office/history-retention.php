<?php
declare(strict_types=1);
/**
 * Retire trash entries past their 15-day restore window, without removing
 * legal/audit provenance or schedule batches and meetings.
 * Idempotent; also invoked on reads/actions so no privileged DB event is required.
 */
function acadExpireDeletedHistory(PDO $db): void {
    $db->exec("UPDATE academic_preview_requests
        SET purged_at = NOW(), purged_by = NULL
        WHERE deleted_at IS NOT NULL AND purged_at IS NULL
          AND deleted_at <= DATE_SUB(NOW(), INTERVAL 15 DAY)
          AND status IN ('APPROVED', 'REJECTED')");
}
