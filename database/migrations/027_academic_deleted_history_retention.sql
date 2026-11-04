-- Apply once AFTER 023. Retains actual approved schedules and immutable approval evidence.
-- "Permanent delete" permanently removes an entry from Academic Office history UI,
-- while preserving the underlying request/audit record for integrity.
ALTER TABLE academic_preview_requests
  ADD COLUMN purged_at DATETIME NULL DEFAULT NULL,
  ADD COLUMN purged_by INT UNSIGNED NULL DEFAULT NULL,
  ADD INDEX idx_academic_deleted_retention (deleted_at, purged_at);
