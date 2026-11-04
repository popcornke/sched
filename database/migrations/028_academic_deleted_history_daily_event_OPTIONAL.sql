-- OPTIONAL: Execute only if your MariaDB/MySQL event_scheduler is ON and
-- your Railway DB account has EVENT permission. This enables unattended
-- daily retirement of entries after 15 days, even if nobody opens the app.
-- Check: SHOW VARIABLES LIKE 'event_scheduler';
-- This never deletes approved schedules, requests, or audit rows.
CREATE EVENT IF NOT EXISTS academic_deleted_history_daily_retention
ON SCHEDULE EVERY 1 DAY
DO
 UPDATE academic_preview_requests
 SET purged_at=NOW(), purged_by=NULL
 WHERE deleted_at IS NOT NULL AND purged_at IS NULL
   AND deleted_at <= DATE_SUB(NOW(), INTERVAL 15 DAY)
   AND status IN ('APPROVED','REJECTED');
