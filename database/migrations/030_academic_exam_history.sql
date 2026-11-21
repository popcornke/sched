-- Run once after 029_academic_exam_approval.sql.
-- Non-destructive: preserves all exam schedule batches, approvals and audit data.
ALTER TABLE academic_exam_requests
  ADD COLUMN archived_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN archived_by INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN deleted_by INT UNSIGNED NULL DEFAULT NULL,
  ADD COLUMN purged_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN purged_by INT UNSIGNED NULL DEFAULT NULL,
  ADD INDEX idx_exam_history_visibility (status, archived_at, deleted_at, purged_at);
