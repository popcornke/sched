-- Apply once after migration 022. Soft deletion affects only Academic Office history visibility.
-- Approved schedule_batches, schedule_meetings, and request/audit data are retained.
ALTER TABLE academic_preview_requests
 ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
 ADD COLUMN deleted_by INT UNSIGNED NULL DEFAULT NULL,
 ADD INDEX idx_preview_history_delete (deleted_at, archived_at, status);
